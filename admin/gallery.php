<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'upload') {
        $cat = isset(GALLERY_CATEGORIES[post('category')]) ? post('category') : 'other';
        $title = mb_substr(post('title'), 0, 160) ?: null;
        $ok = 0; $fail = [];
        $next = (int) q('SELECT COALESCE(MAX(sort_order), 0) FROM gallery')->fetchColumn();
        foreach (files_list('images') as $f) {
            if ($f['error'] === UPLOAD_ERR_NO_FILE) continue;
            try {
                $path = save_upload($f, 'gallery', 1800);
                q('INSERT INTO gallery (title, category, image, sort_order) VALUES (?,?,?,?)', [$title, $cat, $path, ++$next]);
                $ok++;
            } catch (RuntimeException $ex) {
                $fail[] = $f['name'] . ': ' . $ex->getMessage();
            }
        }
        if ($ok) flash('success', $ok . ' photo' . ($ok > 1 ? 's' : '') . ' added to the gallery.');
        if ($fail) flash('error', implode(' ', $fail));
        if (!$ok && !$fail) flash('error', 'Choose at least one photo to upload. If you chose many large photos, try fewer at a time.');
        redirect('admin/gallery.php');
    }
    if ($action === 'update') {
        $cat = isset(GALLERY_CATEGORIES[post('category')]) ? post('category') : 'other';
        q('UPDATE gallery SET title = ?, category = ?, sort_order = ? WHERE id = ?', [mb_substr(post('title'), 0, 160) ?: null, $cat, (int) post('sort_order'), $id]);
        flash('success', 'Photo updated.');
        redirect('admin/gallery.php' . (isset($_GET['cat']) ? '?cat=' . urlencode((string) $_GET['cat']) : ''));
    }
    if ($action === 'toggle') {
        q('UPDATE gallery SET is_active = 1 - is_active WHERE id = ?', [$id]);
        redirect('admin/gallery.php');
    }
    if ($action === 'delete') {
        $img = q('SELECT image FROM gallery WHERE id = ?', [$id])->fetchColumn();
        q('DELETE FROM gallery WHERE id = ?', [$id]);
        delete_upload($img ?: null);
        flash('success', 'Photo deleted.');
        redirect('admin/gallery.php');
    }
}

$cat = (string) ($_GET['cat'] ?? '');
$items = $cat !== '' && isset(GALLERY_CATEGORIES[$cat])
    ? q('SELECT * FROM gallery WHERE category = ? ORDER BY sort_order, id DESC', [$cat])->fetchAll()
    : q('SELECT * FROM gallery ORDER BY sort_order, id DESC')->fetchAll();
$counts = [];
foreach (q('SELECT category, COUNT(*) c FROM gallery GROUP BY category')->fetchAll() as $r) $counts[$r['category']] = (int) $r['c'];

admin_top('Gallery', 'gallery');
?>
<section class="card" style="margin-bottom:22px">
  <div class="card-head"><h2>Add photos</h2><span class="muted">Several at once is fine</span></div>
  <form method="post" enctype="multipart/form-data" class="card-body">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <div class="grid grid-main">
      <label class="dropzone">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <strong>Drop photos here or click to choose</strong>
        <span class="muted">Any photo (JPG, PNG, GIF, WebP...) up to 10 MB each. Saved as WebP and resized automatically.</span>
        <span class="drop-count"></span>
        <input type="file" name="images[]" accept="image/*" multiple hidden>
      </label>
      <div style="display:grid;gap:14px;align-content:start">
        <label class="fld"><span>Category</span>
          <select name="category"><?php foreach (GALLERY_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
        </label>
        <label class="fld"><span>Caption (optional)</span><input name="title" maxlength="160" placeholder="e.g. Wedding reception, Kampala"></label>
        <button class="btn btn-pink btn-block"><i class="fa-solid fa-upload"></i> Upload photos</button>
      </div>
    </div>
  </form>
</section>

<section class="card">
  <div class="card-head">
    <nav class="tabs">
      <a href="?" class="<?= $cat === '' ? 'active' : '' ?>">All<em><?= array_sum($counts) ?></em></a>
      <?php foreach (GALLERY_CATEGORIES as $k => $l): ?><a href="?cat=<?= e($k) ?>" class="<?= $cat === $k ? 'active' : '' ?>"><?= e($l) ?><em><?= $counts[$k] ?? 0 ?></em></a><?php endforeach; ?>
    </nav>
  </div>
  <div class="card-body">
    <?php if ($items): ?>
      <div class="g-admin">
        <?php foreach ($items as $g): ?>
          <div class="g-tile<?= $g['is_active'] ? '' : ' is-off' ?>">
            <a href="<?= e(img_url($g['image'])) ?>" target="_blank"><img src="<?= e(img_url($g['image'])) ?>" alt="" loading="lazy"></a>
            <form method="post" class="g-edit" action="?<?= $cat ? 'cat=' . e($cat) : '' ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <input name="title" value="<?= e($g['title'] ?? '') ?>" placeholder="Caption" aria-label="Caption" maxlength="160">
              <div class="g-tile-actions">
                <select name="category" class="grow" aria-label="Category"><?php foreach (GALLERY_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $g['category'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                <input type="number" name="sort_order" value="<?= (int) $g['sort_order'] ?>" style="width:64px" aria-label="Position" title="Position">
              </div>
              <div class="g-tile-actions">
                <button class="btn btn-light btn-sm grow"><i class="fa-solid fa-check"></i> Save</button>
                <button class="icon-btn" formaction="?" name="action" value="toggle" title="<?= $g['is_active'] ? 'Hide from website' : 'Show on website' ?>" aria-label="Show or hide"><i class="fa-regular <?= $g['is_active'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i></button>
                <button class="icon-btn danger" formaction="?" name="action" value="delete" data-confirm-click="Delete this photo?" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
              </div>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><i class="fa-regular fa-image"></i>No photos here yet.</div>
    <?php endif; ?>
  </div>
</section>
<?php admin_bottom(); ?>
