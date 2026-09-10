<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['listing_id'] ?? 0);
    $owned = fetchOne('SELECT id FROM listings WHERE id = ? AND seller_id = ?', [$id, $u['id']]);
    if ($owned) {
        if (($_POST['action'] ?? '') === 'price') {
            updateRow('listings', ['price' => (float) $_POST['price']], 'id = ?', [$id]);
            flash('success', 'Price updated.');
        } elseif (($_POST['action'] ?? '') === 'withdraw') {
            updateRow('listings', ['status' => 'draft'], 'id = ?', [$id]);
            flash('success', 'Listing moved to draft.');
        } elseif (($_POST['action'] ?? '') === 'publish') {
            updateRow('listings', ['status' => 'pending'], 'id = ?', [$id]);
            flash('success', 'Listing sent for admin approval.');
        }
    }
    redirect(base('seller/listings.php'));
}

$rows = fetchAll('SELECT l.*, v.make, v.model, v.year, v.km_driven, v.city FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.seller_id = ? ORDER BY l.created_at DESC', [$u['id']]);
adminHeader('My listings', 'listings', 'seller');
?>
<div class="table-wrap"><table class="data">
  <thead><tr><th>ID</th><th>Car</th><th>City</th><th>KM</th><th>Price</th><th>Status</th><th>Update price</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">You have no listings. <a href="<?= e(base('sell.php')) ?>">Create one</a>.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e(refCode('LST', (int) $r['id'])) ?></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td><?= e((string) $r['city']) ?></td>
      <td class="num"><?= number_format((int) $r['km_driven']) ?></td>
      <td class="num"><?= rupees($r['price']) ?></td>
      <td><?= statusBadge((string) $r['status']) ?><?= $r['rejection_note'] ? '<div class="muted" style="font-size:12px">' . e((string) $r['rejection_note']) . '</div>' : '' ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px"><?= csrfField() ?>
          <input type="hidden" name="action" value="price"><input type="hidden" name="listing_id" value="<?= (int) $r['id'] ?>">
          <input class="form-control num" style="width:120px" type="number" name="price" value="<?= (int) $r['price'] ?>">
          <button class="btn btn-outline btn-sm">Save</button>
        </form>
      </td>
      <td>
        <form method="post"><?= csrfField() ?>
          <input type="hidden" name="listing_id" value="<?= (int) $r['id'] ?>">
          <input type="hidden" name="action" value="<?= $r['status'] === 'draft' ? 'publish' : 'withdraw' ?>">
          <button class="btn btn-<?= $r['status'] === 'draft' ? 'primary' : 'ghost' ?> btn-sm"><?= $r['status'] === 'draft' ? 'Publish' : 'Withdraw' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
