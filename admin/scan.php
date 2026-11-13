<?php
/**
 * Admin receipt scanner: reads a receipt QR code with the camera (or a photo, or a typed number)
 * and checks it against the database.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/receipts.php';
require_admin();

/* ---------- lookup endpoint (POST, JSON reply) ---------- */
if (is_post()) {
    csrf_check();
    header('Content-Type: application/json; charset=utf-8');
    $raw = trim(mb_substr((string) ($_POST['code'] ?? ''), 0, 500));

    // What was scanned? A verify link (no + check code), a private receipt link (token), or a bare receipt number.
    $no = null; $k = null; $token = null;
    if (preg_match('~[?&]no=(TR-\d{4}-\d{4,6})~i', $raw, $m)) {
        $no = strtoupper($m[1]);
        if (preg_match('~[?&]k=([a-f0-9]{12})~i', $raw, $m2)) $k = strtolower($m2[1]);
    } elseif (preg_match('~[?&]t=([a-f0-9]{40})~i', $raw, $m)) {
        $token = strtolower($m[1]);
    } elseif (preg_match('~^\s*(TR-\d{4}-\d{4,6})\s*$~i', $raw, $m)) {
        $no = strtoupper($m[1]);
    }

    if (!$no && !$token) {
        echo json_encode(['state' => 'invalid', 'message' => 'This is not a Tosmic Events receipt code.']);
        exit;
    }
    $row = $token ? q('SELECT id, token FROM receipts WHERE token = ?', [$token])->fetch()
                  : q('SELECT id, token FROM receipts WHERE receipt_no = ?', [$no])->fetch();
    if (!$row) {
        echo json_encode(['state' => 'notfound', 'message' => 'No receipt ' . ($no ?? '') . ' exists in the system.']);
        exit;
    }
    // a QR whose check code does not match was not printed by this system
    if ($k !== null && !hash_equals(substr($row['token'], 0, 12), $k)) {
        echo json_encode(['state' => 'forged', 'message' => 'The receipt number exists, but this QR code does not match it. It was not printed by this system: treat it as a possible fake.', 'receipt_no' => $no]);
        exit;
    }
    $r = load_receipt('id', (int) $row['id']);
    $bk = q('SELECT b.*, p.price AS package_price FROM bookings b LEFT JOIN packages p ON p.id = b.package_id WHERE b.id = ?', [$r['booking_id']])->fetch();
    $now = booking_payments($bk);
    $state = $r['is_void'] ? 'void' : ($r['full'] ? 'paid' : 'part');
    echo json_encode([
        'state' => $state,
        'checked_by' => $k !== null || $token !== null ? 'qr' : 'number',
        'receipt_no' => $r['receipt_no'],
        'booking_ref' => $r['booking_ref'],
        'client' => $r['full_name'],
        'phone' => $r['phone'],
        'package' => $r['package_name'] ?? 'Custom DJ package',
        'event_date' => $r['event_date'] ? date('D d M Y', strtotime($r['event_date'])) : null,
        'amount' => (int) $r['amount'],
        'method' => $r['method'],
        'paid_on' => date('d M Y', strtotime($r['paid_on'])),
        'received_by' => $r['received_by'],
        'void_reason' => $r['void_reason'],
        // where the booking stands today (later payments included)
        'agreed' => $now['total'],
        'paid_total' => $now['paid'],
        'balance' => $now['balance'],
        'booking_status' => BOOKING_STATUSES[$bk['status']] ?? $bk['status'],
        'booking_paid' => (bool) $bk['is_paid'],
        'receipt_url' => url('admin/receipt.php?id=' . $r['id']),
        'booking_url' => url('admin/booking.php?id=' . $r['booking_id']),
    ]);
    exit;
}

admin_top('Scan receipt', 'scan');
?>
<div class="scan-grid">
  <section class="card scan-cam">
    <div class="card-head"><h2><i class="fa-solid fa-qrcode" style="color:var(--pink)"></i> Camera</h2>
      <div class="toolbar">
        <button type="button" class="btn btn-light btn-sm" id="camSwitch" hidden><i class="fa-solid fa-camera-rotate"></i><span class="lbl"> Switch</span></button>
        <button type="button" class="btn btn-light btn-sm" id="camStop" hidden><i class="fa-solid fa-stop"></i> Stop</button>
      </div>
    </div>
    <div class="card-body">
      <div class="viewfinder" id="viewfinder">
        <video id="camVideo" playsinline muted></video>
        <div class="vf-frame" aria-hidden="true"><i></i><i></i><i></i><i></i><span class="vf-line"></span></div>
        <div class="vf-idle" id="vfIdle">
          <i class="fa-solid fa-camera"></i>
          <p>Point the camera at the QR code on a Tosmic receipt.</p>
          <button type="button" class="btn btn-pink" id="camStart"><i class="fa-solid fa-video"></i> Start camera</button>
          <small class="muted" id="camMsg"></small>
        </div>
      </div>
      <canvas id="camCanvas" hidden></canvas>

      <div class="scan-alt">
        <label class="btn btn-light btn-sm"><i class="fa-regular fa-image"></i> Scan from a photo<input type="file" accept="image/*" id="scanFile" hidden></label>
        <form id="manualForm" class="scan-manual">
          <input id="manualNo" placeholder="or type TR-2026-0001" maxlength="20" autocomplete="off" aria-label="Receipt number">
          <button class="btn btn-navy btn-sm">Check</button>
        </form>
      </div>
    </div>
  </section>

  <section class="card scan-result" id="scanResult" aria-live="polite">
    <div class="sr-empty">
      <i class="fa-solid fa-receipt"></i>
      <h3>Waiting for a receipt</h3>
      <p class="muted">Scan the QR code at the bottom of a receipt. The result shows here: whether the receipt is real, how much was paid and what is still owed.</p>
    </div>
  </section>
</div>
<input type="hidden" id="scanCsrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" id="scanEndpoint" value="<?= e(url('admin/scan.php')) ?>">
<script src="<?= e(asset('vendor/jsQR.js')) ?>"></script>
<script src="<?= e(asset('js/scan.js')) ?>"></script>
<?php admin_bottom(); ?>
