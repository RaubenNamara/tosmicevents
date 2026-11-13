<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');

define('LOG_DIR', APP_ROOT . '/storage/logs');

$CONFIG = require APP_ROOT . '/config.php';
date_default_timezone_set($CONFIG['timezone']);

/* ---------------- Errors: details go to a private log, never to visitors ---------------- */
// 'debug' => 'auto' shows errors only when the site is opened on this computer (localhost).
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ['127.0.0.1', '::1'], true)
    && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $_SERVER['HTTP_HOST'] ?? 'localhost');
define('APP_DEBUG', PHP_SAPI === 'cli' || ($CONFIG['debug'] === 'auto' ? (bool) $isLocal : (bool) $CONFIG['debug']));

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0750, true);
ini_set('error_log', LOG_DIR . '/php-errors.log');

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (PHP_SAPI === 'cli' || APP_DEBUG) {
        throw $e;
    }
    if (!headers_sent()) http_response_code(500);
    // generic page: no paths, queries or database details
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Something went wrong</title>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#120f28;color:#fff;font-family:system-ui,sans-serif;text-align:center;padding:20px">'
        . '<div><h1 style="color:#f5c842">Something went wrong</h1><p>Please try again in a moment, or call us directly.</p><p><a href="/" style="color:#ec3b9a">Back to the home page</a></p></div>';
    exit;
});

/* ---------------- Session hardening ---------------- */
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');   // refuse session IDs the server did not create
    ini_set('session.use_only_cookies', '1');  // never accept a session ID from the URL
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_name('tosmic_sess');
    session_set_cookie_params([
        'httponly' => true,                    // JavaScript cannot read the cookie
        'samesite' => 'Lax',                   // not sent on cross-site form posts
        'secure' => is_https(),                // HTTPS-only cookie on the live site
        'path' => '/',
    ]);
    session_start();
}

/* ---------------- Security headers ---------------- */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Per-request random value that lets our own inline script run under the Content-Security-Policy. */
function csp_nonce(): string
{
    static $n = null;
    return $n ??= base64_encode(random_bytes(16));
}

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    // Only our own scripts (plus the one inline line carrying the nonce) may run; no plugins, no framing by other sites,
    // forms may only post back to this site. Styles/fonts are allowed from Google Fonts and cdnjs (Font Awesome).
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-" . csp_nonce() . "'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; "
        . "img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
        . (is_https() ? '; upgrade-insecure-requests' : ''));
    if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/* ---------------- Database ---------------- */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $CONFIG['db_host'], $CONFIG['db_port'], $CONFIG['db_name']);
        $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/* ---------------- URLs ---------------- */
function base_url(): string
{
    static $base = null;
    global $CONFIG;
    if ($base !== null) return $base;
    if ($CONFIG['base_url'] !== '') return $base = rtrim($CONFIG['base_url'], '/');
    $doc = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $app = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $rel = '';
    if ($doc !== '' && stripos($app, $doc) === 0) {
        $rel = substr($app, strlen($doc));
    }
    return $base = rtrim($rel, '/');
}

/**
 * Site link. Pages are written as 'about.php' in code but printed as clean addresses:
 * 'about.php' -> /about, 'index.php' -> /, 'admin/booking.php?id=3' -> /admin/booking?id=3.
 * .htaccess maps the clean address back to the .php file.
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if (!preg_match('#^(assets|uploads)/#', $path)) {
        $path = preg_replace('#(^|/)index\.php(?=$|[?\#])#', '$1', $path);
        $path = preg_replace('#\.php(?=$|[?\#])#', '', $path);
    }
    return base_url() . '/' . $path;
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** URL of an uploaded or seeded image ('seed/x.jpg' lives in assets/img, everything else in uploads). */
function img_url(?string $path): string
{
    if (!$path) return url('assets/img/seed/logo.jpg');
    if (str_starts_with($path, 'seed/')) return url('assets/img/' . $path);
    return url('uploads/' . $path);
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

/* ---------------- Output helpers ---------------- */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int|float|string|null $amount): string
{
    return number_format((float) $amount) . '/=';
}

function lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text))));
}

function phone_href(string $phone): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
}

function whatsapp_link(string $text = ''): string
{
    $num = preg_replace('/\D/', '', setting('whatsapp', ''));
    return 'https://wa.me/' . $num . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** WhatsApp link to a client's number; local Ugandan numbers like 0772... become 256772... */
function whatsapp_link_to(string $phone, string $text = ''): string
{
    $num = preg_replace('/\D/', '', $phone);
    if (str_starts_with($num, '0')) $num = '256' . substr($num, 1);
    return 'https://wa.me/' . $num . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** The two background video slots: setting key => built-in clip used when nothing is uploaded. */
const HERO_VIDEO_SLOTS = [
    'hero_video' => ['file' => 'video/hero-crowd.mp4', 'label' => 'concert crowd, blue and pink lights'],
    'hero_video_2' => ['file' => 'video/hero-festival.mp4', 'label' => 'festival crowd, gold stage lights'],
];

/** Home page background videos, played one after the other. Empty when switched off. */
function hero_videos(): array
{
    if (setting('hero_video_on', '1') !== '1') return [];
    $list = [];
    foreach (HERO_VIDEO_SLOTS as $key => $slot) {
        $list[] = hero_video_slot($key);
    }
    return $list;
}

function hero_video_slot(string $key): array
{
    $custom = setting($key);
    if ($custom !== '' && is_file(UPLOAD_DIR . '/' . $custom)) {
        return ['src' => url('uploads/' . $custom), 'type' => str_ends_with($custom, '.webm') ? 'video/webm' : 'video/mp4', 'custom' => true];
    }
    return ['src' => asset(HERO_VIDEO_SLOTS[$key]['file']), 'type' => 'video/mp4', 'custom' => false];
}

/** Validates and stores an uploaded MP4/WebM video. Returns the path relative to uploads/. */
function save_video_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException($file['error'] === UPLOAD_ERR_INI_SIZE ? 'The video is larger than the server allows (check upload_max_filesize in php.ini).' : 'The video upload failed.');
    }
    if ($file['size'] > 60 * 1024 * 1024) throw new RuntimeException('Videos must be 60 MB or smaller. A short 10 to 20 second loop works best.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['video/mp4' => 'mp4', 'video/webm' => 'webm'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Please upload an MP4 or WebM video.');
    $dir = UPLOAD_DIR . '/video';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) throw new RuntimeException('The video could not be saved.');
    return "video/$name";
}

/* ---------------- Settings ---------------- */
function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT skey, svalue FROM settings')->fetchAll() as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) && $all[$key] !== '' ? (string) $all[$key] : $default;
}

/* ---------------- Flash + CSRF ---------------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(403);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/* ---------------- Image uploads ---------------- */
/**
 * Validates an uploaded image, re-encodes it with GD (which strips anything hidden in the file)
 * and resizes it to fit $maxW. Returns the stored path relative to uploads/, or throws.
 */
function save_upload(array $file, string $folder, int $maxW = 1920): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $codes = [UPLOAD_ERR_INI_SIZE => 'The image is larger than the server allows.', UPLOAD_ERR_FORM_SIZE => 'The image is too large.', UPLOAD_ERR_NO_FILE => 'No image was chosen.'];
        throw new RuntimeException($codes[$file['error'] ?? 4] ?? 'The upload failed, please try again.');
    }
    if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('Images must be 10 MB or smaller.');
    $info = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    // every accepted format is converted to WebP when saved
    $readers = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/gif' => 'imagecreatefromgif',
        'image/bmp' => 'imagecreatefrombmp',
        'image/x-ms-bmp' => 'imagecreatefrombmp',
        'image/avif' => 'imagecreatefromavif',
    ];
    if (!$info || !isset($readers[$mime]) || !function_exists($readers[$mime])) {
        throw new RuntimeException('Please upload a photo (JPG, PNG, WebP, GIF, BMP or AVIF). iPhone HEIC photos: set the camera to "Most Compatible" or share as JPG.');
    }
    $src = @($readers[$mime])($file['tmp_name']);
    if (!$src) throw new RuntimeException('That image could not be read.');

    // WebP needs true colour; keep transparency from PNG/GIF
    if (!imageistruecolor($src)) imagepalettetotruecolor($src);
    imagealphablending($src, false);
    imagesavealpha($src, true);

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $o = $exif['Orientation'] ?? 1;
        if ($o == 3) $src = imagerotate($src, 180, 0);
        elseif ($o == 6) $src = imagerotate($src, -90, 0);
        elseif ($o == 8) $src = imagerotate($src, 90, 0);
    }

    $w = imagesx($src); $h = imagesy($src);
    if ($w > $maxW) {
        $nh = (int) round($h * $maxW / $w);
        $dst = imagecreatetruecolor($maxW, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
        unset($src);
        $src = $dst;
    }

    $dir = UPLOAD_DIR . '/' . $folder;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.webp';
    $ok = imagewebp($src, "$dir/$name", 84);
    unset($src);
    if (!$ok) throw new RuntimeException('The image could not be saved.');
    return "$folder/$name";
}

function delete_upload(?string $path): void
{
    if (!$path || str_starts_with($path, 'seed/')) return;
    $full = realpath(UPLOAD_DIR . '/' . $path);
    $root = realpath(UPLOAD_DIR);
    if ($full && $root && str_starts_with($full, $root) && is_file($full)) @unlink($full);
}

/** Normalise the $_FILES layout of a multiple-file input into a list of single files. */
function files_list(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/* ---------------- Rate limiting (stored on the server, not in the visitor's cookie) ---------------- */
function client_ip(): string
{
    // REMOTE_ADDR only: X-Forwarded-For is set by the client and can be faked.
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** True when this IP (or this email) has failed to sign in too often recently. */
function login_locked(string $email): bool
{
    q('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    $byIp = (int) q('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE', [client_ip()])->fetchColumn();
    $byEmail = $email === '' ? 0 : (int) q('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE', [$email])->fetchColumn();
    return $byIp >= 10 || $byEmail >= 5;
}

function login_failed(string $email): void
{
    q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [client_ip(), mb_substr($email, 0, 190)]);
}

function login_succeeded(string $email): void
{
    q('DELETE FROM login_attempts WHERE email = ? OR ip = ?', [$email, client_ip()]);
}

/** Writes one row to the admin sign-in log. Failed attempts are only logged for emails that belong to an admin. */
function log_admin_login(?int $adminId, string $email, bool $success): void
{
    if (!$success && $adminId === null) return;
    q('INSERT INTO admin_logins (admin_id, email, success, ip, user_agent) VALUES (?,?,?,?,?)', [
        $adminId, mb_substr($email, 0, 190), $success ? 1 : 0, client_ip(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

/** "Chrome on Windows" style label from a browser user-agent string. */
function device_label(?string $ua): string
{
    $ua = (string) $ua;
    $browser = match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
        str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
        str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Safari/') => 'Safari',
        default => 'Browser',
    };
    $os = match (true) {
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'iPhone') => 'iPhone',
        str_contains($ua, 'iPad') => 'iPad',
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Mac OS') => 'Mac',
        str_contains($ua, 'Linux') => 'Linux',
        default => 'unknown device',
    };
    return "$browser on $os";
}

/* ---------------- Auth ---------------- */
function admin_pw_fingerprint(string $hash): string
{
    return hash('sha256', $hash);
}

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) return $admin;
    $id = $_SESSION['admin_id'] ?? null;
    $row = $id ? (q('SELECT id, name, email, password_hash FROM admins WHERE id = ?', [$id])->fetch() ?: null) : null;
    // the session remembers a fingerprint of the password; after a password change, older sessions stop working
    if ($row && !hash_equals(admin_pw_fingerprint($row['password_hash']), (string) ($_SESSION['pw_fp'] ?? ''))) $row = null;
    if ($row) unset($row['password_hash']);
    return $admin = $row;
}

function require_admin(): array
{
    $a = current_admin();
    if (!$a) redirect('admin/login.php');
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 4 * 3600) {
        session_unset();
        session_regenerate_id(true);
        flash('info', 'You were signed out after a period of inactivity.');
        redirect('admin/login.php');
    }
    $_SESSION['last_seen'] = time();
    return $a;
}

/* ---------------- Bookings ---------------- */
const BOOKING_STATUSES = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
];

const GALLERY_CATEGORIES = [
    'corporate' => 'Corporate',
    'social' => 'Social',
    'weddings' => 'Weddings',
    'parties' => 'Parties',
    'concerts' => 'Concerts',
    'other' => 'Other',
];
