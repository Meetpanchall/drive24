<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['listing_id'] ?? 0);
    $owned = fetchOne('SELECT id FROM listings WHERE id = ? AND seller_id = ?', [$id, $u['id']]);
    if ($owned) {
        if (($_POST['action'] ?? '') === 'price') {
            $oldPrice = (float) fetchValue('SELECT price FROM listings WHERE id = ?', [$id], 0);
            $newPrice = (float) $_POST['price'];
            updateRow('listings', ['price' => $newPrice], 'id = ?', [$id]);
            if ($oldPrice > 0 && $newPrice < $oldPrice) {
                $car = findListing($id);
                foreach (fetchAll('SELECT user_id FROM wishlists WHERE listing_id = ?', [$id]) as $w) {
                    notify((int) $w['user_id'], 'Price dropped', vehicleTitle($car ?? []) . ' is now ' . rupees($newPrice), 'car.php?id=' . $id);
                }
            }
            flash('success', 'Price updated.');
        } elseif (($_POST['action'] ?? '') === 'withdraw') {
            updateRow('listings', ['status' => 'draft'], 'id = ?', [$id]);
            flash('success', 'Listing moved to draft.');
        } elseif (($_POST['action'] ?? '') === 'publish') {
            updateRow('listings', ['status' => 'pending'], 'id = ?', [$id]);
            flash('success', 'Listing sent for admin approval.');
        } elseif (($_POST['action'] ?? '') === 'auction') {
            $ends = trim((string) ($_POST['auction_ends_at'] ?? ''));
            $sb = (float) ($_POST['starting_bid'] ?? 0);
            if ($ends === '' || $sb <= 0 || strtotime($ends) === false) {
                flash('error', 'Set a valid end date and a starting bid.');
            } else {
                updateRow('listings', ['auction_enabled' => 1, 'auction_ends_at' => date('Y-m-d H:i:s', strtotime($ends)), 'starting_bid' => $sb], 'id = ?', [$id]);
                flash('success', 'Auction enabled - buyers can bid till ' . date('d M, h:i A', strtotime($ends)) . '.');
            }
        } elseif (($_POST['action'] ?? '') === 'auction_off') {
            updateRow('listings', ['auction_enabled' => 0], 'id = ?', [$id]);
            flash('success', 'Auction disabled.');
        }
    }
    redirect(base('seller/listings.php'));
}

$rows = fetchAll('SELECT l.*, v.make, v.model, v.year, v.km_driven, v.city FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.seller_id = ? ORDER BY l.created_at DESC', [$u['id']]);
adminHeader('My listings', 'listings', 'seller');
?>
<div class="table-wrap"><table class="data">
  <thead><tr><th>ID</th><th>Car</th><th>City</th><th>KM</th><th>Price</th><th>Status</th><th>Auction</th><th>Update price</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9" class="empty">You have no listings. <a href="<?= e(base('sell.php')) ?>">Create one</a>.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e(refCode('LST', (int) $r['id'])) ?></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td><?= e((string) $r['city']) ?></td>
      <td class="num"><?= number_format((int) $r['km_driven']) ?></td>
      <td class="num"><?= rupees($r['price']) ?></td>
      <td><?= statusBadge((string) $r['status']) ?><?= $r['rejection_note'] ? '<div class="muted" style="font-size:12px">' . e((string) $r['rejection_note']) . '</div>' : '' ?></td>
      <td>
        <?php if ((int) $r['auction_enabled'] === 1): ?>
          <span class="badge warn">Live</span>
          <div class="muted num" style="font-size:12px">ends <?= e(date('d M, h:i A', strtotime((string) $r['auction_ends_at']))) ?><br>from <?= rupees($r['starting_bid']) ?></div>
          <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="auction_off"><input type="hidden" name="listing_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-ghost btn-sm">Stop</button></form>
        <?php else: ?>
          <form method="post" style="display:flex;gap:4px;flex-wrap:wrap"><?= csrfField() ?>
            <input type="hidden" name="action" value="auction"><input type="hidden" name="listing_id" value="<?= (int) $r['id'] ?>">
            <input class="form-control num" style="width:150px" type="datetime-local" name="auction_ends_at" value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days'))) ?>" title="Auction ends at">
            <input class="form-control num" style="width:110px" type="number" name="starting_bid" value="<?= (int) ((float) $r['price'] * 0.9) ?>" title="Starting bid">
            <button class="btn btn-outline btn-sm">Auction</button>
          </form>
        <?php endif; ?>
      </td>
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
