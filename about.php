<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/components.php';

$packages = q('SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$eventTypes = [];
foreach ($packages as $p) $eventTypes = array_merge($eventTypes, lines($p['events']));

$pageTitle = 'About';
$activePage = 'about';
$pageDescription = 'Meet DJ Tosmic and Tosmic Events: professional DJ services for corporate, social and other events.';
require __DIR__ . '/includes/header.php';
echo page_hero(setting('about_title', 'Meet DJ Tosmic'), 'About Tosmic Events', 'The DJ, the booth, the lights and the smoke, brought together for your event.');
?>

<section class="section">
  <div class="container about-grid">
    <div class="about-visual reveal">
      <div class="about-frame"><img src="<?= e(url('assets/img/seed/dj.jpg')) ?>" alt="DJ Tosmic"></div>
      <div class="about-tag"><img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt=""><span>Follow us<br><strong>@<?= e(setting('instagram', 'tosmicevents')) ?></strong></span></div>
    </div>
    <div class="about-story reveal" style="--d:120ms">
      <span class="eyebrow">Our story</span>
      <h2 class="section-title">Music that moves every room</h2>
      <?php foreach (lines(setting('about_text')) as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
      <div class="slide-actions" style="margin-top:28px">
        <a href="<?= e(url('booking.php')) ?>" class="btn btn-pink">Book DJ Tosmic <i class="fa-solid fa-arrow-right"></i></a>
        <a href="<?= e(url('packages.php')) ?>" class="btn btn-outline">View packages</a>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Why book us</span>
      <h2 class="section-title">What you can count on</h2>
    </div>
    <div class="values">
      <div class="value reveal"><i class="fa-solid fa-headphones"></i><h3>Reading the crowd</h3><p>Sets are planned around your guests and your programme, and adjusted live so the floor stays full.</p></div>
      <div class="value reveal" style="--d:120ms"><i class="fa-solid fa-lightbulb"></i><h3>Full DJ setup</h3><p>Professional DJ equipment, a DJ booth, dynamic basic lighting and a smoke effect come with every package.</p></div>
      <div class="value reveal" style="--d:240ms"><i class="fa-solid fa-file-invoice"></i><h3>Clear pricing</h3><p>Published package rates, adjusted only for event distance and duration, and custom quotes for bigger productions.</p></div>
    </div>
  </div>
</section>

<?php if ($eventTypes): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Events we play</span>
      <h2 class="section-title">From boardrooms to beach bashes</h2>
    </div>
    <div class="event-cloud reveal">
      <?php foreach ($eventTypes as $ev): ?><span><i class="fa-solid fa-music"></i><?= e($ev) ?></span><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?= cta_band() ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
