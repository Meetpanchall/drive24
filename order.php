<?php
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$id = (int) ($_GET['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'cancel') {
        q("UPDATE orders SET status = 'cancelled' WHERE id = ? AND buyer_id = ? AND status IN ('pending','confirmed')", [$id, $u['id']]);
        q("UPDATE listings l JOIN orders o ON o.listing_id = l.id SET l.status = 'approved' WHERE o.id = ?", [$id]);
        flash('success', 'Order cancelled and the car was returned to inventory.');
    }
    redirect(base('order.php?id=' . $id));
}

$order = fetchOne('SELECT o.*, v.make, v.model, v.year, v.variant, v.image, v.reg_number, u.name AS seller_name
    FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id
    WHERE o.id = ? AND o.buyer_id = ?', [$id, $u['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect(base('orders.php')); }
$payments = fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$id]);
$docs = fetchAll('SELECT * FROM documents WHERE order_id = ?', [$id]);
$timeline = ['confirmed' => 'Booking confirmed', 'processing' => 'Documentation &amp; RC transfer', 'in_transit' => 'Vehicle dispatched', 'delivered' => 'Delivered'];
$order_status = (string) $order['status'];
$stages = array_keys($timeline);
$currentIndex = array_search($order_status, $stages, true);
$currentIndex = $currentIndex === false ? 0 : (int) $currentIndex;

renderHeader('Order ' . $order['order_no'], '');
?>
<div class="wrap section">
  <h1 style="font-size:1.5rem">Order <?= e($order['order_no']) ?></h1>
  <div class="steps" style="margin-top:14px">
    <?php $i = 0; foreach ($timeline as $key => $label): ?>
      <div class="step <?= $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'active' : '') ?>"><?= ($i + 1) . '. ' . $label ?></div>
    <?php $i++; endforeach; ?>
  </div>
  <div class="split-3">
    <div class="card card-pad">
      <div style="display:flex;gap:16px;flex-wrap:wrap">
        <img src="<?= e(base('assets/img/' . $order['image'])) ?>" alt="" style="width:220px;border-radius:12px">
        <div>
          <h2 style="font-size:1.15rem"><?= e($order['year'] . ' ' . $order['make'] . ' ' . $order['model'] . ' ' . $order['variant']) ?></h2>
          <div class="kv"><span>Registration</span><span class="num"><?= e((string) $order['reg_number']) ?></span></div>
          <div class="kv"><span>Seller</span><span><?= e($order['seller_name']) ?></span></div>
          <div class="kv"><span>Status</span><span><?= statusBadge($order_status) ?></span></div>
        </div>
      </div>
      <h3 style="margin-top:18px;font-size:1.05rem">Payments</h3>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Txn ref</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?>
          <tr><td class="num"><?= e($p['txn_ref']) ?></td><td><?= e(strtoupper($p['method'])) ?></td>
            <td class="num"><?= rupees($p['amount']) ?></td><td><?= statusBadge((string) $p['status']) ?></td>
            <td class="num"><?= e(date('d M Y', strtotime((string) $p['created_at']))) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php if ($docs): ?>
        <h3 style="margin-top:18px;font-size:1.05rem">Documents</h3>
        <?php foreach ($docs as $d): ?><div class="kv"><span><?= e($d['doc_name']) ?></span><span><?= statusBadge((string) $d['status']) ?></span></div><?php endforeach; ?>
      <?php endif; ?>
    </div>
    <aside class="card card-pad sticky">
      <div class="kv"><span>Car price</span><span class="num"><?= rupees($order['amount']) ?></span></div>
      <div class="kv"><span>Booking paid</span><span class="num"><?= rupees($order['booking_amount']) ?></span></div>
      <div class="kv"><span>Finance</span><span class="num"><?= ((int) $order['finance_opted']) ? rupees($order['loan_amount']) . ' / ' . (int) $order['tenure_months'] . 'm' : 'Not opted' ?></span></div>
      <div class="kv"><span>Delivery</span><span class="num"><?= e(date('d M Y', strtotime((string) $order['delivery_date']))) ?></span></div>
      <div class="kv"><span>Address</span><span><?= e((string) $order['delivery_address']) ?></span></div>
      <?php if (in_array($order_status, ['pending', 'confirmed'], true)): ?>
        <form method="post" style="margin-top:12px"><?= csrfField() ?><input type="hidden" name="action" value="cancel">
          <button class="btn btn-outline btn-block btn-sm" type="submit">Cancel order</button></form>
      <?php endif; ?>
      <a class="btn btn-ghost btn-block btn-sm" href="<?= e(base('support.php')) ?>">Need help?</a>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
