<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

$kpi = [
    'listings' => (int) fetchValue('SELECT COUNT(*) FROM listings WHERE seller_id = ?', [$u['id']]),
    'live'     => (int) fetchValue("SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'approved'", [$u['id']]),
    'offers'   => (int) fetchValue('SELECT COUNT(*) FROM offers o JOIN listings l ON l.id = o.listing_id WHERE l.seller_id = ?', [$u['id']]),
    'earnings' => (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE seller_id = ? AND status = 'paid'", [$u['id']]),
    'views'    => (int) fetchValue('SELECT COALESCE(SUM(views),0) FROM listings WHERE seller_id = ?', [$u['id']]),
];
$recent = fetchAll('SELECT l.id, l.price, l.status, l.views, v.make, v.model, v.year FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.seller_id = ? ORDER BY l.created_at DESC LIMIT 6', [$u['id']]);
$offers = fetchAll('SELECT o.*, v.make, v.model, u.name AS buyer FROM offers o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = o.buyer_id WHERE l.seller_id = ? ORDER BY o.created_at DESC LIMIT 6', [$u['id']]);
$monthly = fetchAll("SELECT DATE_FORMAT(created_at,'%b') AS m, COUNT(*) AS c FROM listings WHERE seller_id = ? GROUP BY DATE_FORMAT(created_at,'%Y-%m'), m ORDER BY MIN(created_at) LIMIT 6", [$u['id']]);

adminHeader('Seller dashboard', 'dashboard', 'seller');
?>
<div class="kpis">
  <div class="kpi"><small>Total listings</small><b class="num"><?= $kpi['listings'] ?></b></div>
  <div class="kpi"><small>Live now</small><b class="num"><?= $kpi['live'] ?></b></div>
  <div class="kpi"><small>Offers received</small><b class="num"><?= $kpi['offers'] ?></b></div>
  <div class="kpi"><small>Total views</small><b class="num"><?= number_format($kpi['views']) ?></b></div>
  <div class="kpi"><small>Paid earnings</small><b class="num"><?= rupees($kpi['earnings']) ?></b></div>
</div>
<div class="split-3">
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">My latest listings</h2>
    <div class="table-wrap" style="border:0"><table class="data">
      <thead><tr><th>Car</th><th>Price</th><th>Views</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="4" class="empty">No listings yet. <a href="<?= e(base('sell.php')) ?>">Add your first car</a>.</td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <tr><td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td><td class="num"><?= rupees($r['price']) ?></td>
          <td class="num"><?= (int) $r['views'] ?></td><td><?= statusBadge((string) $r['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Latest offers</h2>
    <?php if (!$offers): ?><p class="muted">No offers yet.</p><?php endif; ?>
    <?php foreach ($offers as $o): ?>
      <div class="kv"><span><?= e($o['buyer']) ?> &middot; <?= e($o['make'] . ' ' . $o['model']) ?></span>
        <span class="num"><?= rupees($o['amount']) ?> <?= statusBadge((string) $o['status']) ?></span></div>
    <?php endforeach; ?>
    <a class="btn btn-primary btn-block btn-sm" style="margin-top:12px" href="<?= e(base('seller/offers.php')) ?>">Manage offers</a>
  </div>
</div>
<?php adminFooter(); ?>
