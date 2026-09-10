<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['scheduled', 'in_progress', 'completed', 'cancelled'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, $allowed, true)) {
        $data = ['status' => $status];
        if (isset($_POST['score']) && $_POST['score'] !== '') { $data['score'] = (float) $_POST['score']; }
        updateRow('inspections', $data, 'id = ?', [$id]);
        logActivity((int) $admin['id'], 'inspections.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
    }
    redirect(base('admin/inspections.php'));
}

$rows = fetchAll("SELECT i.*, v.make, v.model, v.year, u.name AS customer FROM inspections i JOIN listings l ON l.id = i.listing_id JOIN vehicles v ON v.id = l.vehicle_id LEFT JOIN users u ON u.id = i.requested_by ORDER BY i.id DESC");
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Vehicle inspections', 'inspections');
?>
<p class="muted">280-point inspection jobs. Updating a row writes to the <code>inspections</code> table and refreshes the score shown on the car page.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Vehicle</th><th>Requested by</th><th>Mode</th><th>Scheduled</th><th>Score</th><th>Inspector</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((int) $r['id'])) ?></td>
      <td><?= e((string) ($r['year'] . ' ' . $r['make'] . ' ' . $r['model'])) ?></td>
      <td><?= e((string) ((string) ($r['customer'] ?? 'Walk-in'))) ?></td>
      <td><?= e((string) (ucfirst((string) $r['mode']))) ?></td>
      <td class="num"><?= e((string) (date('d M Y', strtotime((string) $r['slot_date'])))) ?></td>
      <td class="num"><?= e((string) (((int) $r['score']) . ' / 280')) ?></td>
      <td><?= e((string) ((string) ($r['inspector'] ?? '-'))) ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input class="form-control num" style="width:120px;padding:6px 8px" type="number" name="score" placeholder="Score" value="<?= e((string) ($r['score'] ?? '')) ?>">
          <select class="form-select" style="padding:6px 8px" name="status">
            <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r[$statusCol] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
