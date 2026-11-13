<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/components.php';

$packages = q('SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();

$pageTitle = 'Booking Packages';
$activePage = 'packages';
$pageDescription = 'DJ Tosmic booking packages for corporate events, social gatherings and other events, with prices.';
require __DIR__ . '/includes/header.php';
echo page_hero('Booking Packages', 'DJ Tosmic', 'Three packages, one standard: a full DJ setup with lighting and smoke, priced up front.');
?>

<section class="section packages-section">
  <div class="container">
    <?php if ($packages): ?>
      <div class="pkg-grid">
        <?php foreach ($packages as $i => $p) echo package_card($p, $i * 120); ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-box-open"></i><p>Packages are being updated. Please contact us for a quotation.</p></div>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">In every package</span>
      <h2 class="section-title">What's included</h2>
    </div>
    <div class="included-band">
      <?php foreach (includes_icons() as $i => $inc): ?>
        <div class="inc-item reveal" style="--d:<?= $i * 100 ?>ms"><i class="<?= e($inc['icon']) ?>"></i><h3><?= e($inc['label']) ?></h3></div>
      <?php endforeach; ?>
    </div>
    <p class="inc-excluded reveal"><i class="fa-solid fa-volume-xmark"></i> Sound equipment is not included in the packages.</p>
    <p class="pkg-note reveal"><i class="fa-solid fa-circle-info"></i> <?= e(setting('package_note')) ?></p>
    <div style="text-align:center;margin-top:30px" class="reveal">
      <a href="<?= e(url('booking.php?package=custom')) ?>" class="btn btn-gold btn-lg"><i class="fa-solid fa-file-signature"></i> Request a custom quotation</a>
    </div>
  </div>
</section>

<?= cta_band() ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
