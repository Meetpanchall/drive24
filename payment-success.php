<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$orderId = (int) ($_GET['order'] ?? 0);
$order = fetchOne('SELECT o.*, v.make, v.model, v.year, v.variant, v.image FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE o.id = ? AND o.buyer_id = ?', [$orderId, $u['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect(base('orders.php')); }
$payment = fetchOne('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$orderId]);

renderHeader('Payment successful', '');
?>
<div class="wrap section" style="max-width:760px">
  <div class="card card-pad" style="text-align:center">
    <div style="width:64px;height:64px;border-radius:50%;background:var(--ok-bg);color:#047857;display:grid;place-items:center;margin:0 auto;font-size:1.8rem">&#10003;</div>
    <h1 style="margin-top:12px">Payment successful</h1>
    <p class="muted">Your booking is confirmed and the car is now reserved for you.</p>
    <div class="card card-pad" style="text-align:left;margin-top:16px;background:var(--surface-low)">
      <div class="kv"><span>Order number</span><span class="num"><?= e($order['order_no']) ?></span></div>
      <div class="kv"><span>Car</span><span><?= e($order['year'] . ' ' . $order['make'] . ' ' . $order['model'] . ' ' . $order['variant']) ?></span></div>
      <div class="kv"><span>Transaction</span><span class="num"><?= e((string) ($payment['txn_ref'] ?? '')) ?> (<?= e((string) ($payment['method'] ?? '')) ?>)</span></div>
      <div class="kv"><span>Paid now</span><span class="num"><?= rupees($order['booking_amount']) ?></span></div>
      <div class="kv"><span>Balance</span><span class="num"><?= rupees((float) $order['amount'] - (float) $order['booking_amount']) ?></span></div>
      <div class="kv"><span>Expected delivery</span><span class="num"><?= e(date('d M Y', strtotime((string) $order['delivery_date']))) ?></span></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:16px;flex-wrap:wrap">
      <a class="btn btn-primary" href="<?= e(base('order.php?id=' . (int) $order['id'])) ?>">Track this order</a>
      <a class="btn btn-outline" href="<?= e(base('cars.php')) ?>">Continue browsing</a>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
