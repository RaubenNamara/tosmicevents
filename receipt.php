<?php
/** Public receipt link sent to the client. Only works with the receipt's long random token. */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/receipts.php';

$token = (string) ($_GET['t'] ?? '');
$r = preg_match('/^[a-f0-9]{40}$/', $token) ? load_receipt('token', $token) : null;
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
if (!$r) {
    http_response_code(404);
    $pageTitle = 'Receipt not found';
    $activePage = '';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section" style="padding-top:calc(var(--header-h) + 60px);text-align:center"><div class="container"><h1>Receipt not found</h1><p style="color:var(--muted)">This receipt link is not valid. Please contact Tosmic Events.</p><a class="btn btn-pink" href="' . e(url('booking.php')) . '">Contact us</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$isAdmin = false;
require __DIR__ . '/includes/receipt_page.php';
