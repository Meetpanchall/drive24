<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/razorpay.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['pending', 'paid', 'failed', 'refunded'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['form'] ?? '') === 'refund' && $id > 0) {
        $pay = fetchOne('SELECT p.*, o.order_no, o.buyer_id FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.id = ?', [$id]);
        if (!$pay) { flash('error', 'Payment not found.'); redirect(base('admin/payments.php')); }
        if (($pay['status'] ?? '') !== 'paid') { flash('error', 'Only paid bookings can be refunded.'); redirect(base('admin/payments.php')); }
        $txn = (string) $pay['txn_ref'];
        $via = 'manual/test ledger';
        if (str_starts_with($txn, 'pay_')) {
            try {
                $rf = razorpayRefund($txn, (int) round(((float) $pay['amount']) * 100));
                $via = 'Razorpay #' . (string) ($rf['id'] ?? $txn);
            } catch (Throwable $ex) {
                flash('error', 'Razorpay refund failed: ' . $ex->getMessage());
                redirect(base('admin/payments.php'));
            }
        }
        updateRow('payments', ['status' => 'refunded'], 'id = ?', [$id]);
        insert('escrow_ledger', ['order_id' => (int) $pay['order_id'], 'kind' => 'refund',
            'amount' => (float) $pay['amount'], 'note' => 'Admin refund (' . $via . ')']);
        notify((int) $pay['buyer_id'], 'Booking refunded', rupees($pay['amount']) . ' for order ' . $pay['order_no'] . ' was refunded to source.', 'orders.php');
        logActivity((int) $admin['id'], 'payments.refunded', '#' . $id . ' via ' . $via);
        flash('success', 'Payment #' . $id . ' refunded (' . $via . ').');
        redirect(base('admin/payments.php'));
    }
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, $allowed, true)) {
        $data = ['status' => $status];
        updateRow('payments', $data, 'id = ?', [$id]);
        logActivity((int) $admin['id'], 'payments.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
    }
    redirect(base('admin/payments.php'));
}

$rows = fetchAll("SELECT p.*, o.order_no, u.name AS buyer FROM payments p JOIN orders o ON o.id = p.order_id JOIN users u ON u.id = o.buyer_id ORDER BY p.id DESC");
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Payments & refunds', 'payments');
?>
<p class="muted">Settlement ledger for every booking. The Refund button pushes money back through Razorpay for live payments (escrow ledger + buyer notified); test-mode rows are recorded as refunded.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>Txn</th><th>Order</th><th>Buyer</th><th>Method</th><th>Amount</th><th>Date</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((string) $r['txn_ref'])) ?></td>
      <td class="num"><?= e((string) ((string) $r['order_no'])) ?></td>
      <td><?= e((string) ((string) $r['buyer'])) ?></td>
      <td><?= e((string) (strtoupper((string) $r['method']))) ?></td>
      <td class="num"><?= e((string) (rupees($r['amount']))) ?></td>
      <td class="num"><?= e((string) (date('d M Y H:i', strtotime((string) $r['created_at'])))) ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select class="form-select" style="padding:6px 8px" name="status">
            <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r[$statusCol] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
        <?php if ($r[$statusCol] === 'paid'): ?>
        <form method="post" style="margin-top:6px" onsubmit="return confirm('Refund <?= e(rupees($r['amount'])) ?> to the buyer?')"><?= csrfField() ?>
          <input type="hidden" name="form" value="refund"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-outline btn-sm" type="submit">Refund<?= str_starts_with((string) $r['txn_ref'], 'pay_') ? ' via Razorpay' : ' (record)' ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
