<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['pending', 'processing', 'paid', 'failed'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, $allowed, true)) {
        $data = ['status' => $status];
        if (isset($_POST['utr']) && $_POST['utr'] !== '') { $data['utr'] = trim((string) $_POST['utr']); }
        updateRow('payouts', $data, 'id = ?', [$id]);
        logActivity((int) $admin['id'], 'payouts.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
    }
    redirect(base('admin/payouts.php'));
}

$rows = fetchAll("SELECT p.*, u.name AS seller, u.email, o.order_no FROM payouts p JOIN users u ON u.id = p.seller_id LEFT JOIN orders o ON o.id = p.order_id ORDER BY p.id DESC");
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Seller payouts', 'payouts');
?>
<p class="muted">Release bank transfers to sellers once delivery and RC transfer are cleared.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Seller</th><th>Order</th><th>Amount</th><th>UTR</th><th>Created</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((int) $r['id'])) ?></td>
      <td><?= e((string) ($r['seller'] . ' - ' . (string) $r['email'])) ?></td>
      <td class="num"><?= e((string) ((string) ($r['order_no'] ?? '-'))) ?></td>
      <td class="num"><?= e((string) (rupees($r['amount']))) ?></td>
      <td class="num"><?= e((string) ((string) ($r['utr'] ?? '-'))) ?></td>
      <td class="num"><?= e((string) (date('d M Y', strtotime((string) $r['created_at'])))) ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input class="form-control" style="width:120px;padding:6px 8px" type="text" name="utr" placeholder="Bank UTR" value="<?= e((string) ($r['utr'] ?? '')) ?>">
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
