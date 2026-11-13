<?php
/**
 * Receipt verification.
 *  - QR scan: verify?no=TR-2026-0001&k=<12-char check code>  (no and k must belong to the same receipt)
 *  - By hand: receipt number + booking reference, both must match the same receipt
 * Only a masked name and payment facts are shown, never phone numbers or the full private receipt.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/components.php';
require __DIR__ . '/includes/receipts.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

$state = null;      // 'valid' | 'void' | 'notfound' | 'limited'
$r = null;
$no = strtoupper(trim((string) ($_GET['no'] ?? $_POST['no'] ?? '')));
$bookingRef = strtoupper(trim((string) ($_POST['booking_ref'] ?? '')));

// failed look-ups are counted per IP on the server, so guessing numbers is slowed right down
$tooMany = fn() => (int) q("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = '~verify' AND attempted_at > NOW() - INTERVAL 15 MINUTE", [client_ip()])->fetchColumn() >= 15;
$fail = fn() => q("INSERT INTO login_attempts (ip, email) VALUES (?, '~verify')", [client_ip()]);

$validNo = (bool) preg_match('/^TR-\d{4}-\d{4,6}$/', $no);

if (isset($_GET['k']) && $no !== '') {
    $k = (string) $_GET['k'];
    if ($tooMany()) {
        $state = 'limited';
    } else {
        $row = $validNo && preg_match('/^[a-f0-9]{12}$/', $k) ? q('SELECT id, token FROM receipts WHERE receipt_no = ?', [$no])->fetch() : null;
        if ($row && hash_equals(substr($row['token'], 0, 12), $k)) {
            $r = load_receipt('id', (int) $row['id']);
        } else {
            $fail();
        }
    }
} elseif (is_post()) {
    csrf_check();
    if ($tooMany()) {
        $state = 'limited';
    } else {
        $row = $validNo && preg_match('/^TE-\d{4}-[A-Z0-9]{5}$/', $bookingRef)
            ? q('SELECT r.id FROM receipts r JOIN bookings b ON b.id = r.booking_id WHERE r.receipt_no = ? AND b.ref = ?', [$no, $bookingRef])->fetch()
            : null;
        if ($row) $r = load_receipt('id', (int) $row['id']);
        else $fail();
    }
}
if ($state === null && ($r !== null || isset($_GET['k']) || is_post())) {
    $state = $r ? ($r['is_void'] ? 'void' : 'valid') : 'notfound';
}

$pageTitle = 'Verify a Receipt';
$activePage = '';
$pageDescription = 'Check that a Tosmic Events payment receipt is genuine.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero--slim">
  <div class="hero-glow" aria-hidden="true"></div>
  <div class="container page-hero-inner">
    <span class="eyebrow">Receipt check</span>
    <h1>Verify a receipt</h1>
    <p>Scan the QR code on a Tosmic Events receipt, or enter its details below.</p>
  </div>
</section>

<section class="section">
  <div class="container verify-wrap">
    <?php if ($state === 'valid' || $state === 'void'): ?>
      <div class="verify-card is-<?= $state ?> reveal">
        <div class="verify-icon"><i class="fa-solid <?= $state === 'valid' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i></div>
        <h2><?= $state === 'valid' ? 'Receipt verified' : 'This receipt was voided' ?></h2>
        <p class="verify-lead"><?= $state === 'valid'
            ? 'This is a genuine receipt issued by ' . e(setting('site_name', 'Tosmic Events')) . '.'
            : 'It was issued by ' . e(setting('site_name', 'Tosmic Events')) . ' but has since been cancelled and is no longer valid.' ?></p>
        <dl class="verify-dl">
          <dt>Receipt number</dt><dd><?= e($r['receipt_no']) ?></dd>
          <dt>Booking reference</dt><dd><?= e($r['booking_ref']) ?></dd>
          <dt>Received from</dt><dd><?= e(mask_name($r['full_name'])) ?></dd>
          <dt>Amount</dt><dd><b>UGX <?= e(number_format((int) $r['amount'])) ?></b></dd>
          <dt>Paid on</dt><dd><?= e(date('d M Y', strtotime($r['paid_on']))) ?> · <?= e($r['method']) ?></dd>
          <dt>For</dt><dd><?= e($r['package_name'] ?? 'Custom DJ package') ?><?= $r['event_date'] ? ', event on ' . e(date('d M Y', strtotime($r['event_date']))) : '' ?></dd>
          <?php if ($state === 'valid'): ?>
            <dt>Booking status</dt><dd><span class="vpill <?= $r['full'] ? 'ok' : 'part' ?>"><?= $r['full'] ? 'Paid in full' : 'Part payment' ?></span></dd>
          <?php else: ?>
            <dt>Reason</dt><dd><?= e($r['void_reason'] ?: 'Cancelled by Tosmic Events') ?></dd>
          <?php endif; ?>
        </dl>
        <p class="verify-small">Checked <?= e(date('d M Y, H:i')) ?>. For privacy, contact details are not shown here.</p>
      </div>
    <?php elseif ($state === 'notfound'): ?>
      <div class="verify-card is-bad reveal">
        <div class="verify-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h2>No matching receipt</h2>
        <p class="verify-lead">We could not find a receipt with those details in our system. Check the numbers, or call us on <a href="<?= e(phone_href(setting('phone1'))) ?>"><?= e(setting('phone1')) ?></a> before relying on it.</p>
      </div>
    <?php elseif ($state === 'limited'): ?>
      <div class="verify-card is-bad reveal">
        <div class="verify-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <h2>Please wait a little</h2>
        <p class="verify-lead">Too many checks from this connection. Try again in 15 minutes, or call us on <?= e(setting('phone1')) ?>.</p>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('verify.php')) ?>" class="form-card verify-form reveal" novalidate>
      <?= csrf_field() ?>
      <h3><i class="fa-solid fa-magnifying-glass"></i> Check by hand</h3>
      <p>Both numbers are printed on the receipt.</p>
      <div class="form-grid">
        <div class="field"><label for="v_no">Receipt number</label><div class="ico-input"><i class="fa-solid fa-receipt"></i><input id="v_no" name="no" value="<?= e($no) ?>" placeholder="TR-2026-0001" required maxlength="20" style="text-transform:uppercase"></div></div>
        <div class="field"><label for="v_ref">Booking reference</label><div class="ico-input"><i class="fa-solid fa-hashtag"></i><input id="v_ref" name="booking_ref" value="<?= e($bookingRef) ?>" placeholder="TE-2611-XXXXX" required maxlength="20" style="text-transform:uppercase"></div></div>
      </div>
      <button class="btn btn-pink btn-block" style="margin-top:14px"><i class="fa-solid fa-shield-halved"></i> Verify receipt</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
