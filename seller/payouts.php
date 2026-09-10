<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');
$rows = fetchAll('SELECT p.*, o.order_no FROM payouts p LEFT JOIN orders o ON o.id = p.order_id WHERE p.seller_id = ? ORDER BY p.created_at DESC', [$u['id']]);
$paid = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE seller_id = ? AND status = 'paid'", [$u['id']]);
$pending = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE seller_id = ? AND status <> 'paid'", [$u['id']]);
adminHeader('Payouts', 'payouts', 'seller');
?>
<div class="kpis">
  <div class="kpi"><small>Settled</small><b class="num"><?= rupees($paid) ?></b></div>
  <div class="kpi"><small>In progress</small><b class="num"><?= rupees($pending) ?></b></div>
  <div class="kpi"><small>Payout cycle</small><b>T+2 working days</b></div>
</div>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Payout</th><th>Order</th><th>Amount</th><th>UTR</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="6" class="empty">No payouts yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr><td class="num"><?= e(refCode('PO', (int) $r['id'])) ?></td><td class="num"><?= e((string) $r['order_no']) ?></td>
      <td class="num"><?= rupees($r['amount']) ?></td><td class="num"><?= e((string) ($r['utr'] ?? '-')) ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td><td class="num"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
