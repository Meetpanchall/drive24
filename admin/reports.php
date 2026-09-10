<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-180 days')));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));

if (($_GET['export'] ?? '') === 'sales' && dbReady()) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="drive24-sales-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Date', 'Car', 'Buyer', 'City', 'Amount', 'Status']);
    foreach (fetchAll("SELECT o.order_no, o.created_at, CONCAT(v.year,' ',v.make,' ',v.model) AS car, u.name AS buyer, o.delivery_city, o.amount, o.status
        FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = o.buyer_id
        WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.id", [$from, $to]) as $row) {
        fputcsv($out, [$row['order_no'], $row['created_at'], $row['car'], $row['buyer'], $row['delivery_city'], $row['amount'], $row['status']]);
    }
    fclose($out);
    exit;
}

$sales = fetchAll("SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS label, COUNT(*) AS orders, COALESCE(SUM(o.amount),0) AS revenue
    FROM orders o WHERE DATE(o.created_at) BETWEEN ? AND ? GROUP BY label ORDER BY label", [$from, $to]);
$topMakes = fetchAll("SELECT v.make AS label, COUNT(*) AS listings, COALESCE(AVG(l.price),0) AS avg_price
    FROM listings l JOIN vehicles v ON v.id = l.vehicle_id GROUP BY v.make ORDER BY listings DESC LIMIT 8");
$funnel = [
    'Listings created' => (int) fetchValue('SELECT COUNT(*) FROM listings', [], 0),
    'Approved live'    => (int) fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'approved'", [], 0),
    'Test drives'      => (int) fetchValue('SELECT COUNT(*) FROM test_drives', [], 0),
    'Offers'           => (int) fetchValue('SELECT COUNT(*) FROM offers', [], 0),
    'Orders'           => (int) fetchValue('SELECT COUNT(*) FROM orders', [], 0),
    'Delivered'        => (int) fetchValue("SELECT COUNT(*) FROM orders WHERE status = 'delivered'", [], 0),
];
$maxRevenue = 1.0;
foreach ($sales as $s) { $maxRevenue = max($maxRevenue, (float) $s['revenue']); }
$maxFunnel = max(1, ...array_values($funnel));
$byCity = fetchAll("SELECT o.delivery_city AS label, COUNT(*) AS orders, COALESCE(SUM(o.amount),0) AS revenue
    FROM orders o WHERE DATE(o.created_at) BETWEEN ? AND ? GROUP BY o.delivery_city ORDER BY revenue DESC LIMIT 8", [$from, $to]);
$topSellers = fetchAll("SELECT COALESCE(u.company, u.name) AS label, COUNT(DISTINCT l.id) AS listings,
    SUM(l.status = 'sold') AS sold, COALESCE(SUM(o.amount),0) AS revenue
    FROM users u LEFT JOIN listings l ON l.seller_id = u.id LEFT JOIN orders o ON o.listing_id = l.id
    WHERE u.role IN ('seller','dealer') GROUP BY u.id ORDER BY revenue DESC LIMIT 8", []);

adminHeader('Analytics and reports', 'reports');
?>
<form method="get" class="card card-pad" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
  <div><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
  <div><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary" type="submit">Run report</button>
  <a class="btn btn-outline" href="<?= e(base('admin/reports.php?from=' . $from . '&to=' . $to . '&export=sales')) ?>">Export sales CSV</a>
</form>

<div class="split" style="margin-top:18px">
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Revenue by month</h2>
    <div class="bars">
      <?php if (!$sales): ?><div class="empty">No orders in this range.</div><?php endif; ?>
      <?php foreach ($sales as $s): ?>
        <div class="bar"><span style="height:<?= (int) round(((float) $s['revenue'] / $maxRevenue) * 100) ?>%"></span>
          <small><?= e((string) $s['label']) ?></small><b class="num"><?= rupees($s['revenue']) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Marketplace funnel</h2>
    <?php foreach ($funnel as $label => $value): ?>
      <div style="margin-bottom:10px">
        <div class="kv" style="border:0;padding:0 0 4px"><span><?= e($label) ?></span><b class="num"><?= (int) $value ?></b></div>
        <div style="height:8px;border-radius:99px;background:var(--surface-low)"><div style="height:8px;border-radius:99px;background:var(--primary);width:<?= (int) round(($value / $maxFunnel) * 100) ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="split" style="margin-top:22px">
  <div>
    <h2 style="font-size:1.1rem">Sales by city</h2>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>City</th><th>Orders</th><th>Revenue</th></tr></thead>
      <tbody><?php if (!$byCity): ?><tr><td colspan="3" class="empty">No orders in this range.</td></tr><?php endif; ?>
      <?php foreach ($byCity as $c): ?>
        <tr><td><?= e((string) ($c['label'] ?: '-')) ?></td><td class="num"><?= (int) $c['orders'] ?></td><td class="num"><?= rupees($c['revenue']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </div>
  <div>
    <h2 style="font-size:1.1rem">Top sellers</h2>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Seller</th><th>Listings</th><th>Sold</th><th>Revenue</th></tr></thead>
      <tbody><?php if (!$topSellers): ?><tr><td colspan="4" class="empty">No sellers yet.</td></tr><?php endif; ?>
      <?php foreach ($topSellers as $t): ?>
        <tr><td><?= e((string) $t['label']) ?></td><td class="num"><?= (int) $t['listings'] ?></td><td class="num"><?= (int) $t['sold'] ?></td><td class="num"><?= rupees($t['revenue']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </div>
</div>

<h2 style="font-size:1.1rem;margin-top:22px">Brand performance</h2>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Brand</th><th>Listings</th><th>Average price</th></tr></thead>
  <tbody><?php foreach ($topMakes as $m): ?>
    <tr><td><?= e((string) $m['label']) ?></td><td class="num"><?= (int) $m['listings'] ?></td><td class="num"><?= rupees($m['avg_price']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php adminFooter(); ?>
