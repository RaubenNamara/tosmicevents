<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/components.php';

$items = q('SELECT * FROM gallery WHERE is_active = 1 ORDER BY sort_order, id DESC')->fetchAll();
$usedCats = array_values(array_unique(array_column($items, 'category')));

$pageTitle = 'Gallery';
$activePage = 'gallery';
$pageDescription = 'Photos of DJ Tosmic at corporate events, weddings, parties and more.';
require __DIR__ . '/includes/header.php';
echo page_hero('Gallery', 'Moments from the decks', 'A look at the events, the crowds and the setups.');
?>

<section class="section">
  <div class="container">
    <?php if ($items): ?>
      <?php if (count($usedCats) > 1): ?>
        <div class="filter-bar reveal" role="toolbar" aria-label="Filter photos">
          <button class="is-active" data-filter="all">All</button>
          <?php foreach (GALLERY_CATEGORIES as $key => $label): if (in_array($key, $usedCats, true)): ?>
            <button data-filter="<?= e($key) ?>"><?= e($label) ?></button>
          <?php endif; endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="gallery-grid">
        <?php foreach ($items as $i => $g): ?>
          <a href="<?= e(img_url($g['image'])) ?>" class="g-item reveal" style="--d:<?= ($i % 4) * 80 ?>ms" data-cat="<?= e($g['category']) ?>" data-lightbox data-caption="<?= e($g['title']) ?>">
            <span class="g-bg" style="background-image:url('<?= e(img_url($g['image'])) ?>')"></span>
            <img src="<?= e(img_url($g['image'])) ?>" alt="<?= e($g['title'] ?: 'Tosmic Events photo') ?>" loading="lazy">
            <span class="g-over"><i class="fa-solid fa-expand"></i><?php if ($g['title']): ?><em><?= e($g['title']) ?></em><?php endif; ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="fa-regular fa-images"></i><p>New photos are on the way. Follow us on Instagram and TikTok @<?= e(setting('instagram', 'tosmicevents')) ?>.</p></div>
    <?php endif; ?>
  </div>
</section>

<?= cta_band() ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
