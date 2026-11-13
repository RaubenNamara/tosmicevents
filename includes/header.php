<?php
/** @var string $pageTitle @var string $activePage @var string|null $pageDescription */
$siteName = setting('site_name', 'Tosmic Events');
$pageDescription = $pageDescription ?? setting('tagline');
$fullTitle = ($pageTitle ?? '') !== '' ? $pageTitle . ' | ' . $siteName : $siteName . ' · DJ Tosmic Booking';
$nav = [
    'home' => ['Home', 'index.php', 'fa-solid fa-house'],
    'about' => ['About', 'about.php', 'fa-solid fa-headphones'],
    'packages' => ['Packages', 'packages.php', 'fa-solid fa-box-open'],
    'gallery' => ['Gallery', 'gallery.php', 'fa-regular fa-images'],
    'reviews' => ['Reviews', 'feedback.php', 'fa-regular fa-star'],
    'booking' => ['Contact', 'booking.php', 'fa-regular fa-envelope'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="theme-color" content="#120F28">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:image" content="<?= e(url('assets/img/seed/dj.jpg')) ?>">
<link rel="icon" href="<?= e(url('assets/img/seed/logo.jpg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script nonce="<?= e(csp_nonce()) ?>">document.documentElement.classList.add('js');</script>
</head>
<body class="page-<?= e($activePage ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="siteHeader">
  <div class="container nav-wrap">
    <a href="<?= e(url('index.php')) ?>" class="brand" aria-label="<?= e($siteName) ?> home">
      <img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt="" width="46" height="46">
      <span class="brand-text"><strong>TOSMIC</strong><small>EVENTS</small></span>
    </a>

    <nav class="main-nav" id="mainNav" aria-label="Main">
      <ul>
        <?php foreach ($nav as $key => [$label, $href, $icon]): ?>
          <li><a href="<?= e(url($href)) ?>" class="<?= ($activePage ?? '') === $key ? 'active' : '' ?>"><i class="nav-ico <?= e($icon) ?>"></i><span><?= e($label) ?></span><i class="nav-go fa-solid fa-chevron-right"></i></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="nav-mobile-extra">
        <a href="<?= e(url('booking.php')) ?>" class="btn btn-pink btn-block nav-book"><i class="fa-regular fa-calendar-check"></i> Book DJ Tosmic</a>
        <a href="<?= e(phone_href(setting('phone1'))) ?>" class="nav-contact"><i class="fa-solid fa-phone"></i> <?= e(setting('phone1')) ?></a>
        <?php if (setting('phone2')): ?><a href="<?= e(phone_href(setting('phone2'))) ?>" class="nav-contact"><i class="fa-solid fa-phone"></i> <?= e(setting('phone2')) ?></a><?php endif; ?>
        <div class="socials">
          <?php if (setting('instagram')): ?><a href="https://instagram.com/<?= e(setting('instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><?php endif; ?>
          <?php if (setting('tiktok')): ?><a href="https://www.tiktok.com/@<?= e(setting('tiktok')) ?>" target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a><?php endif; ?>
          <?php if (setting('whatsapp')): ?><a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?>
        </div>
      </div>
    </nav>

    <div class="nav-actions">
      <div class="nav-social">
        <?php if (setting('tiktok')): ?><a href="https://www.tiktok.com/@<?= e(setting('tiktok')) ?>" target="_blank" rel="noopener" aria-label="TikTok" title="TikTok @<?= e(setting('tiktok')) ?>" class="ns-tiktok"><i class="fa-brands fa-tiktok"></i></a><?php endif; ?>
        <?php if (setting('instagram')): ?><a href="https://instagram.com/<?= e(setting('instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram @<?= e(setting('instagram')) ?>" class="ns-ig"><i class="fa-brands fa-instagram"></i></a><?php endif; ?>
        <?php if (setting('whatsapp')): ?><a href="<?= e(whatsapp_link('Hello Tosmic Events, I would like to book DJ Tosmic.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp" class="ns-wa"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?>
      </div>
      <a href="<?= e(url('booking.php')) ?>" class="btn btn-pink btn-sm nav-cta">Book Now</a>
      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mainNav">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main id="main">
