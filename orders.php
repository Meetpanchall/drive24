<?php
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$filter = (string) ($_GET['status'] ?? '');
$sql = 'SELECT o.*, v.make, v.model, v.year, v.image FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE o.buyer_id = ?';
$params = [$u['id']];
if ($filter !== '') { $sql .= ' AND o.status = ?'; $params[] = $filter; }
$orders = fetchAll($sql . ' ORDER BY o.created_at DESC', $params);

renderHeader('My orders', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">My orders</h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 18px">
    <?php foreach (['' => 'All', 'confirmed' => 'Active', 'processing' => 'Processing', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'] as $val => $label): ?>
      <a class="chip <?= $filter === $val ? 'active' : '' ?>" href="<?= e(base('orders.php' . ($val ? '?status=' . $val : ''))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$orders): ?><div class="card card-pad empty">No orders in this view.</div><?php endif; ?>
  <div class="grid" style="grid-template-columns:1fr">
    <?php foreach ($orders as $o): ?>
      <div class="card card-pad" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <img src="<?= e(listingImage($o)) ?>" alt="" style="width:150px;height:94px;object-fit:cover;border-radius:10px">
        <div style="flex:1;min-width:190px">
          <b><?= e($o['year'] . ' ' . $o['make'] . ' ' . $o['model']) ?></b>
          <div class="muted num" style="font-size:13px">Order <?= e($o['order_no']) ?> &middot; placed <?= e(date('d M Y', strtotime((string) $o['created_at']))) ?></div>
        </div>
        <div class="num" style="font-weight:700"><?= rupees($o['amount']) ?></div>
        <div><?= statusBadge((string) $o['status']) ?></div>
        <a class="btn btn-outline btn-sm" href="<?= e(base('order.php?id=' . (int) $o['id'])) ?>">Details</a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php renderFooter(); ?>
