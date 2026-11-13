<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/components.php';

$slides = q('SELECT * FROM slides WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$packages = q('SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
// the 4 most recently added photos, one row on every screen; the full set is on gallery.php
$gallery = q('SELECT * FROM gallery WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 4')->fetchAll();
$testimonials = q('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order, created_at DESC LIMIT 3')->fetchAll();
$reviewSum = review_summary();
$minPrice = $packages ? min(array_column($packages, 'price')) : 0;

$eventTypes = [];
foreach ($packages as $p) $eventTypes = array_merge($eventTypes, lines($p['events']));

$pageTitle = '';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>

<!-- ================= HERO SLIDESHOW ================= -->
<section class="hero" id="hero" aria-roledescription="carousel" aria-label="Highlights">
  <div class="hero-bg" aria-hidden="true">
    <?php if ($heroVideos = hero_videos()): ?>
      <div class="hero-videos" id="heroVideos" style="background-image:url('<?= e(url('assets/video/hero-crowd-poster.jpg')) ?>')">
        <?php foreach ($heroVideos as $vi => $v): ?>
          <video class="hero-video<?= $vi === 0 ? ' is-on' : '' ?>" muted playsinline disablepictureinpicture <?= $vi === 0 ? 'autoplay preload="auto"' : 'preload="metadata"' ?>>
            <source src="<?= e($v['src']) ?>" type="<?= e($v['type']) ?>">
          </video>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="beam beam-1"></div><div class="beam beam-2"></div><div class="beam beam-3"></div>
    <?php endif; ?>
    <div class="hero-shade"></div>
    <div class="hero-glow"></div>
    <div class="hero-dots"></div>
  </div>
  <div class="hero-wipe" aria-hidden="true"><span></span><span></span></div>

  <div class="slides">
    <?php foreach ($slides as $i => $s): ?>
      <div class="slide slide--<?= e($s['layout']) ?><?= $i === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($slides) ?>" <?= $i === 0 ? '' : 'aria-hidden="true"' ?>>
        <?php if ($s['layout'] === 'full'): ?>
          <div class="slide-media-full" style="background-image:url('<?= e(img_url($s['image'])) ?>')"></div>
        <?php endif; ?>
        <div class="container slide-inner">
          <div class="slide-text">
            <?php if ($s['eyebrow']): ?><span class="eyebrow anim" style="--a:1"><?= e($s['eyebrow']) ?></span><?php endif; ?>
            <h<?= $i === 0 ? '1' : '2' ?> class="slide-title anim" style="--a:2"><?= e($s['title']) ?></h<?= $i === 0 ? '1' : '2' ?>>
            <?php if ($s['subtitle']): ?><p class="slide-sub anim" style="--a:3"><?= e($s['subtitle']) ?></p><?php endif; ?>
            <div class="slide-actions anim" style="--a:4">
              <?php if ($s['button_text']): ?>
                <a href="<?= e(preg_match('#^(https?:)?//#', (string) $s['button_link']) ? $s['button_link'] : url((string) $s['button_link'])) ?>" class="btn btn-pink btn-lg"><?= e($s['button_text']) ?> <i class="fa-solid fa-arrow-right"></i></a>
              <?php endif; ?>
              <a href="<?= e(whatsapp_link('Hello Tosmic Events, I would like to book DJ Tosmic.')) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-lg"><i class="fa-brands fa-whatsapp"></i> WhatsApp us</a>
            </div>
          </div>
          <?php if ($s['layout'] === 'split'): ?>
            <div class="slide-visual anim" style="--a:2">
              <div class="orbit orbit-1"></div>
              <div class="orbit orbit-2"></div>
              <div class="photo-ring">
                <img src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
              </div>
              <?php if ($minPrice): ?>
                <div class="float-chip chip-price"><small>Packages from</small><strong>UGX <?= e(number_format($minPrice)) ?></strong></div>
              <?php endif; ?>
              <div class="float-chip chip-light"><i class="fa-solid fa-lightbulb"></i> Lighting &amp; smoke included</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (count($slides) > 1): ?>
  <div class="container hero-controls">
    <div class="hero-counter"><span id="slideNow">01</span><i></i><span><?= str_pad((string) count($slides), 2, '0', STR_PAD_LEFT) ?></span></div>
    <div class="hero-dots-nav" role="tablist" aria-label="Choose slide">
      <?php foreach ($slides as $i => $s): ?>
        <button role="tab" class="dot<?= $i === 0 ? ' is-active' : '' ?>" aria-label="Slide <?= $i + 1 ?>: <?= e($s['title']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><span></span></button>
      <?php endforeach; ?>
    </div>
    <div class="hero-arrows">
      <button class="arrow" id="prevSlide" aria-label="Previous slide"><i class="fa-solid fa-arrow-left"></i></button>
      <button class="arrow" id="nextSlide" aria-label="Next slide"><i class="fa-solid fa-arrow-right"></i></button>
    </div>
  </div>
  <?php endif; ?>

  <div class="eq-bars" aria-hidden="true"><?php for ($i = 0; $i < 64; $i++): ?><span style="--i:<?= $i ?>"></span><?php endfor; ?></div>
</section>

<!-- ================= TICKER ================= -->
<?php if ($eventTypes): ?>
<?php
  // each event gets its own glass card; one row glides left and every card floats on its own beat
  $evIcons = ['fa-solid fa-music', 'fa-solid fa-champagne-glasses', 'fa-solid fa-star', 'fa-solid fa-headphones', 'fa-solid fa-microphone-lines', 'fa-solid fa-compact-disc', 'fa-solid fa-bolt', 'fa-solid fa-heart'];
  $rows = [$eventTypes]; // one row with every event
?>
<div class="event-marquee" aria-label="Events we play">
  <?php foreach ($rows as $ri => $row): ?>
    <div class="em-row em-row--<?= $ri === 0 ? 'left' : 'right' ?>">
      <div class="em-track">
        <?php for ($copy = 0; $copy < 2; $copy++): foreach ($row as $ci => $ev): $n = $ci + $ri * 3; ?>
          <span class="em-card" style="--bob:<?= 3.2 + ($n % 5) * .45 ?>s;--delay:-<?= ($n * .37) % 3 ?>s"<?= $copy ? ' aria-hidden="true"' : '' ?>>
            <i class="<?= e($evIcons[$n % count($evIcons)]) ?>"></i><?= e($ev) ?>
          </span>
        <?php endforeach; endfor; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================= ABOUT TEASER ================= -->
<section class="section about-teaser">
  <div class="container about-grid">
    <div class="about-visual reveal">
      <div class="about-frame">
        <img src="<?= e(url('assets/img/seed/dj.jpg')) ?>" alt="DJ Tosmic" loading="lazy">
      </div>
      <div class="about-tag"><img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt=""><span>The home of<br><strong>DJ Tosmic</strong></span></div>
    </div>
    <div class="about-copy reveal" style="--d:120ms">
      <span class="eyebrow">Who we are</span>
      <h2 class="section-title"><?= e(setting('about_title', 'Meet DJ Tosmic')) ?></h2>
      <?php foreach (array_slice(lines(setting('about_text')), 0, 2) as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
      <div class="included-mini">
        <?php foreach (includes_icons() as $inc): ?>
          <div><i class="<?= e($inc['icon']) ?>"></i><span><?= e($inc['label']) ?></span></div>
        <?php endforeach; ?>
      </div>
      <a href="<?= e(url('about.php')) ?>" class="btn btn-outline">More about us <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<!-- ================= PACKAGES ================= -->
<section class="section packages-section" id="packages">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Booking packages</span>
      <h2 class="section-title">Pick the package for your event</h2>
      <p>Every package comes with professional DJ equipment, a DJ booth, dynamic basic lighting and a smoke effect.</p>
    </div>
    <div class="pkg-grid">
      <?php foreach ($packages as $i => $p) echo package_card($p, $i * 120); ?>
    </div>
    <p class="pkg-note reveal"><i class="fa-solid fa-circle-info"></i> <?= e(setting('package_note')) ?></p>
  </div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="section steps-section">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Simple booking</span>
      <h2 class="section-title">From enquiry to dance floor</h2>
    </div>
    <div class="steps">
      <div class="step reveal" style="--d:0ms"><span class="step-no">01</span><i class="fa-solid fa-list-check"></i><h3>Choose a package</h3><p>Corporate, social or other events, or ask for a custom quote for bigger setups.</p></div>
      <div class="step reveal" style="--d:120ms"><span class="step-no">02</span><i class="fa-regular fa-paper-plane"></i><h3>Send your details</h3><p>Share the date, venue and duration through the booking form, a call or WhatsApp.</p></div>
      <div class="step reveal" style="--d:240ms"><span class="step-no">03</span><i class="fa-solid fa-handshake"></i><h3>We confirm</h3><p>We check availability, agree the final rate for distance and duration, and lock in your date.</p></div>
      <div class="step reveal" style="--d:360ms"><span class="step-no">04</span><i class="fa-solid fa-music"></i><h3>Party time</h3><p>DJ Tosmic arrives, sets up the booth, lights and smoke, and keeps the floor full.</p></div>
    </div>
  </div>
</section>

<!-- ================= GALLERY PREVIEW ================= -->
<?php if ($gallery): ?>
<section class="section gallery-preview">
  <div class="container">
    <div class="section-head section-head--row reveal">
      <div>
        <span class="eyebrow">Gallery</span>
        <h2 class="section-title">Moments from the decks</h2>
      </div>
      <a href="<?= e(url('gallery.php')) ?>" class="btn btn-outline">View full gallery <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="gallery-grid">
      <?php foreach ($gallery as $i => $g): ?>
        <a href="<?= e(img_url($g['image'])) ?>" class="g-item reveal" style="--d:<?= ($i % 4) * 80 ?>ms" data-lightbox data-caption="<?= e($g['title']) ?>">
          <span class="g-bg" style="background-image:url('<?= e(img_url($g['image'])) ?>')"></span>
          <img src="<?= e(img_url($g['image'])) ?>" alt="<?= e($g['title'] ?: 'Tosmic Events photo') ?>" loading="lazy">
          <span class="g-over"><i class="fa-solid fa-expand"></i><?php if ($g['title']): ?><em><?= e($g['title']) ?></em><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= TESTIMONIALS ================= -->
<section class="section testimonials-section">
  <div class="container">
    <div class="section-head section-head--row reveal">
      <div>
        <span class="eyebrow">Client feedback</span>
        <h2 class="section-title">What our clients say</h2>
        <?php if ($reviewSum['count']): ?>
          <p class="review-line"><span class="stars"><?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= round($reviewSum['avg']) ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?></span> <b><?= e(number_format($reviewSum['avg'], 1)) ?></b> from <?= $reviewSum['count'] ?> review<?= $reviewSum['count'] > 1 ? 's' : '' ?></p>
        <?php endif; ?>
      </div>
      <div class="cta-actions">
        <?php if ($reviewSum['count'] > count($testimonials)): ?><a href="<?= e(url('feedback.php')) ?>" class="btn btn-outline">All reviews <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
        <a href="<?= e(url('feedback.php#share')) ?>" class="btn btn-pink"><i class="fa-regular fa-pen-to-square"></i> Share your experience</a>
      </div>
    </div>
    <?php if ($testimonials): ?>
      <div class="testi-track">
        <?php foreach ($testimonials as $i => $t) echo review_card($t, $i * 100); ?>
      </div>
    <?php else: ?>
      <div class="empty-state reveal"><i class="fa-regular fa-comments"></i><p>Had DJ Tosmic at your event? Be the first to tell others how it went.</p></div>
    <?php endif; ?>
  </div>
</section>

<?= cta_band() ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
