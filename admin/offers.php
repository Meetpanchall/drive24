<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['pending', 'countered', 'accepted', 'rejected', 'expired'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, $allowed, true)) {
        $data = ['status' => $status];
        if (isset($_POST['counter_amount']) && $_POST['counter_amount'] !== '') { $data['counter_amount'] = (float) $_POST['counter_amount']; }
        updateRow('offers', $data, 'id = ?', [$id]);
        logActivity((int) $admin['id'], 'offers.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
    }
    redirect(base('admin/offers.php'));
}

$rows = fetchAll("SELECT o.*, v.make, v.model, v.year, l.price, u.name AS buyer FROM offers o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = o.buyer_id ORDER BY o.id DESC");
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Offer resolution', 'offers');
?>
<p class="muted">Negotiations between buyers and sellers. Admin can settle a final price when a deal stalls.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Vehicle</th><th>Buyer</th><th>Listed</th><th>Offered</th><th>Counter</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((int) $r['id'])) ?></td>
      <td><?= e((string) ($r['year'] . ' ' . $r['make'] . ' ' . $r['model'])) ?></td>
      <td><?= e((string) ((string) $r['buyer'])) ?></td>
      <td class="num"><?= e((string) (rupees($r['price']))) ?></td>
      <td class="num"><?= e((string) (rupees($r['amount']))) ?></td>
      <td class="num"><?= e((string) ($r['counter_amount'] ? rupees($r['counter_amount']) : '-')) ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input class="form-control num" style="width:120px;padding:6px 8px" type="number" name="counter_amount" placeholder="Final / counter price" value="<?= e((string) ($r['counter_amount'] ?? '')) ?>">
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
