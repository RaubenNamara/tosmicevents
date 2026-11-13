<?php
/**
 * Stand-alone receipt page. Expects $r (from load_receipt) and $isAdmin (bool).
 * The receipt is drawn like a till roll: monospace font, dashed rules, logo on top.
 */
$siteName = setting('site_name', 'Tosmic Events');
$publicUrl = receipt_public_url($r);
$fmt = fn($n) => 'UGX ' . number_format((int) $n);
$clientPhoneWa = whatsapp_link_to($r['phone'], "Hello {$r['full_name']}, here is your Tosmic Events payment receipt {$r['receipt_no']} for booking {$r['booking_ref']}: $publicUrl");
$fileBase = 'Tosmic-Receipt-' . $r['receipt_no'];
$line = str_repeat('-', 40);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Receipt <?= e($r['receipt_no']) ?> · <?= e($siteName) ?></title>
<link rel="icon" href="<?= e(url('assets/img/seed/logo.jpg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/receipt.css')) ?>">
</head>
<body>
<div class="rc-page">
  <div class="rc-toolbar no-print">
    <?php if ($isAdmin): ?>
      <a class="rc-btn ghost" href="<?= e(url('admin/booking.php?id=' . $r['booking_id'])) ?>"><i class="fa-solid fa-arrow-left"></i> Back to booking</a>
    <?php else: ?>
      <a class="rc-btn ghost" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-house"></i> <?= e($siteName) ?></a>
    <?php endif; ?>
    <div class="rc-actions">
      <button class="rc-btn" data-act="print"><i class="fa-solid fa-print"></i> <span class="l">Print / PDF</span><span class="s">Print</span></button>
      <button class="rc-btn" data-act="png" data-file="<?= e($fileBase) ?>"><i class="fa-regular fa-image"></i> <span>PNG</span></button>
      <button class="rc-btn" data-act="jpg" data-file="<?= e($fileBase) ?>"><i class="fa-regular fa-file-image"></i> <span>JPG</span></button>
      <?php if ($isAdmin && !$r['is_void']): ?>
        <button class="rc-btn" data-act="copy" data-link="<?= e($publicUrl) ?>"><i class="fa-regular fa-copy"></i> <span class="l">Copy link</span><span class="s">Link</span></button>
        <a class="rc-btn wa" href="<?= e($clientPhoneWa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> <span class="l">Send to client</span><span class="s">Send</span></a>
      <?php endif; ?>
    </div>
  </div>

  <div class="rc-stage">
    <article class="rc-paper<?= $r['is_void'] ? ' is-void' : '' ?>" id="receipt">
      <header class="rc-head">
        <img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt="<?= e($siteName) ?>" class="rc-logo" crossorigin="anonymous">
        <h1><?= e(mb_strtoupper($siteName)) ?></h1>
        <p class="rc-sub">DJ Tosmic · Professional DJ services</p>
        <p class="rc-contact"><?= e(setting('phone1')) ?><?= setting('phone2') ? ' / ' . e(setting('phone2')) : '' ?></p>
        <?php if (setting('website')): ?><p class="rc-contact"><?= e(setting('website')) ?></p><?php endif; ?>
      </header>

      <div class="rc-rule"></div>
      <h2 class="rc-title">PAYMENT RECEIPT</h2>
      <div class="rc-rule"></div>

      <div class="rc-row"><span>DATE: <?= e(date('d/m/Y', strtotime($r['paid_on']))) ?></span><span>TIME: <?= e(date('h:i A', strtotime($r['created_at']))) ?></span></div>
      <div class="rc-row"><span>RECEIPT #</span><span><?= e($r['receipt_no']) ?></span></div>
      <div class="rc-row"><span>BOOKING REF</span><span><?= e($r['booking_ref']) ?></span></div>
      <div class="rc-rule"></div>

      <div class="rc-block">
        <b>RECEIVED FROM:</b>
        <span><?= e($r['full_name']) ?></span>
        <span><?= e($r['phone']) ?></span>
      </div>
      <div class="rc-block">
        <b>FOR:</b>
        <span><?= e($r['package_name'] ?? 'Custom DJ package') ?><?= $r['event_type'] ? ' - ' . e($r['event_type']) : '' ?></span>
        <?php if ($r['event_date']): ?><span>Event: <?= e(date('D d M Y', strtotime($r['event_date']))) ?><?= $r['start_time'] ? ', ' . e($r['start_time']) : '' ?></span><?php endif; ?>
        <?php if ($r['venue']): ?><span>Venue: <?= e($r['venue']) ?></span><?php endif; ?>
      </div>
      <div class="rc-rule"></div>

      <?php if ($r['total'] !== null): ?><div class="rc-row"><span>AGREED TOTAL</span><span><?= e($fmt($r['total'])) ?></span></div><?php endif; ?>
      <div class="rc-row rc-strong rc-big"><span>AMOUNT PAID</span><span><?= e($fmt($r['amount'])) ?></span></div>
      <p class="rc-words">(<?= e(ucfirst(number_words((int) $r['amount']))) ?> Uganda shillings only)</p>
      <div class="rc-rule rc-rule--double"></div>
      <div class="rc-row"><span>PAYMENT</span><span><?= e(mb_strtoupper($r['method'])) ?></span></div>
      <?php if ($r['payment_ref']): ?><div class="rc-row"><span>TXN REF</span><span><?= e($r['payment_ref']) ?></span></div><?php endif; ?>
      <div class="rc-row"><span>PAID TO DATE</span><span><?= e($fmt($r['paid_to_date'])) ?></span></div>
      <?php if ($r['balance'] !== null): ?><div class="rc-row rc-strong"><span>BALANCE</span><span><?= e($fmt($r['balance'])) ?></span></div><?php endif; ?>
      <?php if ($r['note']): ?><p class="rc-note">NOTE: <?= e($r['note']) ?></p><?php endif; ?>

      <div class="rc-stamp-wrap">
        <?php if ($r['is_void']): ?>
          <span class="rc-stamp void">VOID</span>
        <?php elseif ($r['full']): ?>
          <span class="rc-stamp paid">PAID IN FULL</span>
        <?php else: ?>
          <span class="rc-stamp part">PART PAYMENT</span>
        <?php endif; ?>
      </div>

      <div class="rc-dash"><?= $line ?></div>
      <p class="rc-center"><b>RECEIVED BY:</b> <?= e($r['received_by']) ?></p>
      <p class="rc-sign">Signature: <span></span></p>
      <div class="rc-dash"><?= $line ?></div>

      <div class="rc-qr-wrap">
        <div class="rc-qr" data-qr="<?= e(receipt_verify_url($r)) ?>" role="img" aria-label="QR code to verify receipt <?= e($r['receipt_no']) ?>">
          <canvas width="300" height="300"></canvas>
          <img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt="" class="rc-qr-logo" crossorigin="anonymous">
        </div>
      </div>

      <p class="rc-center rc-thanks">THANK YOU FOR CHOOSING <?= e(mb_strtoupper($siteName)) ?>!</p>
      <p class="rc-center rc-small">Sound equipment excluded from packages.<br>Keep this receipt for your records.</p>
      <?php if ($r['is_void']): ?><p class="rc-center rc-small">Voided: <?= e($r['void_reason'] ?: 'cancelled by Tosmic Events') ?></p><?php endif; ?>
    </article>
  </div>
  <p class="rc-toast no-print" id="rcToast" role="status" aria-live="polite"></p>
</div>
<script src="<?= e(asset('vendor/qrcode.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/html2canvas.min.js')) ?>"></script>
<script src="<?= e(asset('js/receipt.js')) ?>"></script>
</body>
</html>
