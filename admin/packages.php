<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'delete') {
        $img = q('SELECT image FROM packages WHERE id = ?', [$id])->fetchColumn();
        q('DELETE FROM packages WHERE id = ?', [$id]);
        delete_upload($img ?: null);
        flash('success', 'Package deleted. Bookings that used it now show as custom quotes.');
        redirect('admin/packages.php');
    }
    if ($action === 'toggle') {
        q('UPDATE packages SET is_active = 1 - is_active WHERE id = ?', [$id]);
        redirect('admin/packages.php');
    }
    if ($action === 'save') {
        $existing = $id ? q('SELECT * FROM packages WHERE id = ?', [$id])->fetch() : null;
        $data = [
            'name' => mb_substr(post('name'), 0, 120),
            'tagline' => mb_substr(post('tagline'), 0, 255) ?: null,
            'price' => (int) preg_replace('/\D/', '', post('price')),
            'events' => implode("\n", lines(post('events'))),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'sort_order' => (int) post('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $image = $existing['image'] ?? null;
        try {
            if ($data['name'] === '') throw new RuntimeException('The package needs a name.');
            if ($data['price'] <= 0) throw new RuntimeException('Please enter the price in UGX.');
            if (!empty($_FILES['image']['name'])) {
                $image = save_upload($_FILES['image'], 'packages', 1000);
                if ($existing) delete_upload($existing['image']);
            }
            $data['image'] = $image;
            if ($existing) {
                q('UPDATE packages SET name=:name, tagline=:tagline, price=:price, events=:events, is_featured=:is_featured, sort_order=:sort_order, is_active=:is_active, image=:image WHERE id=:id', $data + ['id' => $id]);
            } else {
                q('INSERT INTO packages (name, tagline, price, events, is_featured, sort_order, is_active, image) VALUES (:name,:tagline,:price,:events,:is_featured,:sort_order,:is_active,:image)', $data);
            }
            flash('success', 'Package saved.');
            redirect('admin/packages.php');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
            $_SESSION['old_pkg'] = $data;
            redirect('admin/packages.php?' . ($id ? 'edit=' . $id : 'new=1'));
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) $editing = q('SELECT * FROM packages WHERE id = ?', [(int) $_GET['edit']])->fetch() ?: null;
$showForm = $editing || isset($_GET['new']);
if ($showForm && isset($_SESSION['old_pkg'])) $editing = array_merge($editing ?? [], $_SESSION['old_pkg']);
unset($_SESSION['old_pkg']);

$packages = q('SELECT p.*, (SELECT COUNT(*) FROM bookings b WHERE b.package_id = p.id) AS bookings FROM packages p ORDER BY sort_order, id')->fetchAll();
admin_top('Packages', 'packages', $showForm ? '' : '<a href="?new=1" class="btn btn-pink"><i class="fa-solid fa-plus"></i><span class="lbl">Add package</span></a>');
?>

<?php if ($showForm): $p = $editing ?? []; ?>
<section class="card" style="margin-bottom:22px">
  <div class="card-head"><h2><?= !empty($p['id']) ? 'Edit package' : 'New package' ?></h2><a href="<?= e(url('admin/packages.php')) ?>" class="btn btn-light btn-sm">Cancel</a></div>
  <form method="post" enctype="multipart/form-data" class="card-body">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
    <div class="grid grid-main">
      <div class="form-grid">
        <label class="fld"><span>Name *</span><input name="name" value="<?= e($p['name'] ?? '') ?>" required maxlength="120" placeholder="e.g. Corporate Events"></label>
        <label class="fld"><span>Price (UGX) *</span><input name="price" inputmode="numeric" value="<?= !empty($p['price']) ? e(number_format((int) $p['price'])) : '' ?>" required placeholder="1,500,000"></label>
        <label class="fld full"><span>Short description</span><input name="tagline" value="<?= e($p['tagline'] ?? '') ?>" maxlength="255"></label>
        <label class="fld full"><span>Events covered</span><textarea name="events" rows="6" placeholder="One per line"><?= e($p['events'] ?? '') ?></textarea><small class="hint">One event type per line. Each line becomes a bullet on the website.</small></label>
        <label class="fld"><span>Position</span><input type="number" name="sort_order" value="<?= (int) ($p['sort_order'] ?? count($packages) + 1) ?>"></label>
        <div style="display:grid;gap:10px;align-content:end">
          <label class="switch"><input type="checkbox" name="is_featured" <?= !empty($p['is_featured']) ? 'checked' : '' ?>> Mark as featured</label>
          <label class="switch"><input type="checkbox" name="is_active" <?= ($p['is_active'] ?? 1) ? 'checked' : '' ?>> Show on the website</label>
        </div>
      </div>
      <div>
        <span class="fld"><span>Photo</span></span>
        <?= img_input_preview($p['image'] ?? null) ?>
        <small class="hint">Shown in a gold circle, so a square photo with the subject in the middle works best.</small>
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-pink"><i class="fa-solid fa-floppy-disk"></i> Save package</button></div>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h2>All packages</h2></div>
  <?php if ($packages): ?>
  <div class="table-wrap">
    <table class="tbl stack">
      <thead><tr><th>Package</th><th>Price</th><th>Events</th><th>Bookings</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($packages as $p): ?>
        <tr>
          <td class="no-label"><div style="display:flex;gap:14px;align-items:center"><img class="thumb round" src="<?= e(img_url($p['image'])) ?>" alt=""><span><span class="t-strong"><?= e($p['name']) ?><?= $p['is_featured'] ? ' <i class="fa-solid fa-star" style="color:var(--gold)" title="Featured"></i>' : '' ?></span><span class="t-sub"><?= e($p['tagline'] ?? '') ?></span></span></div></td>
          <td data-label="Price"><b>UGX <?= e(money($p['price'])) ?></b></td>
          <td data-label="Events"><?= count(lines($p['events'])) ?></td>
          <td data-label="Bookings"><?= (int) $p['bookings'] ?></td>
          <td data-label="Status">
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="pill <?= $p['is_active'] ? 'pill-on' : 'pill-off' ?>" style="border:0" title="Click to switch"><?= $p['is_active'] ? 'Showing' : 'Hidden' ?></button>
            </form>
          </td>
          <td class="no-label"><div class="actions">
            <a class="icon-btn" href="?edit=<?= (int) $p['id'] ?>" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
            <form method="post" data-confirm="Delete the <?= e($p['name']) ?> package?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="icon-btn danger" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty"><i class="fa-solid fa-box-open"></i>No packages yet.</div>
  <?php endif; ?>
</section>
<?php admin_bottom(); ?>
