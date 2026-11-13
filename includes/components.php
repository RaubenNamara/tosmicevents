<?php
/** Reusable public-site pieces. */

function package_card(array $p, int $delay = 0): string
{
    ob_start(); ?>
    <article class="pkg-card reveal<?= $p['is_featured'] ? ' is-featured' : '' ?>" id="package-<?= (int) $p['id'] ?>" style="--d:<?= $delay ?>ms">
      <?php if ($p['is_featured']): ?><span class="pkg-badge"><i class="fa-solid fa-star"></i> Featured</span><?php endif; ?>
      <div class="pkg-photo">
        <img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
      </div>
      <div class="pkg-body">
        <h3><?= e($p['name']) ?></h3>
        <?php if ($p['tagline']): ?><p class="pkg-tagline"><?= e($p['tagline']) ?></p><?php endif; ?>
        <ul class="pkg-list">
          <?php foreach (lines($p['events']) as $ev): ?><li><?= e($ev) ?></li><?php endforeach; ?>
        </ul>
        <div class="pkg-price">
          <small>UGX</small>
          <strong><?= e(money($p['price'])) ?></strong>
        </div>
        <a href="<?= e(url('booking.php?package=' . (int) $p['id'])) ?>" class="btn btn-navy btn-block">Book this package <i class="fa-solid fa-arrow-right"></i></a>
      </div>
    </article>
    <?php return ob_get_clean();
}

function page_hero(string $title, string $eyebrow, string $text = ''): string
{
    ob_start(); ?>
    <section class="page-hero">
      <div class="hero-glow" aria-hidden="true"></div>
      <div class="container page-hero-inner">
        <span class="eyebrow"><?= e($eyebrow) ?></span>
        <h1><?= e($title) ?></h1>
        <?php if ($text): ?><p><?= e($text) ?></p><?php endif; ?>
        <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a><i class="fa-solid fa-chevron-right"></i><span><?= e($title) ?></span></nav>
      </div>
      <div class="eq-bars" aria-hidden="true"><?php for ($i = 0; $i < 48; $i++): ?><span style="--i:<?= $i ?>"></span><?php endfor; ?></div>
    </section>
    <?php return ob_get_clean();
}

function includes_icons(): array
{
    $icons = ['fa-solid fa-sliders', 'fa-solid fa-table-cells-large', 'fa-solid fa-lightbulb', 'fa-solid fa-smog', 'fa-solid fa-star', 'fa-solid fa-bolt'];
    $out = [];
    foreach (lines(setting('package_includes')) as $i => $label) {
        $out[] = ['icon' => $icons[$i % count($icons)], 'label' => $label];
    }
    return $out;
}

function review_card(array $t, int $delay = 0): string
{
    ob_start(); ?>
    <figure class="testi reveal" style="--d:<?= $delay ?>ms">
      <div class="testi-top">
        <div class="stars" aria-label="<?= (int) $t['rating'] ?> out of 5"><?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= $t['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?></div>
        <?php if (!empty($t['booking_id'])): ?><span class="verified" title="This client booked Tosmic Events"><i class="fa-solid fa-circle-check"></i> Verified booking</span><?php endif; ?>
      </div>
      <blockquote><?= nl2br(e($t['message'])) ?></blockquote>
      <figcaption>
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span>
        <span><strong><?= e($t['name']) ?></strong><small><?= e(trim(($t['role'] ?? '') . ($t['role'] ? ' · ' : '') . date('M Y', strtotime($t['created_at'])), ' ·')) ?></small></span>
      </figcaption>
    </figure>
    <?php return ob_get_clean();
}

/** Published reviews and their average, for the home page and the Reviews page. */
function review_summary(): array
{
    $r = q('SELECT COUNT(*) n, AVG(rating) avg FROM testimonials WHERE is_active = 1')->fetch();
    return ['count' => (int) $r['n'], 'avg' => $r['n'] ? round((float) $r['avg'], 1) : 0];
}

function cta_band(): string
{
    ob_start(); ?>
    <section class="cta-band">
      <div class="container cta-inner reveal">
        <div>
          <span class="eyebrow eyebrow--dark">Dates fill up fast</span>
          <h2>Ready to book DJ Tosmic?</h2>
          <p>Tell us about your event and we will get back to you with availability and a quotation.</p>
        </div>
        <div class="cta-actions">
          <a href="<?= e(url('booking.php')) ?>" class="btn btn-navy btn-lg"><i class="fa-regular fa-calendar-check"></i> Book your date</a>
          <a href="<?= e(phone_href(setting('phone1'))) ?>" class="btn btn-outline-dark btn-lg"><i class="fa-solid fa-phone"></i> <?= e(setting('phone1')) ?></a>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}
