<?php
/** Payments and receipts for confirmed bookings. */

/** Stored/printed name => short label on the tick boxes. */
const PAYMENT_METHODS = [
    'Cash' => 'Cash',
    'MTN Mobile Money' => 'MTN',
    'Airtel Money' => 'Airtel',
    'Bank transfer' => 'Bank',
];

/** Statuses that allow taking money and issuing receipts. */
const PAYABLE_STATUSES = ['confirmed'];

/** The amount the client owes in total: the agreed amount, else the package price. */
function booking_total(array $b): ?int
{
    if ($b['quoted_amount'] !== null && $b['quoted_amount'] !== '') return (int) $b['quoted_amount'];
    if (!empty($b['package_price'])) return (int) $b['package_price'];
    return null;
}

/** Paid so far (void receipts excluded), the balance and a status word. */
function booking_payments(array $b): array
{
    $paid = (int) q('SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE booking_id = ? AND is_void = 0', [$b['id']])->fetchColumn();
    $total = booking_total($b);
    $balance = $total === null ? null : max(0, $total - $paid);
    $state = $paid === 0 ? 'unpaid' : (($total !== null && $paid >= $total) ? 'paid' : 'part');
    return ['paid' => $paid, 'total' => $total, 'balance' => $balance, 'state' => $state];
}

/**
 * Keep the booking's "Paid" tick in step with its receipts: ticked once receipts cover the agreed total,
 * unticked if a voided receipt takes it back below. Bookings with no receipts keep whatever the admin ticked.
 */
function sync_paid_flag(int $bookingId): void
{
    $b = q('SELECT b.id, b.quoted_amount, p.price AS package_price FROM bookings b LEFT JOIN packages p ON p.id = b.package_id WHERE b.id = ?', [$bookingId])->fetch();
    if (!$b) return;
    $hasReceipts = (int) q('SELECT COUNT(*) FROM receipts WHERE booking_id = ?', [$bookingId])->fetchColumn() > 0;
    $pay = booking_payments($b);
    if (!$hasReceipts || $pay['total'] === null) return;
    q('UPDATE bookings SET is_paid = ? WHERE id = ?', [$pay['paid'] >= $pay['total'] ? 1 : 0, $bookingId]);
}

/** Records one payment and returns [receipt id, receipt number]. Runs in a transaction so numbers never clash. */
function create_receipt(int $bookingId, int $amount, string $method, ?string $ref, string $paidOn, string $receivedBy, ?string $note, ?int $adminId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $no = next_receipt_no();
        q('INSERT INTO receipts (receipt_no, token, booking_id, amount, method, payment_ref, paid_on, received_by, note, admin_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $no, bin2hex(random_bytes(20)), $bookingId, $amount, $method, $ref ?: null, $paidOn, $receivedBy, $note ?: null, $adminId,
        ]);
        $rid = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    sync_paid_flag($bookingId);
    return [$rid, $no];
}

const PAYMENT_STATES = ['unpaid' => 'Unpaid', 'part' => 'Part paid', 'paid' => 'Paid in full'];

/** Next receipt number for this year: TR-2026-0001, TR-2026-0002 ... */
function next_receipt_no(): string
{
    $year = date('Y');
    $last = q("SELECT receipt_no FROM receipts WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1", ["TR-$year-%"])->fetchColumn();
    $n = $last ? (int) substr($last, -4) + 1 : 1;
    return sprintf('TR-%s-%04d', $year, $n);
}

/** Whole number in English words, e.g. 1500000 -> "one million five hundred thousand". */
function number_words(int $n): string
{
    if ($n === 0) return 'zero';
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $chunk = function (int $x) use ($ones, $tens): string {
        $out = [];
        if ($x >= 100) { $out[] = $ones[intdiv($x, 100)] . ' hundred'; $x %= 100; }
        if ($x >= 20) { $out[] = $tens[intdiv($x, 10)] . ($x % 10 ? '-' . $ones[$x % 10] : ''); }
        elseif ($x > 0) { $out[] = $ones[$x]; }
        return implode(' ', $out);
    };
    $parts = [];
    foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand', 1 => ''] as $size => $name) {
        if ($n >= $size) {
            $parts[] = trim($chunk(intdiv($n, $size)) . ' ' . $name);
            $n %= $size;
        }
    }
    return implode(' ', $parts);
}

/** Full data needed to draw one receipt. */
function load_receipt(string $field, string|int $value): ?array
{
    $col = $field === 'token' ? 'r.token' : 'r.id';
    $r = q("SELECT r.*, b.ref AS booking_ref, b.full_name, b.phone, b.email, b.event_type, b.event_date, b.start_time, b.venue,
                   b.status AS booking_status, b.quoted_amount, b.id AS bid, p.name AS package_name, p.price AS package_price
            FROM receipts r JOIN bookings b ON b.id = r.booking_id LEFT JOIN packages p ON p.id = b.package_id
            WHERE $col = ?", [$value])->fetch();
    if (!$r) return null;
    $booking = ['id' => $r['bid'], 'quoted_amount' => $r['quoted_amount'], 'package_price' => $r['package_price']];
    $r['total'] = booking_total($booking);
    // paid up to and including this receipt, so older receipts keep showing the balance at that time
    $r['paid_to_date'] = (int) q('SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE booking_id = ? AND is_void = 0 AND id <= ?', [$r['bid'], $r['id']])->fetchColumn();
    $r['balance'] = $r['total'] === null ? null : max(0, $r['total'] - $r['paid_to_date']);
    $r['full'] = $r['total'] !== null && $r['paid_to_date'] >= $r['total'];
    return $r;
}

function receipt_public_url(array $r): string
{
    $scheme = is_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('receipt.php?t=' . $r['token']);
}

/** Short check code printed in the QR: proves the scan came from a real receipt without exposing the full private link. */
function receipt_check_code(array $r): string
{
    return substr($r['token'], 0, 12);
}

/** Absolute address the receipt's QR code opens. */
function receipt_verify_url(array $r): string
{
    $scheme = is_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('verify.php?no=' . rawurlencode($r['receipt_no']) . '&k=' . receipt_check_code($r));
}

/** "Namara Rauben" -> "Namara R." so a verification page never shows a client's full name. */
function mask_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = array_shift($parts) ?: '';
    return $first . ($parts ? ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : '');
}

/** Bars for a decorative barcode built from the receipt number (same number always draws the same bars). */
function receipt_bars(string $text): array
{
    $bars = [];
    foreach (str_split(md5($text) . md5(strrev($text))) as $c) {
        $v = hexdec($c);
        $bars[] = [1 + ($v % 3), 1 + (($v >> 2) % 3)]; // [bar width, gap]
    }
    return $bars;
}
