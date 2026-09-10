<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['pending', 'paid', 'failed', 'refunded'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
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
<p class="muted">Settlement ledger for every booking. Marking a payment refunded is recorded against the same order.</p>
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
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
