<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$stats = q("SELECT
    SUM(status = 'new') AS new_count,
    SUM(status = 'confirmed' AND event_date >= CURDATE()) AS upcoming,
    SUM(created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS this_month,
    COUNT(*) AS total
  FROM bookings")->fetch();

$recent = q('SELECT b.*, p.name AS package_name FROM bookings b LEFT JOIN packages p ON p.id = b.package_id ORDER BY b.created_at DESC LIMIT 6')->fetchAll();
$upcoming = q("SELECT b.*, p.name AS package_name FROM bookings b LEFT JOIN packages p ON p.id = b.package_id
               WHERE b.event_date >= CURDATE() AND b.status IN ('new','contacted','confirmed') ORDER BY b.event_date LIMIT 6")->fetchAll();

// requests per month, last 6 months
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("first day of -$i month"));
    $months[$m] = 0;
}
foreach (q("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) c FROM bookings WHERE created_at >= ? GROUP BY m", [array_key_first($months) . '-01'])->fetchAll() as $r) {
    if (isset($months[$r['m']])) $months[$r['m']] = (int) $r['c'];
}
$maxM = max(1, max($months));

$byPackage = q("SELECT COALESCE(p.name, 'Custom quote') name, COUNT(*) c FROM bookings b LEFT JOIN packages p ON p.id = b.package_id GROUP BY name ORDER BY c DESC")->fetchAll();
$totalB = max(1, (int) $stats['total']);

$content = [
    'slides' => (int) q('SELECT COUNT(*) FROM slides WHERE is_active = 1')->fetchColumn(),
    'packages' => (int) q('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn(),
    'gallery' => (int) q('SELECT COUNT(*) FROM gallery WHERE is_active = 1')->fetchColumn(),
    'testimonials' => (int) q('SELECT COUNT(*) FROM testimonials WHERE is_active = 1')->fetchColumn(),
    'pending' => (int) q('SELECT COUNT(*) FROM testimonials WHERE reviewed = 0')->fetchColumn(),
];

$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
admin_top('Dashboard', 'dashboard', '<a href="' . e(url('admin/bookings.php?status=new')) . '" class="btn btn-pink"><i class="fa-regular fa-bell"></i><span class="lbl">New requests</span></a>');
?>
<p class="muted" style="margin:-6px 0 20px"><?= e($greet) ?>, <?= e(explode(' ', current_admin()['name'])[0]) ?>. Here is what is happening with Tosmic Events.</p>

<div class="stats">
  <a class="stat s-pink" href="<?= e(url('admin/bookings.php?status=new')) ?>"><i class="fa-regular fa-bell"></i><strong><?= (int) $stats['new_count'] ?></strong><span>New requests</span></a>
  <a class="stat s-gold" href="<?= e(url('admin/bookings.php?status=confirmed')) ?>"><i class="fa-regular fa-calendar-check"></i><strong><?= (int) $stats['upcoming'] ?></strong><span>Confirmed upcoming</span></a>
  <div class="stat s-cyan"><i class="fa-solid fa-chart-line"></i><strong><?= (int) $stats['this_month'] ?></strong><span>Requests this month</span></div>
  <a class="stat s-purple" href="<?= e(url('admin/bookings.php')) ?>"><i class="fa-solid fa-layer-group"></i><strong><?= (int) $stats['total'] ?></strong><span>All bookings</span></a>
</div>

<div class="grid grid-main">
  <div class="grid">
    <section class="card">
      <div class="card-head"><h2>Latest requests</h2><a href="<?= e(url('admin/bookings.php')) ?>" class="btn btn-light btn-sm">View all</a></div>
      <?php if ($recent): ?>
      <div class="table-wrap">
        <table class="tbl stack">
          <thead><tr><th>Client</th><th>Package</th><th>Event date</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($recent as $b): ?>
            <tr>
              <td class="no-label"><span class="t-strong"><?= e($b['full_name']) ?></span><span class="t-sub"><?= e($b['ref']) ?> · <?= e($b['phone']) ?></span></td>
              <td data-label="Package"><?= e($b['package_name'] ?? 'Custom quote') ?></td>
              <td data-label="Event date"><?= $b['event_date'] ? e(date('d M Y', strtotime($b['event_date']))) : '-' ?></td>
              <td data-label="Status"><?= status_pill($b['status'], (bool) $b['is_paid']) ?></td>
              <td class="no-label"><div class="actions"><a class="btn btn-navy btn-sm" href="<?= e(url('admin/booking.php?id=' . $b['id'])) ?>"><i class="fa-regular fa-eye"></i> View</a></div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="empty"><i class="fa-regular fa-calendar"></i>No booking requests yet. They will appear here as soon as someone uses the booking form.</div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Requests per month</h2><span class="muted">Last 6 months</span></div>
      <div class="card-body">
        <div class="bars">
          <?php foreach ($months as $m => $c): ?>
            <div class="b" title="<?= e(date('F Y', strtotime($m . '-01'))) ?>: <?= $c ?>"><b><?= $c ?></b><i style="height:<?= round($c / $maxM * 100) ?>%"></i><em><?= e(date('M', strtotime($m . '-01'))) ?></em></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="grid">
    <section class="card">
      <div class="card-head"><h2>Coming up</h2></div>
      <?php if ($upcoming): ?>
        <ul class="mini-list">
          <?php foreach ($upcoming as $b): ?>
            <li>
              <span class="date-chip"><b><?= e(date('M', strtotime($b['event_date']))) ?></b><span><?= e(date('d', strtotime($b['event_date']))) ?></span></span>
              <span class="grow"><strong><?= e($b['full_name']) ?></strong><small><?= e($b['venue'] ?: '-') ?></small></span>
              <?= status_pill($b['status'], (bool) $b['is_paid']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty"><i class="fa-regular fa-calendar"></i>No upcoming events.</div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>By package</h2></div>
      <div class="card-body">
        <?php if ($byPackage): foreach ($byPackage as $r): $pct = round($r['c'] / $totalB * 100); ?>
          <div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:6px"><span><?= e($r['name']) ?></span><b><?= (int) $r['c'] ?></b></div>
            <div style="height:8px;border-radius:8px;background:var(--bg);overflow:hidden"><div style="height:100%;width:<?= $pct ?>%;background:var(--pink);border-radius:8px"></div></div>
          </div>
        <?php endforeach; else: ?>
          <p class="muted" style="margin:0">No data yet.</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <div class="card-head"><h2>Website content</h2></div>
      <ul class="mini-list">
        <li><i class="fa-solid fa-images" style="color:var(--pink)"></i><span class="grow"><strong>Slides showing</strong></span><b><?= $content['slides'] ?></b><a class="icon-btn" href="<?= e(url('admin/slides.php')) ?>" aria-label="Manage slides"><i class="fa-solid fa-pen"></i></a></li>
        <li><i class="fa-solid fa-box-open" style="color:var(--pink)"></i><span class="grow"><strong>Packages</strong></span><b><?= $content['packages'] ?></b><a class="icon-btn" href="<?= e(url('admin/packages.php')) ?>" aria-label="Manage packages"><i class="fa-solid fa-pen"></i></a></li>
        <li><i class="fa-regular fa-image" style="color:var(--pink)"></i><span class="grow"><strong>Gallery photos</strong></span><b><?= $content['gallery'] ?></b><a class="icon-btn" href="<?= e(url('admin/gallery.php')) ?>" aria-label="Manage gallery"><i class="fa-solid fa-pen"></i></a></li>
        <li><i class="fa-regular fa-comment-dots" style="color:var(--pink)"></i><span class="grow"><strong>Published reviews</strong><?php if ($content['pending']): ?><small style="color:var(--pink);font-weight:600"><?= $content['pending'] ?> waiting for review</small><?php endif; ?></span><b><?= $content['testimonials'] ?></b><a class="icon-btn" href="<?= e(url('admin/testimonials.php')) ?>" aria-label="Manage reviews"><i class="fa-solid fa-pen"></i></a></li>
      </ul>
    </section>
  </div>
</div>
<?php admin_bottom(); ?>
