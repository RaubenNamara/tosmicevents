<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$tab = (string) ($_GET['tab'] ?? '');
$tabs = ['pending' => 'Waiting for review', 'published' => 'Published', 'hidden' => 'Hidden'];
$counts = q("SELECT
    SUM(reviewed = 0) AS pending,
    SUM(reviewed = 1 AND is_active = 1) AS published,
    SUM(reviewed = 1 AND is_active = 0) AS hidden
  FROM testimonials")->fetch();
if (!isset($tabs[$tab])) $tab = (int) $counts['pending'] > 0 ? 'pending' : 'published';
$back = 'admin/testimonials.php?tab=' . $tab;

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');
    if ($action === 'approve') {
        q('UPDATE testimonials SET is_active = 1, reviewed = 1 WHERE id = ?', [$id]);
        flash('success', 'Review published on the website.');
        redirect($back);
    }
    if ($action === 'hide') {
        q('UPDATE testimonials SET is_active = 0, reviewed = 1 WHERE id = ?', [$id]);
        flash('success', 'Review hidden from the website.');
        redirect($back);
    }
    if ($action === 'delete') {
        q('DELETE FROM testimonials WHERE id = ?', [$id]);
        flash('success', 'Review deleted.');
        redirect($back);
    }
    if ($action === 'save') {
        $data = [
            'name' => mb_substr(post('name'), 0, 120),
            'role' => mb_substr(post('role'), 0, 160) ?: null,
            'message' => mb_substr(post('message'), 0, 1500),
            'rating' => max(1, min(5, (int) post('rating', '5'))),
            'sort_order' => (int) post('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['message'] === '') {
            flash('error', 'Please fill in the client name and what they said.');
            $_SESSION['old_t'] = $data;
            redirect('admin/testimonials.php?' . ($id ? 'edit=' . $id : 'new=1'));
        }
        if ($id) {
            q('UPDATE testimonials SET name=:name, role=:role, message=:message, rating=:rating, sort_order=:sort_order, is_active=:is_active, reviewed=1 WHERE id=:id', $data + ['id' => $id]);
        } else {
            q("INSERT INTO testimonials (name, role, message, rating, sort_order, is_active, source, reviewed) VALUES (:name,:role,:message,:rating,:sort_order,:is_active,'admin',1)", $data);
        }
        flash('success', 'Review saved.');
        redirect('admin/testimonials.php?tab=' . ($data['is_active'] ? 'published' : 'hidden'));
    }
}

$editing = isset($_GET['edit']) ? (q('SELECT * FROM testimonials WHERE id = ?', [(int) $_GET['edit']])->fetch() ?: null) : null;
$showForm = $editing || isset($_GET['new']);
if ($showForm && isset($_SESSION['old_t'])) $editing = array_merge($editing ?? [], $_SESSION['old_t']);
unset($_SESSION['old_t']);

$where = ['pending' => 't.reviewed = 0', 'published' => 't.reviewed = 1 AND t.is_active = 1', 'hidden' => 't.reviewed = 1 AND t.is_active = 0'][$tab];
$rows = q("SELECT t.*, b.ref AS booking_ref FROM testimonials t LEFT JOIN bookings b ON b.id = t.booking_id WHERE $where ORDER BY t.sort_order, t.created_at DESC")->fetchAll();

admin_top('Reviews', 'testimonials', $showForm ? '' : '<a href="?new=1" class="btn btn-pink"><i class="fa-solid fa-plus"></i><span class="lbl">Add review</span></a>');
?>
<p class="muted" style="margin:-6px 0 18px">Clients send feedback from the <a href="<?= e(url('feedback.php')) ?>" target="_blank" style="color:var(--pink);font-weight:600">Reviews page</a>. Nothing appears on the website until you publish it. <span class="pill pill-confirmed"><i class="fa-solid fa-circle-check"></i> Verified booking</span> means the booking reference and phone number they gave match a real booking.</p>

<?php if ($showForm): $t = $editing ?? []; ?>
<section class="card" style="margin-bottom:22px">
  <div class="card-head"><h2><?= !empty($t['id']) ? 'Edit review' : 'Add a review you received elsewhere' ?></h2><a href="<?= e(url('admin/testimonials.php')) ?>" class="btn btn-light btn-sm">Cancel</a></div>
  <form method="post" class="card-body form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($t['id'] ?? 0) ?>">
    <label class="fld"><span>Client name *</span><input name="name" value="<?= e($t['name'] ?? '') ?>" required maxlength="120"></label>
    <label class="fld"><span>Event</span><input name="role" value="<?= e($t['role'] ?? '') ?>" maxlength="160" placeholder="e.g. Wedding reception"></label>
    <label class="fld full"><span>What they said *</span><textarea name="message" required maxlength="1500"><?= e($t['message'] ?? '') ?></textarea><?php if (($t['source'] ?? '') === 'client'): ?><small class="hint">Sent by the client. Fix spelling if needed, but keep their meaning.</small><?php endif; ?></label>
    <label class="fld"><span>Rating</span><select name="rating"><?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>" <?= (int) ($t['rating'] ?? 5) === $r ? 'selected' : '' ?>><?= str_repeat('★', $r) ?></option><?php endfor; ?></select></label>
    <label class="fld"><span>Position (lower shows first)</span><input type="number" name="sort_order" value="<?= (int) ($t['sort_order'] ?? 0) ?>"></label>
    <label class="switch full"><input type="checkbox" name="is_active" <?= ($t['is_active'] ?? 1) ? 'checked' : '' ?>> Show on the website</label>
    <div class="form-actions full"><button class="btn btn-pink"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <div class="card-head">
    <nav class="tabs">
      <?php foreach ($tabs as $k => $label): ?>
        <a href="?tab=<?= e($k) ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($label) ?><em><?= (int) $counts[$k] ?></em></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <?php if ($rows): ?>
    <ul class="review-admin">
      <?php foreach ($rows as $t): ?>
        <li>
          <div class="ra-head">
            <span class="adm-avatar"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span>
            <div class="grow">
              <strong><?= e($t['name']) ?></strong>
              <small><?= e($t['role'] ?: 'Event not given') ?> · <?= e(date('d M Y, H:i', strtotime($t['created_at']))) ?> · <?= $t['source'] === 'client' ? 'sent by client' : 'added by admin' ?></small>
            </div>
            <span class="ra-stars"><?= str_repeat('★', (int) $t['rating']) ?><span style="color:#ddd"><?= str_repeat('★', 5 - (int) $t['rating']) ?></span></span>
          </div>
          <p class="ra-msg"><?= nl2br(e($t['message'])) ?></p>
          <div class="ra-foot">
            <div class="ra-meta">
              <?php if ($t['booking_id']): ?><a class="pill pill-confirmed" href="<?= e(url('admin/booking.php?id=' . $t['booking_id'])) ?>"><i class="fa-solid fa-circle-check"></i> Verified booking <?= e($t['booking_ref']) ?></a>
              <?php elseif ($t['source'] === 'client'): ?><span class="pill pill-completed">Not linked to a booking</span><?php endif; ?>
              <?php if ($t['phone']): ?><a class="pill pill-contacted" href="<?= e(phone_href($t['phone'])) ?>"><i class="fa-solid fa-phone"></i> <?= e($t['phone']) ?></a><?php endif; ?>
            </div>
            <div class="actions">
              <?php if ($tab !== 'published'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-pink btn-sm"><i class="fa-solid fa-check"></i> Publish</button></form>
              <?php endif; ?>
              <?php if ($tab !== 'hidden'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-light btn-sm"><i class="fa-regular fa-eye-slash"></i> <?= $tab === 'pending' ? "Don't publish" : 'Hide' ?></button></form>
              <?php endif; ?>
              <a class="icon-btn" href="?edit=<?= (int) $t['id'] ?>" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
              <form method="post" data-confirm="Delete this review for good?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="icon-btn danger" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></form>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="empty"><i class="fa-regular fa-comment-dots"></i><?= $tab === 'pending' ? 'No new feedback waiting. You are all caught up.' : ($tab === 'published' ? 'No reviews on the website yet.' : 'No hidden reviews.') ?></div>
  <?php endif; ?>
</section>
<?php admin_bottom(); ?>
