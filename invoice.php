<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$id = (int) ($_GET['id'] ?? 0);
$order = fetchOne('SELECT o.*, v.make, v.model, v.variant, v.year, v.reg_number,
    s.name AS seller_name, s.company AS seller_company, s.city AS seller_city
    FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id
    JOIN users s ON s.id = l.seller_id
    WHERE o.id = ? AND (o.buyer_id = ? OR l.seller_id = ?' . ($u['role'] === 'admin' ? ' OR 1 = 1' : '') . ')',
    [$id, $u['id'], $u['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect(base('orders.php')); }
$payments = fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$id]);
$docFee = 4999;
$tcs = ((float) $order['amount'] >= 1000000) ? (float) $order['amount'] * 0.01 : 0;
$total = (float) $order['amount'] + $docFee + $tcs;
$paid = 0.0;
foreach ($payments as $p) { if ($p['status'] === 'paid') { $paid += (float) $p['amount']; } }

renderHeader('Invoice ' . $order['order_no'], '');
?>
<div class="wrap section" style="max-width:760px">
  <div class="card card-pad" id="invoice">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
      <div><h1 style="font-size:1.5rem;margin:0">DRIVE24</h1><small class="muted">Tax invoice (facilitation)</small></div>
      <div style="text-align:right"><b class="num"><?= e($order['order_no']) ?></b><br><small class="muted num"><?= e(date('d M Y', strtotime((string) $order['created_at']))) ?></small></div>
    </div>
    <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px"><small class="muted">Billed to</small><br><b><?= e($u['name']) ?></b><br><?= e((string) ($u['mobile'] ?? '')) ?><br><?= e((string) $order['delivery_address']) ?>, <?= e((string) $order['delivery_city']) ?></div>
      <div style="flex:1;min-width:200px"><small class="muted">Seller</small><br><b><?= e((string) ($order['seller_company'] ?: $order['seller_name'])) ?></b><br><?= e((string) ($order['seller_city'] ?? '')) ?></div>
    </div>
    <div class="table-wrap" style="margin-top:14px"><table class="data">
      <thead><tr><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
      <tbody>
        <tr><td><?= e($order['year'] . ' ' . $order['make'] . ' ' . $order['model'] . ' ' . $order['variant']) ?> <span class="muted">(<?= e((string) $order['reg_number']) ?>)</span></td><td class="num" style="text-align:right"><?= rupees($order['amount']) ?></td></tr>
        <tr><td>RC transfer + documentation facilitation</td><td class="num" style="text-align:right"><?= rupees($docFee) ?></td></tr>
        <?php if ($tcs > 0): ?><tr><td>TCS @ 1% (cars above &#8377;10 lakh)</td><td class="num" style="text-align:right"><?= rupees($tcs) ?></td></tr><?php endif; ?>
        <tr><td><b>Total</b></td><td class="num" style="text-align:right"><b><?= rupees($total) ?></b></td></tr>
        <tr><td>Paid till date</td><td class="num" style="text-align:right"><?= rupees($paid) ?></td></tr>
        <tr><td><b>Balance</b></td><td class="num" style="text-align:right"><b><?= rupees(max(0, $total - $paid)) ?></b></td></tr>
      </tbody></table></div>
    <h3 style="font-size:1rem;margin-top:14px">Payments received</h3>
    <?php if (!$payments): ?><p class="muted">No payments recorded yet.</p><?php endif; ?>
    <?php foreach ($payments as $p): ?>
      <div class="kv"><span class="num"><?= e($p['txn_ref']) ?> (<?= e(strtoupper($p['method'])) ?>)</span><span class="num"><?= rupees($p['amount']) ?> &middot; <?= statusBadge((string) $p['status']) ?></span></div>
    <?php endforeach; ?>
    <p class="muted" style="font-size:12.5px;margin-top:12px">This is a system-generated invoice for the DRIVE24 facilitation service. The sale deed and Form 29/30 are issued along with delivery. 7-day easy return applies.</p>
    <div style="display:flex;gap:8px;margin-top:8px"><button class="btn btn-dark btn-sm" type="button" onclick="window.print()">Print / save PDF</button>
      <a class="btn btn-ghost btn-sm" href="<?= e(base('order.php?id=' . $id)) ?>">Back to order</a></div>
  </div>
</div>
<?php renderFooter(); ?>
