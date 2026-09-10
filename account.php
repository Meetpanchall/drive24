<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    updateRow('users', [
        'name'   => trim((string) ($_POST['name'] ?? $u['name'])),
        'mobile' => trim((string) ($_POST['mobile'] ?? '')),
        'city'   => trim((string) ($_POST['city'] ?? '')),
    ], 'id = ?', [$u['id']]);
    flash('success', 'Profile updated in the database.');
    redirect(base('account.php'));
}

$orders = fetchAll('SELECT o.*, v.make, v.model, v.year FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE o.buyer_id = ? ORDER BY o.created_at DESC', [$u['id']]);
$drives = fetchAll('SELECT t.*, v.make, v.model FROM test_drives t JOIN listings l ON l.id = t.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE t.user_id = ? ORDER BY t.slot_date DESC', [$u['id']]);
$myOffers = fetchAll('SELECT o.*, v.make, v.model FROM offers o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE o.buyer_id = ? ORDER BY o.created_at DESC', [$u['id']]);
$saved = count(wishlistIds());

renderHeader('My account', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">Hello, <?= e($u['name']) ?></h1>
  <p class="muted">Role: <?= e(ucfirst($u['role'])) ?> &middot; KYC <?= statusBadge((string) $u['kyc_status']) ?></p>
  <div class="kpis" style="margin-top:16px">
    <div class="kpi"><small>Orders</small><b class="num"><?= count($orders) ?></b></div>
    <div class="kpi"><small>Saved cars</small><b class="num"><?= $saved ?></b></div>
    <div class="kpi"><small>Test drives</small><b class="num"><?= count($drives) ?></b></div>
    <div class="kpi"><small>Offers sent</small><b class="num"><?= count($myOffers) ?></b></div>
  </div>

  <div class="split-3">
    <div>
      <h2 style="font-size:1.2rem">Recent orders</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Order</th><th>Car</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$orders): ?><tr><td colspan="5" class="empty">No orders yet.</td></tr><?php endif; ?>
        <?php foreach ($orders as $o): ?>
          <tr><td class="num"><?= e($o['order_no']) ?></td>
            <td><?= e($o['year'] . ' ' . $o['make'] . ' ' . $o['model']) ?></td>
            <td class="num"><?= rupees($o['amount']) ?></td>
            <td><?= statusBadge((string) $o['status']) ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(base('order.php?id=' . (int) $o['id'])) ?>">Track</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>

      <h2 style="font-size:1.2rem;margin-top:24px">Test drives</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Car</th><th>Mode</th><th>Slot</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$drives): ?><tr><td colspan="4" class="empty">No test drives booked.</td></tr><?php endif; ?>
        <?php foreach ($drives as $d): ?>
          <tr><td><?= e($d['make'] . ' ' . $d['model']) ?></td><td><?= e(ucfirst($d['mode'])) ?></td>
            <td class="num"><?= e(date('d M Y', strtotime((string) $d['slot_date']))) ?>, <?= e($d['slot_time']) ?></td>
            <td><?= statusBadge((string) $d['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>

      <h2 style="font-size:1.2rem;margin-top:24px">My offers</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Car</th><th>Offered</th><th>Counter</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$myOffers): ?><tr><td colspan="4" class="empty">No offers submitted.</td></tr><?php endif; ?>
        <?php foreach ($myOffers as $o): ?>
          <tr><td><?= e($o['make'] . ' ' . $o['model']) ?></td><td class="num"><?= rupees($o['amount']) ?></td>
            <td class="num"><?= $o['counter_amount'] ? rupees($o['counter_amount']) : '&mdash;' ?></td>
            <td><?= statusBadge((string) $o['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>

    <aside class="card card-pad sticky">
      <h2 style="font-size:1.2rem">Profile</h2>
      <form method="post">
        <?= csrfField() ?>
        <div style="margin-bottom:10px"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($u['name']) ?>"></div>
        <div style="margin-bottom:10px"><label class="form-label">Email</label><input class="form-control" value="<?= e($u['email']) ?>" disabled></div>
        <div style="margin-bottom:10px"><label class="form-label">Mobile</label><input class="form-control" name="mobile" value="<?= e((string) $u['mobile']) ?>"></div>
        <div style="margin-bottom:14px"><label class="form-label">City</label><input class="form-control" name="city" value="<?= e((string) $u['city']) ?>"></div>
        <button class="btn btn-primary btn-block" type="submit">Save profile</button>
      </form>
      <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
      <a class="btn btn-outline btn-block btn-sm" href="<?= e(base('wishlist.php')) ?>">Wishlist &amp; alerts</a>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:8px" href="<?= e(base('services.php')) ?>">Documents &amp; services</a>
      <?php if (isRole('seller', 'dealer', 'admin')): ?><a class="btn btn-dark btn-block btn-sm" style="margin-top:8px" href="<?= e(base('seller/dashboard.php')) ?>">Seller portal</a><?php endif; ?>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
