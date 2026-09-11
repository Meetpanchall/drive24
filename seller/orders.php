<?php
// Seller view of orders for their listings + rating buyers (SRS: sellers rate buyers).
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $ord = fetchOne('SELECT o.*, l.seller_id, l.id AS listing_id FROM orders o JOIN listings l ON l.id = o.listing_id WHERE o.id = ?', [$orderId]);
    // Fulfilment: sellers advance their own paid orders one strict step at a
    // time (confirmed -> processing -> in_transit), which opens the buyer's
    // handover ceremony. No skipping, no touching other sellers' orders.
    $advance = $_POST['advance'] ?? '';
    if ($ord && (int) $ord['seller_id'] === (int) $u['id'] && in_array($advance, ['processing', 'in_transit'], true)) {
        $okStep = ($advance === 'processing' && $ord['status'] === 'confirmed')
            || ($advance === 'in_transit' && $ord['status'] === 'processing');
        if ($okStep) {
            $stmt = q('UPDATE orders SET status = ? WHERE id = ? AND status = ?',
                [$advance, $orderId, $advance === 'processing' ? 'confirmed' : 'processing']);
            if ($stmt->rowCount() > 0) {
                notify((int) $ord['buyer_id'], 'Order ' . str_replace('_', ' ', $advance),
                    'Order ' . $ord['order_no'] . ($advance === 'in_transit' ? ' is on its way - keep your handover OTP ready.' : ' - documentation started.'), 'order.php?id=' . $orderId);
                flash('success', 'Order ' . $ord['order_no'] . ' moved to ' . str_replace('_', ' ', $advance) . '.');
            }
        } else {
            flash('error', 'Orders move one step at a time: confirmed, processing, in transit.');
        }
        redirect(base('seller/orders.php'));
    }
    $rating = (int) ($_POST['rating'] ?? 0);
    if ($ord && (int) $ord['seller_id'] === (int) $u['id'] && $rating >= 1 && $rating <= 5) {
        $exists = fetchOne("SELECT id FROM reviews WHERE order_id = ? AND author_id = ? AND reviewer_role = 'seller'", [$orderId, $u['id']]);
        if (!$exists) {
            insert('reviews', ['listing_id' => (int) $ord['listing_id'], 'order_id' => $orderId, 'author_id' => $u['id'],
                'target_user_id' => (int) $ord['buyer_id'], 'reviewer_role' => 'seller', 'rating' => $rating,
                'comment' => mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 2000) ?: null, 'status' => 'pending']);
            flash('success', 'Buyer rating submitted for moderation.');
        }
    }
    redirect(base('seller/orders.php'));
}

$rows = fetchAll('SELECT o.*, v.make, v.model, v.year, b.name AS buyer, b.mobile,
    (SELECT COUNT(*) FROM reviews r WHERE r.order_id = o.id AND r.author_id = ?) AS rated
    FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id
    JOIN users b ON b.id = o.buyer_id WHERE l.seller_id = ? ORDER BY o.created_at DESC', [$u['id'], $u['id']]);

adminHeader('Sales orders', 'orders', 'seller');
?>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Order</th><th>Buyer</th><th>Car</th><th>Amount</th><th>Status</th><th>Agreement</th><th>Delivery</th><th>Fulfil</th><th>Rate buyer</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9" class="empty">No sales yet - approved listings appear in the marketplace.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e($r['order_no']) ?><div class="muted" style="font-size:12px"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></div></td>
      <td><b><?= e($r['buyer']) ?></b><div class="muted num" style="font-size:12px"><?= e((string) $r['mobile']) ?></div></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td class="num"><?= rupees($r['amount']) ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td><?php $sg = orderSignatures((int) $r['id']); ?>
        <?= ($sg['buyer'] && $sg['seller']) ? statusBadge('verified') : statusBadge('pending') ?>
        <div><a class="btn btn-ghost btn-sm" href="<?= e(base('agreement.php?order=' . (int) $r['id'])) ?>"><?= $sg['seller'] ? 'View' : 'Sign now' ?></a></div></td>
      <td class="num"><?= e($r['delivery_date'] ? date('d M Y', strtotime((string) $r['delivery_date'])) : '-') ?></td>
      <td><?php if ($r['status'] === 'confirmed'): ?>
          <form method="post"><?= csrfField() ?><input type="hidden" name="order_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="advance" value="processing"><button class="btn btn-dark btn-sm">Start docs</button></form>
        <?php elseif ($r['status'] === 'processing'): ?>
          <form method="post"><?= csrfField() ?><input type="hidden" name="order_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="advance" value="in_transit"><button class="btn btn-dark btn-sm">Dispatch</button></form>
        <?php else: ?><small class="muted">-</small><?php endif; ?></td>
      <td><?php if ((int) $r['rated']): ?><small class="muted">Rated ✓</small>
        <?php else: ?><form method="post" style="display:flex;gap:6px"><?= csrfField() ?>
          <input type="hidden" name="order_id" value="<?= (int) $r['id'] ?>">
          <select class="form-select" style="padding:6px 8px;width:90px" name="rating"><option value="5">★★★★★</option><option value="4">★★★★</option><option value="3">★★★</option><option value="2">★★</option><option value="1">★</option></select>
          <button class="btn btn-outline btn-sm">Rate</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
