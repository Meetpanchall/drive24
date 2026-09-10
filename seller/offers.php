<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $offerId = (int) ($_POST['offer_id'] ?? 0);
    $offer = fetchOne('SELECT o.*, l.seller_id FROM offers o JOIN listings l ON l.id = o.listing_id WHERE o.id = ?', [$offerId]);
    if ($offer && ((int) $offer['seller_id'] === (int) $u['id'] || $u['role'] === 'admin')) {
        $action = $_POST['action'] ?? '';
        if ($action === 'accept') {
            updateRow('offers', ['status' => 'accepted'], 'id = ?', [$offerId]);
            updateRow('listings', ['price' => (float) $offer['amount']], 'id = ?', [(int) $offer['listing_id']]);
            flash('success', 'Offer accepted and listing price updated.');
        } elseif ($action === 'reject') {
            updateRow('offers', ['status' => 'rejected'], 'id = ?', [$offerId]);
            flash('success', 'Offer rejected.');
        } elseif ($action === 'counter') {
            updateRow('offers', ['status' => 'countered', 'counter_amount' => (float) ($_POST['counter'] ?? 0)], 'id = ?', [$offerId]);
            flash('success', 'Counter offer sent to the buyer.');
        }
    }
    redirect(base('seller/offers.php'));
}

$rows = fetchAll('SELECT o.*, v.make, v.model, v.year, l.price AS listed_price, u.name AS buyer, u.mobile
    FROM offers o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = o.buyer_id
    WHERE l.seller_id = ? ORDER BY o.created_at DESC', [$u['id']]);
adminHeader('Offers received', 'offers', 'seller');
?>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Buyer</th><th>Car</th><th>Listed</th><th>Offer</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">No offers received yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['buyer']) ?></b><div class="muted num" style="font-size:12px"><?= e((string) $r['mobile']) ?></div></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td class="num"><?= rupees($r['listed_price']) ?></td>
      <td class="num"><b><?= rupees($r['amount']) ?></b><?= $r['counter_amount'] ? '<div class="muted" style="font-size:12px">counter ' . rupees($r['counter_amount']) . '</div>' : '' ?></td>
      <td class="muted"><?= e((string) $r['message']) ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <form method="post"><?= csrfField() ?><input type="hidden" name="offer_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="accept"><button class="btn btn-ok btn-sm">Accept</button></form>
          <form method="post"><?= csrfField() ?><input type="hidden" name="offer_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-outline btn-sm">Reject</button></form>
          <form method="post" style="display:flex;gap:6px"><?= csrfField() ?><input type="hidden" name="offer_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="counter">
            <input class="form-control num" style="width:118px" type="number" name="counter" value="<?= (int) $r['listed_price'] ?>"><button class="btn btn-dark btn-sm">Counter</button></form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
