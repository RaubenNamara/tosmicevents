<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/receipts.php';
require_admin();

$r = load_receipt('id', (int) ($_GET['id'] ?? 0));
if (!$r) {
    flash('error', 'That receipt was not found.');
    redirect('admin/bookings.php');
}
$isAdmin = true;
require dirname(__DIR__) . '/includes/receipt_page.php';
