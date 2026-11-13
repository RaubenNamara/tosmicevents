<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'delete') {
        $img = q('SELECT image FROM slides WHERE id = ?', [$id])->fetchColumn();
        q('DELETE FROM slides WHERE id = ?', [$id]);
        delete_upload($img ?: null);
        flash('success', 'Slide deleted.');
        redirect('admin/slides.php');
    }
    if ($action === 'toggle') {
        q('UPDATE slides SET is_active = 1 - is_active WHERE id = ?', [$id]);
        redirect('admin/slides.php');
    }
    if ($action === 'order') {
        foreach ((array) ($_POST['order'] ?? []) as $sid => $ord) q('UPDATE slides SET sort_order = ? WHERE id = ?', [(int) $ord, (int) $sid]);
        flash('success', 'Order saved.');
        redirect('admin/slides.php');
    }
    if ($action === 'save') {
        $existing = $id ? q('SELECT * FROM slides WHERE id = ?', [$id])->fetch() : null;
        $data = [
            'eyebrow' => mb_substr(post('eyebrow'), 0, 120) ?: null,
            'title' => mb_substr(post('title'), 0, 160),
            'subtitle' => mb_substr(post('subtitle'), 0, 400) ?: null,
            'layout' => post('layout') === 'full' ? 'full' : 'split',
            'button_text' => mb_substr(post('button_text'), 0, 60) ?: null,
            'button_link' => mb_substr(post('button_link'), 0, 255) ?: null,
            'sort_order' => (int) post('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $image = $existing['image'] ?? null;
        try {
            if ($data['title'] === '') throw new RuntimeException('The slide needs a title.');
            // links may only be a page on this site (letters, digits, - _ / . ? = & #) or an http(s) address
            if ($data['button_link'] !== null && !preg_match('~^(https?://[^\s<>"\']+|[a-z0-9][a-z0-9/_.\-]*(\?[a-z0-9=&_\-]*)?(#[a-z0-9_\-]*)?|#[a-z0-9_\-]+)$~i', $data['button_link'])) {
                throw new RuntimeException('The button link must be a page like booking.php or a full https:// address.');
            }
            if (!empty($_FILES['image']['name'])) {
                $image = save_upload($_FILES['image'], 'slides', 2400);
                if ($existing) delete_upload($existing['image']);
            }
            if (!$image) throw new RuntimeException('Please choose an image for the slide.');
            $data['image'] = $image;
            if ($existing) {
                q('UPDATE slides SET eyebrow=:eyebrow, title=:title, subtitle=:subtitle, layout=:layout, button_text=:button_text, button_link=:button_link, sort_order=:sort_order, is_active=:is_active, image=:image WHERE id=:id', $data + ['id' => $id]);
            } else {
                q('INSERT INTO slides (eyebrow, title, subtitle, layout, button_text, button_link, sort_order, is_active, image) VALUES (:eyebrow,:title,:subtitle,:layout,:button_text,:button_link,:sort_order,:is_active,:image)', $data);
            }
            flash('success', 'Slide saved.');
            redirect('admin/slides.php');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
            $_SESSION['old_slide'] = $data;
            redirect('admin/slides.php?' . ($id ? 'edit=' . $id : 'new=1'));
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) $editing = q('SELECT * FROM slides WHERE id = ?', [(int) $_GET['edit']])->fetch() ?: null;
$showForm = $editing || isset($_GET['new']);
if ($showForm && isset($_SESSION['old_slide'])) $editing = array_merge($editing ?? [], $_SESSION['old_slide']);
unset($_SESSION['old_slide']);

$slides = q('SELECT * FROM slides ORDER BY sort_order, id')->fetchAll();
admin_top('Home slideshow', 'slides', $showForm ? '' : '<a href="?new=1" class="btn btn-pink"><i class="fa-solid fa-plus"></i><span class="lbl">Add slide</span></a>');
?>

<?php if ($showForm): $s = $editing ?? []; ?>
<section class="card" style="margin-bottom:22px">
  <div class="card-head"><h2><?= !empty($s['id']) ? 'Edit slide' : 'New slide' ?></h2><a href="<?= e(url('admin/slides.php')) ?>" class="btn btn-light btn-sm">Cancel</a></div>
  <form method="post" enctype="multipart/form-data" class="card-body">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($s['id'] ?? 0) ?>">
    <div class="grid grid-main">
      <div class="form-grid">
        <label class="fld full"><span>Small heading</span><input name="eyebrow" value="<?= e($s['eyebrow'] ?? '') ?>" placeholder="e.g. Social gatherings" maxlength="120"></label>
        <label class="fld full"><span>Title *</span><input name="title" value="<?= e($s['title'] ?? '') ?>" required maxlength="160"></label>
        <label class="fld full"><span>Text</span><textarea name="subtitle" maxlength="400"><?= e($s['subtitle'] ?? '') ?></textarea></label>
        <label class="fld"><span>Button text</span><input name="button_text" value="<?= e($s['button_text'] ?? '') ?>" placeholder="Book DJ Tosmic" maxlength="60"></label>
        <label class="fld"><span>Button link</span><input name="button_link" value="<?= e($s['button_link'] ?? '') ?>" placeholder="booking.php" maxlength="255"><small class="hint">A page like booking.php or packages.php, or a full https:// link.</small></label>
        <label class="fld"><span>Layout</span>
          <select name="layout">
            <option value="split" <?= ($s['layout'] ?? 'split') === 'split' ? 'selected' : '' ?>>Photo in a gold circle (any photo)</option>
            <option value="full" <?= ($s['layout'] ?? '') === 'full' ? 'selected' : '' ?>>Full-screen photo (large, wide photos)</option>
          </select>
        </label>
        <label class="fld"><span>Position</span><input type="number" name="sort_order" value="<?= (int) ($s['sort_order'] ?? count($slides) + 1) ?>"></label>
        <label class="switch full"><input type="checkbox" name="is_active" <?= ($s['is_active'] ?? 1) ? 'checked' : '' ?>> Show on the website</label>
      </div>
      <div>
        <span class="fld"><span>Image *</span></span>
        <?= img_input_preview($s['image'] ?? null) ?>
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-pink"><i class="fa-solid fa-floppy-disk"></i> Save slide</button></div>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h2>Slides</h2><span class="muted">They play in this order on the home page</span></div>
  <?php if ($slides): ?>
  <form method="post" id="orderForm"><?= csrf_field() ?><input type="hidden" name="action" value="order"></form>
  <div class="table-wrap">
    <table class="tbl stack">
      <thead><tr><th>Slide</th><th>Layout</th><th>Position</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($slides as $s): ?>
        <tr>
          <td class="no-label"><div style="display:flex;gap:14px;align-items:center"><img class="thumb" src="<?= e(img_url($s['image'])) ?>" alt=""><span><span class="t-strong"><?= e($s['title']) ?></span><span class="t-sub"><?= e($s['eyebrow'] ?? '') ?></span></span></div></td>
          <td data-label="Layout"><?= $s['layout'] === 'full' ? 'Full-screen' : 'Gold circle' ?></td>
          <td data-label="Position"><input form="orderForm" type="number" name="order[<?= (int) $s['id'] ?>]" value="<?= (int) $s['sort_order'] ?>" style="width:70px;padding:7px 9px;border:1px solid var(--line);border-radius:9px"></td>
          <td data-label="Status">
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="pill <?= $s['is_active'] ? 'pill-on' : 'pill-off' ?>" style="border:0" title="Click to switch"><?= $s['is_active'] ? 'Showing' : 'Hidden' ?></button>
            </form>
          </td>
          <td class="no-label"><div class="actions">
            <a class="icon-btn" href="?edit=<?= (int) $s['id'] ?>" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
            <form method="post" data-confirm="Delete this slide?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="icon-btn danger" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-body" style="border-top:1px solid var(--line);display:flex;justify-content:flex-end"><button form="orderForm" class="btn btn-light"><i class="fa-solid fa-arrow-down-1-9"></i> Save order</button></div>
  <?php else: ?>
    <div class="empty"><i class="fa-solid fa-images"></i>No slides yet. Add one so the home page has a slideshow.</div>
  <?php endif; ?>
</section>
<?php admin_bottom(); ?>
