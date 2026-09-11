<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$allowed = ['open', 'processing', 'completed'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $resolution = trim((string) ($_POST['resolution'] ?? ''));
    if ($id > 0 && in_array($status, $allowed, true)) {
        updateRow('support_tickets', ['status' => $status, 'resolution' => ($resolution !== '' ? $resolution : null)], 'id = ?', [$id]);
        $t = fetchOne('SELECT user_id, subject FROM support_tickets WHERE id = ?', [$id]);
        if ($t && (int) $t['user_id'] > 0) {
            $msg = 'Your complaint ' . refCode('CMP', $id) . ' is now ' . $status;
            if ($status === 'completed' && $resolution !== '') { $msg .= ': ' . mb_substr($resolution, 0, 200); }
            notify((int) $t['user_id'], 'Complaint updated', $msg, 'support.php');
        }
        logActivity((int) $admin['id'], 'complaints.resolved', '#' . $id . ' -> ' . $status);
        flash('success', 'Complaint ' . refCode('CMP', $id) . ' moved to ' . $status . '.');
    }
    redirect(base('admin/complaints.php'));
}

$rows = fetchAll("SELECT t.*, u.name AS reporter FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.category = 'report' ORDER BY t.id DESC");
$listingIds = [];
foreach ($rows as $r) {
    if (preg_match('/Listing report #(\\d+)/', (string) $r['subject'], $m)) { $listingIds[(int) $m[1]] = true; }
}
$cars = [];
if ($listingIds) {
    $ph = implode(',', array_fill(0, count($listingIds), '?'));
    foreach (fetchAll("SELECT l.id, v.make, v.model, v.year, l.status, u.name AS seller FROM listings l JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id WHERE l.id IN ($ph)", array_keys($listingIds)) as $c) {
        $cars[(int) $c['id']] = $c;
    }
}

adminHeader('Complaints & disputes', 'complaints');
?>
<p class="muted">Listing reports from buyers, oldest-first in spirit, newest-first on screen. Record what you decided - the reporter is notified when you close it.</p>
<div class="table-wrap"><table class="data">
  <thead><tr><th>#</th><th>Reported car</th><th>Reporter &amp; reason</th><th>Status</th><th>Resolution</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="5" class="empty">No complaints. The marketplace is behaving.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <?php
      $lid = preg_match('/Listing report #(\\d+)/', (string) $r['subject'], $m) ? (int) $m[1] : 0;
      $car = $lid > 0 ? ($cars[$lid] ?? null) : null;
    ?>
    <tr>
      <td class="num"><?= e(refCode('CMP', (int) $r['id'])) ?><div class="muted num" style="font-size:12px"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></div></td>
      <td><?php if ($car): ?>
          <a href="<?= e(base('car.php?id=' . $lid)) ?>"><?= e($car['year'] . ' ' . $car['make'] . ' ' . $car['model']) ?></a>
          <div class="muted" style="font-size:12px">seller: <?= e((string) $car['seller']) ?> &middot; <?= statusBadge((string) $car['status']) ?></div>
        <?php elseif ($lid > 0): ?><small class="muted">Listing #<?= $lid ?> (removed)</small>
        <?php else: ?><small class="muted">-</small><?php endif; ?></td>
      <td><b><?= e((string) ($r['reporter'] ?? 'Guest')) ?></b>
        <div class="muted" style="font-size:12px;white-space:pre-line;max-width:340px"><?= e((string) $r['message']) ?></div></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td style="min-width:260px">
        <form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <textarea class="form-control" name="resolution" rows="2" placeholder="e.g. Seller warned; price corrected."><?= e((string) ($r['resolution'] ?? '')) ?></textarea>
          <div style="display:flex;gap:6px;margin-top:6px">
            <select class="form-select" style="padding:6px 8px" name="status">
              <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-dark btn-sm" type="submit">Save</button>
          </div>
        </form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
