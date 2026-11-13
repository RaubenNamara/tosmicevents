<?php
/** Admin page chrome. Pages call admin_top($title, $activeKey) and admin_bottom(). */

function admin_nav(): array
{
    $newCount = (int) q("SELECT COUNT(*) FROM bookings WHERE status = 'new'")->fetchColumn();
    $pendingReviews = (int) q('SELECT COUNT(*) FROM testimonials WHERE reviewed = 0')->fetchColumn();
    return [
        'Overview' => [
            'dashboard' => ['Dashboard', 'fa-solid fa-gauge-high', 'index.php', 0],
            'bookings' => ['Bookings', 'fa-regular fa-calendar-check', 'bookings.php', $newCount],
            'scan' => ['Scan receipt', 'fa-solid fa-qrcode', 'scan.php', 0],
        ],
        'Website content' => [
            'slides' => ['Home slideshow', 'fa-solid fa-images', 'slides.php', 0],
            'packages' => ['Packages', 'fa-solid fa-box-open', 'packages.php', 0],
            'gallery' => ['Gallery', 'fa-regular fa-image', 'gallery.php', 0],
            'testimonials' => ['Reviews', 'fa-regular fa-comment-dots', 'testimonials.php', $pendingReviews],
        ],
        'System' => [
            'settings' => ['Site settings', 'fa-solid fa-sliders', 'settings.php', 0],
            'account' => ['My account', 'fa-regular fa-user', 'account.php', 0],
        ],
    ];
}

function admin_top(string $title, string $active, string $actionsHtml = ''): void
{
    $admin = current_admin();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Tosmic Admin</title>
<link rel="icon" href="<?= e(url('assets/img/seed/logo.jpg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<div class="adm">
  <aside class="adm-side" id="admSide">
    <a href="<?= e(url('admin/index.php')) ?>" class="adm-brand">
      <img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt="">
      <span><strong>TOSMIC</strong><small>Admin panel</small></span>
    </a>
    <nav class="adm-nav">
      <?php foreach (admin_nav() as $group => $items): ?>
        <p class="adm-nav-label"><?= e($group) ?></p>
        <?php foreach ($items as $key => [$label, $icon, $href, $badge]): ?>
          <a href="<?= e(url('admin/' . $href)) ?>" class="<?= $active === $key ? 'active' : '' ?>">
            <i class="<?= e($icon) ?>"></i><span><?= e($label) ?></span>
            <?php if ($badge): ?><em class="badge"><?= (int) $badge ?></em><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <a href="<?= e(url('index.php')) ?>" target="_blank" class="adm-view-site"><i class="fa-solid fa-arrow-up-right-from-square"></i> View website</a>
  </aside>
  <div class="adm-backdrop" id="admBackdrop"></div>

  <div class="adm-main">
    <header class="adm-top">
      <button class="adm-burger" id="admBurger" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
      <h1><?= e($title) ?></h1>
      <div class="adm-top-right">
        <?= $actionsHtml ?>
        <div class="adm-user">
          <span class="adm-avatar"><?= e(mb_strtoupper(mb_substr($admin['name'] ?? 'A', 0, 1))) ?></span>
          <span class="adm-user-name"><?= e($admin['name'] ?? '') ?></span>
          <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button class="icon-btn" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-right-from-bracket"></i></button></form>
        </div>
      </div>
    </header>
    <main class="adm-content">
      <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><i class="fa-solid <?= $f['type'] === 'success' ? 'fa-circle-check' : ($f['type'] === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-info') ?>"></i><span><?= e($f['msg']) ?></span><button class="flash-x" aria-label="Dismiss">&times;</button></div>
      <?php endforeach; ?>
<?php
}

function admin_bottom(): void
{
    ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
<?php
}

function status_pill(string $status, bool $paid = false): string
{
    $html = '<span class="pill pill-' . e($status) . '">' . e(BOOKING_STATUSES[$status] ?? $status) . '</span>';
    if ($paid) $html .= ' <span class="pill pill-paid"><i class="fa-solid fa-check"></i> Paid</span>';
    return $html;
}

function img_input_preview(?string $current, string $name = 'image', bool $required = false): string
{
    ob_start(); ?>
    <div class="img-input">
      <div class="img-preview" data-preview><?php if ($current): ?><img src="<?= e(img_url($current)) ?>" alt=""><?php else: ?><i class="fa-regular fa-image"></i><?php endif; ?></div>
      <label class="btn btn-light"><i class="fa-solid fa-upload"></i> <?= $current ? 'Replace image' : 'Choose image' ?>
        <input type="file" name="<?= e($name) ?>" accept="image/*" hidden <?= $required ? 'required' : '' ?> data-preview-input>
      </label>
      <small class="hint">Any photo up to 10 MB. Saved as WebP automatically.</small>
    </div>
    <?php return ob_get_clean();
}
