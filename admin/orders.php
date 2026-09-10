<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['pending', 'confirmed', 'processing', 'in_transit', 'delivered', 'cancelled', 'returned'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, $allowed, true)) {
        $data = ['status' => $status];
        updateRow('orders', $data, 'id = ?', [$id]);
        logActivity((int) $admin['id'], 'orders.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
    }
    redirect(base('admin/orders.php'));
}

$rows = fetchAll("SELECT o.*, v.make, v.model, v.year, u.name AS buyer FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = o.buyer_id ORDER BY o.id DESC");
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Order lifecycle', 'orders');
?>
<p class="muted">Move orders through documentation, dispatch and delivery. Buyers track the same timeline on their order page.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>Order</th><th>Vehicle</th><th>Buyer</th><th>Amount</th><th>Booking</th><th>City</th><th>Delivery</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((string) $r['order_no'])) ?></td>
      <td><?= e((string) ($r['year'] . ' ' . $r['make'] . ' ' . $r['model'])) ?></td>
      <td><?= e((string) ((string) $r['buyer'])) ?></td>
      <td class="num"><?= e((string) (rupees($r['amount']))) ?></td>
      <td class="num"><?= e((string) (rupees($r['booking_amount']))) ?></td>
      <td><?= e((string) ((string) ($r['delivery_city'] ?? '-'))) ?></td>
      <td class="num"><?= e((string) ($r['delivery_date'] ? date('d M Y', strtotime((string) $r['delivery_date'])) : '-')) ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
