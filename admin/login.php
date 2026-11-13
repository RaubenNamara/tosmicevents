<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (current_admin()) redirect('admin/index.php');

$error = '';
$email = '';

if (is_post()) {
    csrf_check();
    $email = mb_substr(strtolower(post('email')), 0, 190);
    if (login_locked($email)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $row = q('SELECT * FROM admins WHERE email = ?', [$email])->fetch();
        // always run one password check, so the response time does not reveal whether the email exists
        $hash = $row['password_hash'] ?? '$2y$10$N5EZeE/u/yDs200lbezxZ.Wwf5YgY6qr7.CNNN6Y8rhAaD2bcpSWy';
        $password = is_string($_POST['password'] ?? null) ? substr($_POST['password'], 0, 200) : '';
        $ok = password_verify($password, $hash) && $row;
        if ($ok) {
            session_regenerate_id(true);                 // new session ID on sign-in (stops session fixation)
            $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'admin_id' => (int) $row['id'], 'last_seen' => time(), 'pw_fp' => admin_pw_fingerprint($row['password_hash'])];
            login_succeeded($email);
            log_admin_login((int) $row['id'], $email, true);
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            q('UPDATE admins SET last_login = NOW() WHERE id = ?', [$row['id']]);
            redirect('admin/index.php');
        }
        login_failed($email);
        log_admin_login($row ? (int) $row['id'] : null, $email, false);
        usleep(random_int(300000, 600000));
        $error = 'That email and password do not match.';
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · Tosmic Admin</title>
<link rel="icon" href="<?= e(url('assets/img/seed/logo.jpg')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="login-body">
  <div class="login-art" aria-hidden="true">
    <div class="login-ring"><img src="<?= e(url('assets/img/seed/dj.jpg')) ?>" alt=""></div>
    <h2>DJ TOSMIC<br><span>Booking packages</span></h2>
  </div>
  <div class="login-panel">
    <form method="post" class="login-card" novalidate>
      <?= csrf_field() ?>
      <img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt="Tosmic Events" class="login-logo">
      <h1>Welcome back</h1>
      <p class="muted">Sign in to manage bookings and the website.</p>
      <?php foreach (take_flashes() as $f): ?><div class="flash flash-<?= e($f['type']) ?>"><span><?= e($f['msg']) ?></span></div><?php endforeach; ?>
      <?php if ($error): ?><div class="flash flash-error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= e($error) ?></span></div><?php endif; ?>
      <label class="fld"><span>Email</span><input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
      <label class="fld"><span>Password</span>
        <span class="pw-wrap"><input type="password" name="password" required autocomplete="current-password" id="pw"><button type="button" class="pw-eye" data-toggle-pw="pw" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></span>
      </label>
      <button class="btn btn-pink btn-block" type="submit">Sign in <i class="fa-solid fa-arrow-right"></i></button>
      <a href="<?= e(url('index.php')) ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to website</a>
    </form>
  </div>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
