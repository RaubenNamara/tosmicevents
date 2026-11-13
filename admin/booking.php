<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/receipts.php';
$admin = require_admin();

$id = (int) ($_GET['id'] ?? 0);
$b = q('SELECT b.*, p.name AS package_name, p.price AS package_price FROM bookings b LEFT JOIN packages p ON p.id = b.package_id WHERE b.id = ?', [$id])->fetch();
if (!$b) {
    flash('error', 'That booking no longer exists.');
    redirect('admin/bookings.php');
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    /* ---- void a receipt (kept for the record, no longer counted) ---- */
    if ($action === 'void_receipt') {
        q('UPDATE receipts SET is_void = 1, void_reason = ? WHERE id = ? AND booking_id = ?', [mb_substr(post('reason'), 0, 255) ?: 'Voided by admin', (int) post('receipt_id'), $id]);
        sync_paid_flag($id);
        flash('success', 'Receipt voided. It stays on record but is no longer counted.');
        redirect('admin/booking.php?id=' . $id . '#payments');
    }

    if ($action === 'delete') {
        if ((int) q('SELECT COUNT(*) FROM receipts WHERE booking_id = ? AND is_void = 0', [$id])->fetchColumn() > 0) {
            flash('error', 'This booking has payment receipts, so it cannot be deleted. Void the receipts first, or set the booking to Cancelled.');
            redirect('admin/booking.php?id=' . $id);
        }
        q('DELETE FROM bookings WHERE id = ?', [$id]);
        flash('success', 'Booking ' . $b['ref'] . ' was deleted.');
        redirect('admin/bookings.php');
    }

    /* ---- save status, agreed amount and (optionally) a payment ---- */
    $st = post('status');
    if (!isset(BOOKING_STATUSES[$st])) $st = $b['status'];
    $paid = isset($_POST['is_paid']) ? 1 : 0;
    $agreedRaw = preg_replace('/\D/', '', post('quoted_amount'));
    $agreed = $agreedRaw !== '' ? (int) $agreedRaw : null;
    $amountNow = (int) preg_replace('/\D/', '', post('amount_now'));

    $err = null;
    $method = post('method') ?: 'Cash';
    $paidOn = post('paid_on') ?: date('Y-m-d');
    if ($amountNow > 0) {
        $already = booking_payments($b)['paid'];
        $total = $agreed ?? booking_total($b);
        $d = DateTime::createFromFormat('Y-m-d', $paidOn);
        if ($st === 'cancelled') $err = 'A cancelled booking cannot take a payment. Choose Confirmed first.';
        elseif ($amountNow > 1000000000) $err = 'That amount is too large.';
        elseif ($total !== null && $already + $amountNow > $total) $err = 'That is more than the balance of UGX ' . number_format(max(0, $total - $already)) . '. Raise the agreed amount first if the price changed.';
        elseif (!isset(PAYMENT_METHODS[$method])) $err = 'Choose how the client paid.';
        elseif (!$d || $d->format('Y-m-d') !== $paidOn || $paidOn > date('Y-m-d')) $err = 'Enter a valid payment date (not in the future).';
        $st = 'confirmed';  // taking money confirms the booking
    }
    if ($err) {
        flash('error', $err);
        redirect('admin/booking.php?id=' . $id);
    }

    // "Paid" sits alongside "Confirmed": a paid booking is always confirmed, a cancelled one is never paid
    if ($paid && $st !== 'cancelled') $st = 'confirmed';
    if ($st === 'cancelled') $paid = 0;
    q('UPDATE bookings SET status = ?, is_paid = ?, quoted_amount = ? WHERE id = ?', [$st, $paid, $agreed, $id]);

    if ($amountNow > 0) {
        [$rid, $no] = create_receipt($id, $amountNow, $method, mb_substr(post('payment_ref'), 0, 80), $paidOn,
            mb_substr(post('received_by'), 0, 120) ?: $admin['name'], mb_substr(post('note'), 0, 255), (int) $admin['id']);
        flash('success', 'Saved. Payment of UGX ' . number_format($amountNow) . " recorded on receipt $no.");
        redirect('admin/receipt.php?id=' . $rid);
    }
    sync_paid_flag($id);  // e.g. raising the agreed amount on a paid booking means it is no longer fully paid
    flash('success', 'Booking updated.');
    redirect('admin/booking.php?id=' . $id);
}

$pay = booking_payments($b);
$receipts = q('SELECT * FROM receipts WHERE booking_id = ? ORDER BY id DESC', [$id])->fetchAll();
$remaining = $pay['total'] !== null ? max(0, $pay['total'] - $pay['paid']) : null;

admin_top('Booking ' . $b['ref'], 'bookings', '<a href="' . e(url('admin/bookings.php')) . '" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i><span class="lbl">All bookings</span></a>');
$waText = 'Hello ' . $b['full_name'] . ', this is Tosmic Events about your booking ' . $b['ref'] . '.';
?>
<div class="grid grid-main">
  <div class="grid">
  <section class="card">
    <div class="card-head bk-head">
      <div class="bk-who">
        <span class="adm-avatar"><?= e(mb_strtoupper(mb_substr($b['full_name'], 0, 1))) ?></span>
        <div><h2><?= e($b['full_name']) ?></h2><span class="muted"><?= e($b['ref']) ?> · received <?= e(date('d M Y, H:i', strtotime($b['created_at']))) ?></span></div>
      </div>
      <div class="bk-contact">
        <a class="btn btn-sm btn-wa-admin" href="<?= e(whatsapp_link_to($b['phone'], $waText)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
        <a class="btn btn-sm btn-navy" href="<?= e(phone_href($b['phone'])) ?>"><i class="fa-solid fa-phone"></i> Call</a>
        <?php if ($b['email']): ?><a class="icon-btn" href="mailto:<?= e($b['email']) ?>?subject=<?= rawurlencode('Your Tosmic Events booking ' . $b['ref']) ?>" title="Email client" aria-label="Email client"><i class="fa-regular fa-envelope"></i></a><?php endif; ?>
      </div>
    </div>
    <?php
      // only show what the client actually filled in
      $facts = array_filter([
          ['fa-solid fa-box-open', 'Package', ($b['package_name'] ?? 'Custom quotation') . ($b['package_price'] ? ' · UGX ' . number_format((int) $b['package_price']) : '')],
          ['fa-regular fa-calendar', 'Event date', $b['event_date'] ? date('D d M Y', strtotime($b['event_date'])) . ($b['start_time'] ? ', ' . $b['start_time'] : '') : ''],
          ['fa-solid fa-location-dot', 'Venue', (string) $b['venue']],
          ['fa-solid fa-phone', 'Phone', (string) $b['phone']],
          ['fa-solid fa-tag', 'Type of event', (string) $b['event_type']],
          ['fa-solid fa-hourglass-half', 'Duration', $b['duration_hours'] ? rtrim(rtrim((string) $b['duration_hours'], '0'), '.') . ' hours' : ''],
          ['fa-solid fa-users', 'Guests', $b['guests'] ? number_format((int) $b['guests']) : ''],
          ['fa-regular fa-envelope', 'Email', (string) $b['email']],
      ], fn($f) => $f[2] !== '');
    ?>
    <div class="card-body">
      <div class="bk-facts">
        <?php foreach ($facts as [$icon, $label, $value]): ?>
          <div class="bk-fact"><i class="<?= e($icon) ?>"></i><div><small><?= e($label) ?></small><span><?= e($value) ?></span></div></div>
        <?php endforeach; ?>
      </div>
      <?php if ($b['message']): ?>
        <div class="bk-message"><small>Client's message</small><p><?= nl2br(e($b['message'])) ?></p></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" id="payments">
    <div class="card-head">
      <h2><i class="fa-solid fa-receipt" style="color:var(--pink)"></i> Payments &amp; receipts</h2>
      <span class="pill pay-<?= e($pay['state']) ?>"><?= e(PAYMENT_STATES[$pay['state']]) ?></span>
    </div>
    <div class="card-body" style="display:grid;gap:18px">
      <div class="pay-summary">
        <div><small>Agreed total</small><b><?= $pay['total'] !== null ? 'UGX ' . e(number_format($pay['total'])) : 'Not set' ?></b></div>
        <div><small>Paid</small><b style="color:#11733f">UGX <?= e(number_format($pay['paid'])) ?></b></div>
        <div><small>Balance</small><b style="color:<?= $pay['balance'] ? '#c06a00' : '#11733f' ?>"><?= $pay['balance'] !== null ? 'UGX ' . e(number_format($pay['balance'])) : '-' ?></b></div>
      </div>
      <?php if ($pay['total'] && $pay['paid']): ?>
        <div class="pay-bar" title="<?= round(min(100, $pay['paid'] / $pay['total'] * 100)) ?>% paid"><i style="width:<?= min(100, round($pay['paid'] / $pay['total'] * 100)) ?>%"></i></div>
      <?php endif; ?>

      <?php if ($receipts): ?>
        <div class="table-wrap">
          <table class="tbl stack">
            <thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($receipts as $rc): ?>
              <tr<?= $rc['is_void'] ? ' style="opacity:.55"' : '' ?>>
                <td class="no-label"><span class="t-strong"><?= e($rc['receipt_no']) ?></span><?php if ($rc['is_void']): ?> <span class="pill pill-cancelled">Void</span><?php endif; ?><span class="t-sub">by <?= e($rc['received_by']) ?></span></td>
                <td data-label="Amount"><b>UGX <?= e(number_format((int) $rc['amount'])) ?></b></td>
                <td data-label="Method"><?= e($rc['method']) ?><?= $rc['payment_ref'] ? '<span class="t-sub">' . e($rc['payment_ref']) . '</span>' : '' ?></td>
                <td data-label="Date"><?= e(date('d M Y', strtotime($rc['paid_on']))) ?></td>
                <td class="no-label"><div class="actions">
                  <a class="btn btn-light btn-sm" href="<?= e(url('admin/receipt.php?id=' . $rc['id'])) ?>"><i class="fa-solid fa-receipt"></i> Receipt</a>
                  <?php if (!$rc['is_void']): ?>
                    <form method="post" data-confirm="Void receipt <?= e($rc['receipt_no']) ?>? It will no longer count as paid."><?= csrf_field() ?><input type="hidden" name="action" value="void_receipt"><input type="hidden" name="receipt_id" value="<?= (int) $rc['id'] ?>"><button class="icon-btn danger" title="Void receipt" aria-label="Void receipt"><i class="fa-solid fa-ban"></i></button></form>
                  <?php endif; ?>
                </div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted" style="margin:0"><i class="fa-solid fa-circle-info"></i> No payments yet. Enter the amount paid in the <b>Status</b> panel and save; a receipt is created automatically.</p>
      <?php endif; ?>
    </div>
  </section>
  </div>

  <div class="grid bk-side">
    <section class="card">
      <div class="card-head"><h2>Status</h2><span><?= status_pill($b['status'], (bool) $b['is_paid']) ?></span></div>
      <form method="post" class="card-body" style="display:grid;gap:16px" id="statusForm">
        <?= csrf_field() ?>
        <div class="status-checks" aria-label="Booking status">
          <?php
            // New / Contacted / Confirmed / Cancelled: pick one. Paid: an extra tick that goes with Confirmed.
            $statusBoxes = [
                ['new', 'New', 'Just arrived, not answered yet'],
                ['contacted', 'Contacted', 'You have called or messaged them'],
                ['confirmed', 'Confirmed', 'Date agreed, payments allowed'],
                ['paid', 'Paid', 'Client has paid in full (ticks Confirmed too)'],
                ['cancelled', 'Cancelled', 'Not going ahead'],
            ];
          ?>
          <?php foreach ($statusBoxes as [$k, $label, $hint]): ?>
            <label class="status-check st-<?= e($k) ?>" title="<?= e($hint) ?>">
              <?php if ($k === 'paid'): ?>
                <input type="checkbox" name="is_paid" value="1" <?= $b['is_paid'] ? 'checked' : '' ?> data-paid-box>
              <?php else: ?>
                <input type="radio" name="status" value="<?= e($k) ?>" <?= $b['status'] === $k ? 'checked' : '' ?> data-status-box>
              <?php endif; ?>
              <span class="sc-box"><i class="fa-solid fa-check"></i></span>
              <span class="sc-text"><b><?= e($label) ?></b><small><?= e($hint) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>

        <!-- money: agreed, paid now (or full amount), live balance -->
        <div class="money" data-already="<?= (int) $pay['paid'] ?>">
          <?php $canTakeMoney = $remaining === null || $remaining > 0; ?>
          <!-- agreed amount, amount paid now and the Full amount tick on one line -->
          <div class="money-line<?= $canTakeMoney ? '' : ' money-line--single' ?>">
            <label class="fld"><span>Agreed (UGX)</span>
              <input name="quoted_amount" id="mAgreed" inputmode="numeric" autocomplete="off" value="<?= $pay['total'] !== null ? e(number_format($pay['total'])) : '' ?>" placeholder="Price">
            </label>
            <?php if ($canTakeMoney): ?>
              <label class="fld"><span>Paid now (UGX)</span>
                <input name="amount_now" id="mNow" inputmode="numeric" autocomplete="off" placeholder="0">
              </label>
              <label class="full-check" title="Fill in the whole remaining balance"><input type="checkbox" id="mFull"><span class="mc-box"><i class="fa-solid fa-check"></i></span> Full</label>
            <?php endif; ?>
          </div>

          <?php if ($pay['paid'] > 0): ?>
            <div class="money-row"><span>Already paid</span><b class="ok">UGX <?= e(number_format($pay['paid'])) ?></b></div>
          <?php endif; ?>

          <?php if ($canTakeMoney): ?>
            <div class="fld" id="mMethodWrap">
              <span>Paid by</span>
              <div class="method-checks" role="radiogroup" aria-label="Paid by">
                <?php $mi = 0; foreach (PAYMENT_METHODS as $k => $l): ?>
                  <label class="method-check"><input type="radio" name="method" value="<?= e($k) ?>" <?= $mi++ === 0 ? 'checked' : '' ?>><span class="mc-box"><i class="fa-solid fa-check"></i></span><?= e($l) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
            <details class="money-more">
              <summary>More payment details <span class="muted">(date, reference, note)</span></summary>
              <div class="money-more-grid">
                <label class="fld"><span>Date paid</span><input type="date" name="paid_on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></label>
                <label class="fld"><span>Transaction ref</span><input name="payment_ref" maxlength="80" placeholder="Mobile Money / bank ref"></label>
                <label class="fld"><span>Received by</span><input name="received_by" maxlength="120" value="<?= e($admin['name']) ?>"></label>
                <label class="fld"><span>Note on receipt</span><input name="note" maxlength="255" placeholder="e.g. Deposit"></label>
              </div>
            </details>
          <?php else: ?>
            <div class="money-row"><span>Payment</span><b class="ok"><i class="fa-solid fa-circle-check"></i> Fully paid</b></div>
          <?php endif; ?>

          <div class="money-balance" id="mBalanceBox">
            <span>Balance</span>
            <b id="mBalance"><?= $remaining !== null ? 'UGX ' . e(number_format($remaining)) : '-' ?></b>
          </div>
        </div>

        <button class="btn btn-pink btn-block" id="statusSave"><i class="fa-solid fa-floppy-disk"></i> <span>Save changes</span></button>
      </form>
    </section>
    <form method="post" class="bk-delete" data-confirm="Delete booking <?= e($b['ref']) ?>? This cannot be undone.">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button><i class="fa-regular fa-trash-can"></i> Delete this booking (spam or duplicate)</button>
    </form>
  </div>
</div>
<?php admin_bottom(); ?>
