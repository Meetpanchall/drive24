<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'suspended'], true) ? (string) $_POST['status'] : 'active';
    updateRow('users', ['status' => $status], 'id = ?', [$id]);
    flash('success', 'Seller #' . $id . ' is now ' . $status . '.');
    redirect(base('admin/sellers.php'));
}

$rows = fetchAll("SELECT u.*,
    (SELECT COUNT(*) FROM listings l WHERE l.seller_id = u.id) AS listings,
    (SELECT COUNT(*) FROM listings l WHERE l.seller_id = u.id AND l.status = 'approved') AS live,
    (SELECT COUNT(*) FROM listings l WHERE l.seller_id = u.id AND l.status = 'sold') AS sold,
    (SELECT COALESCE(SUM(p.amount),0) FROM payouts p WHERE p.seller_id = u.id) AS payouts,
    (SELECT COUNT(*) FROM offers o JOIN listings l ON l.id = o.listing_id WHERE l.seller_id = u.id) AS offers,
    (SELECT COUNT(*) FROM offers o JOIN listings l ON l.id = o.listing_id WHERE l.seller_id = u.id AND o.status <> 'new') AS offers_ok
    FROM users u WHERE u.role IN ('seller','dealer') ORDER BY listings DESC");

adminHeader('Sellers and dealers', 'sellers');
?>
<div class="kpis">
  <div class="kpi"><small>Partners</small><b class="num"><?= count($rows) ?></b></div>
  <div class="kpi"><small>Dealers</small><b class="num"><?= count(array_filter($rows, static fn ($r) => $r['role'] === 'dealer')) ?></b></div>
  <div class="kpi"><small>Payouts released</small><b class="num"><?= rupees(array_sum(array_map(static fn ($r) => (float) $r['payouts'], $rows))) ?></b></div>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Partner</th><th>Type</th><th>Dealership</th><th>Listings</th><th>Live</th><th>Sold</th><th>Response</th><th>Payouts</th><th>KYC</th><th>Status</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="11" class="empty">No sellers registered.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr><td class="num"><?= (int) $r['id'] ?></td>
      <td><b><?= e((string) $r['name']) ?></b><div class="muted" style="font-size:12.4px"><?= e((string) $r['email']) ?></div></td>
      <td><?= e(ucfirst((string) $r['role'])) ?></td>
      <td><?= e((string) ($r['company'] ?? '-')) ?></td>
      <td class="num"><?= (int) $r['listings'] ?></td><td class="num"><?= (int) $r['live'] ?></td><td class="num"><?= (int) $r['sold'] ?></td><td class="num"><?= (int) $r['offers'] ? (int) round(((int) $r['offers_ok']) * 100 / ((int) $r['offers'])) . '%' : '-' ?></td>
      <td class="num"><?= rupees($r['payouts']) ?></td>
      <td><?= statusBadge((string) $r['kyc_status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px">
        <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <option value="active" <?= $r['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= $r['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select><button class="btn btn-dark btn-sm" type="submit">Save</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
