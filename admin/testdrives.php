<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['requested', 'confirmed', 'completed', 'cancelled'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, $allowed, true)) {
        $exec = mb_substr(trim((string) ($_POST['executive'] ?? '')), 0, 120);
        updateRow('test_drives', ['status' => $status, 'executive' => $exec !== '' ? $exec : null], 'id = ?', [$id]);
        $td = fetchOne('SELECT * FROM test_drives WHERE id = ?', [$id]);
        if ($td) { notify((int) $td['user_id'], 'Test drive ' . $status, 'Slot: ' . $td['slot_date'] . ', ' . $td['slot_time'] . ($exec !== '' ? ' with ' . $exec : ''), 'account.php'); }
        logActivity((int) $admin['id'], 'test_drives.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
    }
    redirect(base('admin/testdrives.php'));
}

$rows = fetchAll("SELECT t.*, v.make, v.model, v.year, u.name AS customer, u.mobile FROM test_drives t JOIN listings l ON l.id = t.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = t.user_id ORDER BY t.slot_date DESC, t.id DESC");
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Test drive dispatch', 'testdrives');
?>
<p class="muted">Assign staff and confirm slots. Buyers immediately see the new status in My account.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Vehicle</th><th>Customer</th><th>Mode</th><th>Slot</th><th>Location</th><th>Executive</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((int) $r['id'])) ?></td>
      <td><?= e((string) ($r['year'] . ' ' . $r['make'] . ' ' . $r['model'])) ?></td>
      <td><?= e((string) ($r['customer'] . ' - ' . (string) $r['mobile'])) ?></td>
      <td><?= e((string) (ucfirst((string) $r['mode']))) ?></td>
      <td class="num"><?= e((string) (date('d M Y', strtotime((string) $r['slot_date'])) . ', ' . (string) $r['slot_time'])) ?></td>
      <td><?= e((string) ((string) ($r['address'] ?? '-'))) ?></td>
      <td><?= e((string) ($r['executive'] ?? '-')) ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select class="form-select" style="padding:6px 8px" name="status">
            <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r[$statusCol] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
          </select>
          <input class="form-control" style="padding:6px 8px;width:130px" name="executive" value="<?= e((string) ($r['executive'] ?? '')) ?>" placeholder="Executive">
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
