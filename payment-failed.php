<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$orderId = (int) ($_GET['order'] ?? 0);
$order = fetchOne('SELECT o.*, v.make, v.model, v.year FROM orders o JOIN listings l ON l.id = o.listing_id
    JOIN vehicles v ON v.id = l.vehicle_id WHERE o.id = ? AND o.buyer_id = ?', [$orderId, $u['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect(base('orders.php')); }
$attempt = fetchOne('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$orderId]);

renderHeader('Payment failed', '');
?>
<div class="wrap section" style="max-width:640px;text-align:center">
  <div style="font-size:3rem">⚠️</div>
  <h1 style="font-size:1.6rem">Payment didn't go through</h1>
  <p class="muted">No money was charged. Your car is still available - pick up right where you left off.</p>
  <div class="card card-pad" style="text-align:left;margin:18px 0">
    <div class="kv"><span>Car</span><b><?= e($order['year'] . ' ' . $order['make'] . ' ' . $order['model']) ?></b></div>
    <div class="kv"><span>Order</span><span class="num"><?= e($order['order_no']) ?></span></div>
    <div class="kv"><span>Booking amount</span><span class="num"><?= rupees($order['booking_amount']) ?></span></div>
    <?php if ($attempt): ?><div class="kv"><span>Last attempt</span><span><?= statusBadge((string) $attempt['status']) ?> <small class="muted num"><?= e(date('d M, h:i A', strtotime((string) $attempt['created_at']))) ?></small></span></div><?php endif; ?>
  </div>
  <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= e(base('pay.php?order=' . $orderId)) ?>">Retry payment</a>
    <a class="btn btn-outline" href="<?= e(base('order.php?id=' . $orderId)) ?>">Order details</a>
    <a class="btn btn-ghost" href="<?= e(base('contact.php')) ?>">Contact support</a>
  </div>
  <p class="muted" style="margin-top:16px;font-size:13px">Common fixes: check your UPI limit, use a different card, or wait a few minutes before retrying.</p>
</div>
<?php renderFooter(); ?>
