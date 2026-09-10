<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'suspended'], true) ? (string) $_POST['status'] : 'active';
    updateRow('users', ['status' => $status], 'id = ? AND role = ?', [$id, 'buyer']);
    logActivity((int) $admin['id'], 'customer.status', '#' . $id . ' -> ' . $status);
    flash('success', 'Customer #' . $id . ' is now ' . $status . '.');
    redirect(base('admin/customers.php'));
}

$rows = fetchAll("SELECT u.*,
    (SELECT COUNT(*) FROM orders o WHERE o.buyer_id = u.id) AS orders,
    (SELECT COALESCE(SUM(o.amount),0) FROM orders o WHERE o.buyer_id = u.id) AS spend,
    (SELECT COUNT(*) FROM wishlists w WHERE w.user_id = u.id) AS saved
    FROM users u WHERE u.role = 'buyer' ORDER BY spend DESC, u.id DESC");

adminHeader('Customer management', 'customers');
?>
<div class="kpis">
  <div class="kpi"><small>Customers</small><b class="num"><?= count($rows) ?></b></div>
  <div class="kpi"><small>With orders</small><b class="num"><?= count(array_filter($rows, static fn ($r) => (int) $r['orders'] > 0)) ?></b></div>
  <div class="kpi"><small>Lifetime value</small><b class="num"><?= rupees(array_sum(array_map(static fn ($r) => (float) $r['spend'], $rows))) ?></b></div>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Customer</th><th>City</th><th>Orders</th><th>Spend</th><th>Saved cars</th><th>KYC</th><th>Status</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No customers yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr><td class="num"><?= (int) $r['id'] ?></td>
      <td><b><?= e((string) $r['name']) ?></b><div class="muted" style="font-size:12.4px"><?= e((string) $r['email']) ?> &middot; <?= e((string) ($r['mobile'] ?? '')) ?></div></td>
      <td><?= e((string) ($r['city'] ?? '-')) ?></td>
      <td class="num"><?= (int) $r['orders'] ?></td>
      <td class="num"><?= rupees($r['spend']) ?></td>
      <td class="num"><?= (int) $r['saved'] ?></td>
      <td><?= statusBadge((string) $r['kyc_status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px">
        <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <option value="active" <?= $r['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= $r['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select><button class="btn btn-dark btn-sm" type="submit">Save</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
