<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $row = q('SELECT * FROM admins WHERE id = ?', [$admin['id']])->fetch();
    $action = post('action');

    /* ---- my details: name, email and (optionally) a new password, confirmed with the current password ---- */
    if ($action === 'me') {
        $name = mb_substr(post('name'), 0, 120);
        $email = strtolower(mb_substr(post('email'), 0, 190));
        $new = (string) ($_POST['new_password'] ?? '');
        $current = (string) ($_POST['current_password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter your name and a valid email.');
        } elseif (!password_verify($current, $row['password_hash'])) {
            flash('error', 'Your current password is not correct, so nothing was changed.');
        } elseif (q('SELECT 1 FROM admins WHERE email = ? AND id <> ?', [$email, $admin['id']])->fetchColumn()) {
            flash('error', 'Another admin already uses that email.');
        } elseif ($new !== '' && strlen($new) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } elseif ($new !== '' && $new !== ($_POST['confirm_password'] ?? '')) {
            flash('error', 'The two new passwords do not match.');
        } else {
            q('UPDATE admins SET name = ?, email = ? WHERE id = ?', [$name, $email, $admin['id']]);
            if ($new !== '') {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [$hash, $admin['id']]);
                session_regenerate_id(true);
                $_SESSION['pw_fp'] = admin_pw_fingerprint($hash);   // keep this session, sign out all others
                flash('success', 'Details and password saved. Other devices signed in with the old password have been signed out.');
            } else {
                flash('success', 'Your details were saved.');
            }
        }
    }

    if ($action === 'add_admin') {
        $name = mb_substr(post('name'), 0, 120);
        $email = strtolower(mb_substr(post('email'), 0, 190));
        $pass = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('error', 'New admins need a name, a valid email and a password of at least 8 characters.');
        } elseif (q('SELECT 1 FROM admins WHERE email = ?', [$email])->fetchColumn()) {
            flash('error', 'An admin with that email already exists.');
        } else {
            q('INSERT INTO admins (name, email, password_hash) VALUES (?,?,?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('success', $name . ' can now sign in. Give them the password and ask them to change it.');
        }
    }

    if ($action === 'remove_admin') {
        $rid = (int) post('id');
        if ($rid === (int) $admin['id']) flash('error', 'You cannot remove your own account.');
        else { q('DELETE FROM admins WHERE id = ?', [$rid]); flash('success', 'Admin removed. Their sign-in history is kept.'); }
    }
    redirect('admin/account.php' . (isset($_GET['who']) ? '?who=' . (int) $_GET['who'] : ''));
}

$me = q('SELECT * FROM admins WHERE id = ?', [$admin['id']])->fetch();
$admins = q("SELECT a.id, a.name, a.email, a.created_at,
                    (SELECT COUNT(*) FROM admin_logins l WHERE l.admin_id = a.id AND l.success = 1) AS logins,
                    (SELECT MAX(created_at) FROM admin_logins l WHERE l.admin_id = a.id AND l.success = 1) AS last_login,
                    (SELECT COUNT(*) FROM admin_logins l WHERE l.admin_id = a.id AND l.success = 0 AND l.created_at > NOW() - INTERVAL 7 DAY) AS failed_week
             FROM admins a ORDER BY a.name")->fetchAll();

$who = (int) ($_GET['who'] ?? 0);
// one row per admin; the individual sign-ins are listed only for the admin that was clicked
$summary = q("SELECT a.id, a.name, a.email,
                     SUM(l.success = 1) AS ok, SUM(l.success = 0) AS failed,
                     MAX(CASE WHEN l.success = 1 THEN l.created_at END) AS last_ok,
                     (SELECT l2.user_agent FROM admin_logins l2 WHERE l2.admin_id = a.id AND l2.success = 1 ORDER BY l2.id DESC LIMIT 1) AS last_ua
              FROM admins a LEFT JOIN admin_logins l ON l.admin_id = a.id
              GROUP BY a.id, a.name, a.email
              ORDER BY MAX(CASE WHEN l.success = 1 THEN l.created_at END) IS NULL,
                       MAX(CASE WHEN l.success = 1 THEN l.created_at END) DESC, a.name")->fetchAll();
$whoRow = null;
foreach ($summary as $srow) if ((int) $srow['id'] === $who) $whoRow = $srow;
$logs = $whoRow ? q('SELECT * FROM admin_logins WHERE admin_id = ? ORDER BY id DESC LIMIT 200', [$who])->fetchAll() : [];

admin_top('My account', 'account');
?>
<div class="acc-grid">
  <!-- my details -->
  <section class="card">
    <div class="card-head"><h2><i class="fa-regular fa-user" style="color:var(--pink)"></i> My details</h2></div>
    <form method="post" class="card-body acc-form" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="me">
      <div class="form-grid">
        <label class="fld"><span>Name</span><input name="name" value="<?= e($me['name']) ?>" required maxlength="120"></label>
        <label class="fld"><span>Email (used to sign in)</span><input type="email" name="email" value="<?= e($me['email']) ?>" required maxlength="190" autocomplete="username"></label>
        <label class="fld"><span>New password <small class="muted">(leave empty to keep)</small></span><input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
        <label class="fld"><span>Repeat new password</span><input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label>
      </div>
      <div class="acc-confirm">
        <label class="fld"><span>Current password <small class="muted">(needed to save)</small></span><input type="password" name="current_password" required autocomplete="current-password"></label>
        <button class="btn btn-pink"><i class="fa-solid fa-floppy-disk"></i> Save</button>
      </div>
    </form>
  </section>

  <!-- admins -->
  <section class="card">
    <div class="card-head">
      <h2><i class="fa-solid fa-users" style="color:var(--pink)"></i> Admins</h2>
      <button type="button" class="btn btn-navy btn-sm" id="addAdminBtn"><i class="fa-solid fa-user-plus"></i> Add admin</button>
    </div>
    <form method="post" class="card-body acc-add" id="addAdminForm" hidden autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
      <div class="form-grid acc-add-grid">
        <label class="fld"><span>Name</span><input name="name" required maxlength="120"></label>
        <label class="fld"><span>Email</span><input type="email" name="email" required maxlength="190" autocomplete="off"></label>
        <label class="fld"><span>Temporary password</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-pink acc-add-btn">Add</button>
      </div>
    </form>
    <ul class="acc-admins">
      <?php foreach ($admins as $a): $isMe = (int) $a['id'] === (int) $admin['id']; ?>
        <li>
          <span class="adm-avatar"><?= e(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></span>
          <div class="grow">
            <strong><?= e($a['name']) ?><?= $isMe ? ' <span class="pill pill-confirmed">You</span>' : '' ?></strong>
            <small><?= e($a['email']) ?></small>
          </div>
          <div class="acc-stat"><b><?= (int) $a['logins'] ?></b><small>sign-ins</small></div>
          <div class="acc-stat acc-last"><b><?= $a['last_login'] ? e(date('d M, H:i', strtotime($a['last_login']))) : 'Never' ?></b><small>last sign-in</small></div>
          <?php if ((int) $a['failed_week']): ?><span class="pill pill-cancelled" title="Wrong password attempts in the last 7 days"><?= (int) $a['failed_week'] ?> failed</span><?php endif; ?>
          <a class="icon-btn" href="?who=<?= (int) $a['id'] ?>#log" title="See this admin's sign-ins" aria-label="See sign-ins"><i class="fa-solid fa-clock-rotate-left"></i></a>
          <?php if (!$isMe): ?>
            <form method="post" data-confirm="Remove <?= e($a['name']) ?> as an admin?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_admin"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="icon-btn danger" title="Remove admin" aria-label="Remove admin"><i class="fa-solid fa-user-minus"></i></button></form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- sign-in log: summary per admin, click the number for details -->
  <section class="card acc-log" id="log">
    <div class="card-head">
      <h2><i class="fa-solid fa-clock-rotate-left" style="color:var(--pink)"></i> Sign-in log</h2>
      <span class="muted" style="font-size:.84rem">Click a number to see each sign-in</span>
    </div>
    <div class="table-wrap">
      <table class="tbl stack">
        <thead><tr><th>Admin</th><th>Sign-ins</th><th>Wrong passwords</th><th>Last sign-in</th><th>Last device</th></tr></thead>
        <tbody>
        <?php foreach ($summary as $sr): $open = $whoRow && (int) $sr['id'] === $who; ?>
          <tr class="<?= $open ? 'is-open' : '' ?>">
            <td class="no-label"><span class="t-strong"><?= e($sr['name']) ?></span><span class="t-sub"><?= e($sr['email']) ?></span></td>
            <td data-label="Sign-ins"><a class="count-link" href="<?= $open ? '?' : '?who=' . (int) $sr['id'] ?>#log"><?= (int) $sr['ok'] ?> <i class="fa-solid fa-chevron-<?= $open ? 'up' : 'down' ?>"></i></a></td>
            <td data-label="Wrong passwords"><?= (int) $sr['failed'] ? '<a class="count-link bad" href="?who=' . (int) $sr['id'] . '#log">' . (int) $sr['failed'] . '</a>' : '<span class="muted">0</span>' ?></td>
            <td data-label="Last sign-in"><?= $sr['last_ok'] ? e(date('D d M Y, H:i', strtotime($sr['last_ok']))) : '<span class="muted">Never</span>' ?></td>
            <td data-label="Last device"><?= $sr['last_ua'] ? e(device_label($sr['last_ua'])) : '<span class="muted">-</span>' ?></td>
          </tr>
          <?php if ($open): ?>
            <tr class="log-detail"><td colspan="5" class="no-label">
              <?php if ($logs): ?>
                <ul class="log-list">
                  <?php foreach ($logs as $l): ?>
                    <li class="<?= $l['success'] ? '' : 'is-bad' ?>">
                      <i class="fa-solid <?= $l['success'] ? 'fa-right-to-bracket' : 'fa-triangle-exclamation' ?>"></i>
                      <span class="ll-when"><?= e(date('D d M Y, H:i', strtotime($l['created_at']))) ?></span>
                      <span class="ll-dev"><?= e(device_label($l['user_agent'])) ?></span>
                      <span class="ll-ip"><?= e($l['ip'] ?? '-') ?></span>
                      <span class="pill <?= $l['success'] ? 'pill-confirmed' : 'pill-cancelled' ?>"><?= $l['success'] ? 'Signed in' : 'Wrong password' ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="muted" style="margin:0">No sign-ins recorded for <?= e($sr['name']) ?> yet.</p>
              <?php endif; ?>
            </td></tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted acc-note">Sign-ins are recorded from 13 Nov 2026. Wrong-password attempts are counted only for emails that belong to an admin.</p>
  </section>
</div>
<?php admin_bottom(); ?>
