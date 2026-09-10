<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$kpi = [
    'Live listings'   => (int) fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'approved'", [], 0),
    'Pending approval'=> (int) fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'pending'", [], 0),
    'Orders'          => (int) fetchValue('SELECT COUNT(*) FROM orders', [], 0),
    'Revenue'         => (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status NOT IN ('cancelled','returned')", [], 0),
    'Test drives'     => (int) fetchValue("SELECT COUNT(*) FROM test_drives WHERE status = 'requested'", [], 0),
    'Open tickets'    => (int) fetchValue("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'", [], 0),
];
$monthly = fetchAll("SELECT DATE_FORMAT(created_at,'%b') AS m, COUNT(*) AS c,
    COALESCE(SUM(amount),0) AS revenue FROM orders GROUP BY DATE_FORMAT(created_at,'%Y-%m'), m
    ORDER BY MIN(created_at) DESC LIMIT 6");
$monthly = array_reverse($monthly);
$max = 1.0;
foreach ($monthly as $m) { $max = max($max, (float) $m['revenue']); }
$queue = fetchAll('SELECT l.id, l.price, l.created_at, v.make, v.model, v.year, u.name AS seller
    FROM listings l JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id
    WHERE l.status = ? ORDER BY l.created_at LIMIT 6', ['pending']);
$recentOrders = fetchAll('SELECT o.order_no, o.amount, o.status, o.created_at, v.make, v.model
    FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id
    ORDER BY o.id DESC LIMIT 6');
$activity = fetchAll('SELECT a.*, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 8');

adminHeader('Operations dashboard', 'dashboard');
?>
<div class="kpis">
  <?php $i = 0; foreach ($kpi as $label => $v): ?>
    <div class="kpi"><small><?= e($label) ?></small><b class="num"><?= $label === 'Revenue' ? rupees($v) : number_format((int) $v) ?></b></div>
  <?php $i++; endforeach; ?>
</div>

<div class="split" style="margin-top:18px">
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Revenue - last 6 months</h2>
    <div class="bars">
      <?php if (!$monthly): ?><div class="empty">No orders yet.</div><?php endif; ?>
      <?php foreach ($monthly as $m): ?>
        <div class="bar"><span style="height:<?= (int) round(((float) $m['revenue'] / $max) * 100) ?>%"></span>
          <small><?= e((string) $m['m']) ?></small><b class="num"><?= money($m['revenue']) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Approval queue <a class="btn btn-ghost btn-sm" style="float:right" href="<?= e(base('admin/approvals.php')) ?>">Open</a></h2>
    <?php if (!$queue): ?><p class="muted">Queue is clear. New seller listings appear here.</p><?php endif; ?>
    <?php foreach ($queue as $r): ?>
      <div class="kv"><span><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?><br><small class="muted"><?= e((string) $r['seller']) ?></small></span>
        <span class="num"><?= rupees($r['price']) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="split" style="margin-top:18px">
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Latest orders</h2>
    <div class="table-wrap" style="border:0"><table class="data">
      <thead><tr><th>Order</th><th>Car</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$recentOrders): ?><tr><td colspan="4" class="empty">No orders yet.</td></tr><?php endif; ?>
      <?php foreach ($recentOrders as $o): ?>
        <tr><td class="num"><?= e($o['order_no']) ?></td><td><?= e($o['make'] . ' ' . $o['model']) ?></td>
          <td class="num"><?= rupees($o['amount']) ?></td><td><?= statusBadge((string) $o['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Recent activity</h2>
    <?php if (!$activity): ?><p class="muted">Actions taken across the console are logged here.</p><?php endif; ?>
    <?php foreach ($activity as $a): ?>
      <div class="kv"><span><b><?= e((string) ($a['name'] ?? 'System')) ?></b> &middot; <?= e($a['action']) ?><br><small class="muted"><?= e((string) ($a['detail'] ?? '')) ?></small></span>
        <small class="muted num"><?= e(date('d M, H:i', strtotime((string) $a['created_at']))) ?></small></div>
    <?php endforeach; ?>
  </div>
</div>
<?php adminFooter(); ?>
