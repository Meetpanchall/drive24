<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $allowed = ['draft', 'pending', 'approved', 'rejected', 'reserved', 'sold'];
    if ($id > 0 && in_array($status, $allowed, true)) {
        $data = ['status' => $status];
        if (isset($_POST['price']) && (float) $_POST['price'] > 0) { $data['price'] = (float) $_POST['price']; }
        $data['featured'] = isset($_POST['featured']) ? 1 : 0;
        $data['certified'] = isset($_POST['certified']) ? 1 : 0;
        updateRow('listings', $data, 'id = ?', [$id]);
        logActivity((int) $admin['id'], 'inventory.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Vehicle #' . $id . ' updated.');
    }
    redirect(base('admin/vehicles.php'));
}

$filter = (string) ($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($filter, ['draft', 'pending', 'approved', 'rejected', 'reserved', 'sold'], true)) {
    $where = 'WHERE l.status = ?';
    $params[] = $filter;
}
$rows = fetchAll('SELECT l.*, v.make, v.model, v.year, v.city, v.km_driven, u.name AS seller
    FROM listings l JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id '
    . $where . ' ORDER BY l.created_at DESC LIMIT 200', $params);
$counts = [];
foreach (fetchAll('SELECT status, COUNT(*) AS c FROM listings GROUP BY status') as $c) { $counts[$c['status']] = (int) $c['c']; }

adminHeader('Vehicle inventory', 'vehicles');
?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
  <a class="chip" href="<?= e(base('admin/vehicles.php')) ?>">All (<?= array_sum($counts) ?>)</a>
  <?php foreach (['pending' => 'Pending', 'approved' => 'Live', 'reserved' => 'Reserved', 'sold' => 'Sold', 'rejected' => 'Rejected', 'draft' => 'Draft'] as $k => $label): ?>
    <a class="chip" href="<?= e(base('admin/vehicles.php?status=' . $k)) ?>"><?= e($label) ?> (<?= $counts[$k] ?? 0 ?>)</a>
  <?php endforeach; ?>
</div>
<div class="table-wrap"><table class="data">
  <thead><tr><th>ID</th><th>Vehicle</th><th>Seller</th><th>City</th><th>Price</th><th>Flags</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No vehicles in this view.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e(refCode('LST', (int) $r['id'])) ?></td>
      <td><b><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></b><div class="muted num" style="font-size:12px"><?= number_format((int) $r['km_driven']) ?> km &middot; score <?= (int) $r['inspection_score'] ?>/100</div></td>
      <td><?= e((string) $r['seller']) ?></td>
      <td><?= e((string) $r['city']) ?></td>
      <td class="num"><?= rupees($r['price']) ?></td>
      <td><small><?= ((int) $r['certified'] ? 'Certified' : 'Not certified') . ((int) $r['featured'] ? ' + Featured' : '') ?></small></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input class="form-control num" style="width:110px;padding:6px 8px" type="number" name="price" value="<?= (int) $r['price'] ?>">
          <select class="form-select" style="padding:6px 8px" name="status">
            <?php foreach (['draft', 'pending', 'approved', 'rejected', 'reserved', 'sold'] as $s): ?>
              <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
          </select>
          <label style="font-size:12px"><input type="checkbox" name="featured" value="1" <?= (int) $r['featured'] ? 'checked' : '' ?>> Feat</label>
          <label style="font-size:12px"><input type="checkbox" name="certified" value="1" <?= (int) $r['certified'] ? 'checked' : '' ?>> Cert</label>
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
