<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/receipts.php';
require_admin();

$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && !isset(BOOKING_STATUSES[$status]) && $status !== 'paid') $status = '';
$search = trim((string) ($_GET['q'] ?? ''));
$when = (string) ($_GET['when'] ?? '');

$where = [];
$params = [];
if ($status === 'paid') { $where[] = 'b.is_paid = 1'; }
elseif ($status !== '') { $where[] = 'b.status = ?'; $params[] = $status; }
if ($search !== '') {
    $where[] = '(b.full_name LIKE ? OR b.phone LIKE ? OR b.email LIKE ? OR b.ref LIKE ? OR b.venue LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $search . '%'));
}
if ($when === 'upcoming') $where[] = 'b.event_date >= CURDATE()';
if ($when === 'past') $where[] = 'b.event_date < CURDATE()';
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* CSV export of the current filter */
if (isset($_GET['export'])) {
    $rows = q("SELECT b.ref, b.created_at, b.full_name, b.phone, b.email, COALESCE(p.name,'Custom quote') package, b.event_type, b.event_date, b.start_time, b.duration_hours, b.venue, b.guests, b.status, IF(b.is_paid = 1, 'Yes', 'No') AS paid, b.quoted_amount, b.message, b.admin_notes
               FROM bookings b LEFT JOIN packages p ON p.id = b.package_id $whereSql ORDER BY b.created_at DESC", $params)->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tosmic-bookings-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Received', 'Name', 'Phone', 'Email', 'Package', 'Event type', 'Event date', 'Start time', 'Hours', 'Venue', 'Guests', 'Status', 'Paid', 'Quoted (UGX)', 'Client message', 'Admin notes']);
    foreach ($rows as $r) {
        // stop spreadsheet apps from treating text as formulas
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r));
    }
    exit;
}

$counts = ['' => 0];
foreach (q('SELECT status, COUNT(*) c FROM bookings GROUP BY status')->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['c'];
    $counts[''] += (int) $r['c'];
}
$counts['paid'] = (int) q('SELECT COUNT(*) FROM bookings WHERE is_paid = 1')->fetchColumn();
// tabs: the statuses, with Paid shown right after Confirmed
$tabs = [];
foreach (BOOKING_STATUSES as $k => $label) { $tabs[$k] = $label; if ($k === 'confirmed') $tabs['paid'] = 'Paid'; }

$perPage = 15;
$total = (int) q("SELECT COUNT(*) FROM bookings b $whereSql", $params)->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$rows = q("SELECT b.*, p.name AS package_name, p.price AS package_price, (SELECT COALESCE(SUM(r.amount), 0) FROM receipts r WHERE r.booking_id = b.id AND r.is_void = 0) AS paid FROM bookings b LEFT JOIN packages p ON p.id = b.package_id $whereSql ORDER BY b.created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params)->fetchAll();

$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['status' => $status, 'q' => $search, 'when' => $when], $over), fn($v) => $v !== '' && $v !== null));

admin_top('Bookings', 'bookings', '<a href="' . e(url('admin/bookings.php' . $qs(['export' => 1]))) . '" class="btn btn-light"><i class="fa-solid fa-file-csv"></i><span class="lbl">Export CSV</span></a>');
?>
<section class="card">
  <div class="card-head">
    <nav class="tabs">
      <a href="<?= e($qs(['status' => '', 'page' => null])) ?>" class="<?= $status === '' ? 'active' : '' ?>">All<em><?= $counts[''] ?></em></a>
      <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= e($qs(['status' => $k, 'page' => null])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($label) ?><em><?= $counts[$k] ?? 0 ?></em></a>
      <?php endforeach; ?>
    </nav>
    <form class="toolbar" method="get">
      <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <select name="when" class="btn btn-light" data-autosubmit aria-label="Event date">
        <option value="">Any date</option>
        <option value="upcoming" <?= $when === 'upcoming' ? 'selected' : '' ?>>Upcoming events</option>
        <option value="past" <?= $when === 'past' ? 'selected' : '' ?>>Past events</option>
      </select>
      <label class="search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, phone, ref, venue"></label>
    </form>
  </div>

  <?php if ($rows): ?>
  <div class="table-wrap">
    <table class="tbl stack">
      <thead><tr><th>Client</th><th>Package</th><th>Event</th><th>Venue</th><th>Received</th><th>Status</th><th>Payment</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $b): ?>
        <tr>
          <td class="no-label"><a href="<?= e(url('admin/booking.php?id=' . $b['id'])) ?>"><span class="t-strong"><?= e($b['full_name']) ?></span><span class="t-sub"><?= e($b['ref']) ?> · <?= e($b['phone']) ?></span></a></td>
          <td data-label="Package"><?= e($b['package_name'] ?? 'Custom quote') ?></td>
          <td data-label="Event"><?= $b['event_date'] ? e(date('D d M Y', strtotime($b['event_date']))) : '-' ?><span class="t-sub"><?= e($b['event_type'] ?? '') ?></span></td>
          <td data-label="Venue"><?= e($b['venue'] ?: '-') ?></td>
          <td data-label="Received"><?= e(date('d M, H:i', strtotime($b['created_at']))) ?></td>
          <td data-label="Status"><?= status_pill($b['status'], (bool) $b['is_paid']) ?></td>
          <td data-label="Payment"><?php $tot = booking_total($b); $pd = (int) $b['paid']; $st = $b['is_paid'] ? 'paid' : ($pd === 0 ? 'unpaid' : (($tot !== null && $pd >= $tot) ? 'paid' : 'part')); ?><?php if (in_array($b['status'], PAYABLE_STATUSES, true) || $pd > 0 || $b['is_paid']): ?><span class="pill pay-<?= $st ?>"><?= e(PAYMENT_STATES[$st]) ?></span><?php if ($pd): ?><span class="t-sub">UGX <?= e(number_format($pd)) ?></span><?php endif; ?><?php else: ?><span class="muted">-</span><?php endif; ?></td>
          <td class="no-label"><div class="actions">
            <a class="btn btn-navy btn-sm" href="<?= e(url('admin/booking.php?id=' . $b['id'])) ?>"><i class="fa-regular fa-eye"></i> View</a>
            <a class="icon-btn" href="<?= e(whatsapp_link_to($b['phone'], 'Hello ' . $b['full_name'] . ', this is Tosmic Events about your booking ' . $b['ref'] . '.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp client" title="WhatsApp client"><i class="fa-brands fa-whatsapp"></i></a>
            <a class="icon-btn" href="<?= e(phone_href($b['phone'])) ?>" aria-label="Call client" title="Call client"><i class="fa-solid fa-phone"></i></a>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
  <?php else: ?>
    <div class="empty"><i class="fa-regular fa-folder-open"></i><?= $search || $status || $when ? 'No bookings match these filters.' : 'No booking requests yet.' ?></div>
  <?php endif; ?>
</section>
<?php admin_bottom(); ?>
