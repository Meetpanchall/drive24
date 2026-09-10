<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? 'profile');
    if ($action === 'profile') {
        updateRow('users', [
            'name'   => trim((string) ($_POST['name'] ?? $u['name'])),
            'mobile' => trim((string) ($_POST['mobile'] ?? '')),
            'city'   => trim((string) ($_POST['city'] ?? '')),
        ], 'id = ?', [$u['id']]);
        flash('success', 'Profile updated in the database.');
    } elseif ($action === 'search_toggle') {
        q('UPDATE saved_searches SET alert_enabled = 1 - alert_enabled WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $u['id']]);
        flash('success', 'Search alert preference saved.');
    } elseif ($action === 'search_delete') {
        q('DELETE FROM saved_searches WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $u['id']]);
        flash('success', 'Saved search deleted.');
    }
    redirect(base('account.php'));
}

$orders = fetchAll('SELECT o.*, v.make, v.model, v.year FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE o.buyer_id = ? ORDER BY o.created_at DESC', [$u['id']]);
$drives = fetchAll('SELECT t.*, v.make, v.model FROM test_drives t JOIN listings l ON l.id = t.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE t.user_id = ? ORDER BY t.slot_date DESC', [$u['id']]);
$myOffers = fetchAll('SELECT o.*, v.make, v.model FROM offers o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE o.buyer_id = ? ORDER BY o.created_at DESC', [$u['id']]);
$searches = fetchAll('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC', [$u['id']]);
$loans = [];
$quotes = [];
$inspections = [];
$chats = 0;
try {
    $loans = fetchAll('SELECT a.*, v.make, v.model FROM loan_applications a JOIN listings l ON l.id = a.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE a.user_id = ? ORDER BY a.id DESC', [$u['id']]);
    $quotes = fetchAll('SELECT q.*, v.make, v.model FROM insurance_quotes q JOIN listings l ON l.id = q.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE q.user_id = ? ORDER BY q.id DESC', [$u['id']]);
    $inspections = fetchAll('SELECT b.*, v.make, v.model FROM inspection_bookings b JOIN listings l ON l.id = b.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE b.user_id = ? ORDER BY b.id DESC', [$u['id']]);
    $chats = (int) fetchValue('SELECT COUNT(*) FROM chat_threads WHERE buyer_id = ? OR seller_id = ?', [$u['id'], $u['id']], 0);
} catch (Throwable $e) { /* extension tables self-create on next request */ }
$saved = count(wishlistIds());
$myBids = [];
try {
    $myBids = fetchAll('SELECT b.amount AS mybid, b.created_at AS bid_on, l.id AS listing_id, l.auction_ends_at,
        (SELECT MAX(amount) FROM bids WHERE listing_id = l.id) AS topbid,
        (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) AS bidcount,
        v.make, v.model, v.year FROM bids b
        JOIN listings l ON l.id = b.listing_id JOIN vehicles v ON v.id = l.vehicle_id
        WHERE b.buyer_id = ? ORDER BY b.id DESC', [$u['id']]);
} catch (Throwable $e) { /* bids table self-creates on next request */ }
$profileDone = 0;
foreach (['name' => $u['name'], 'mobile' => $u['mobile'], 'city' => $u['city']] as $pv) { if (trim((string) $pv) !== '') { $profileDone++; } }
if ((int) ($u['mobile_verified'] ?? 0)) { $profileDone++; }
if (($u['kyc_status'] ?? '') === 'verified') { $profileDone++; }
$profilePct = (int) round($profileDone * 100 / 5);

renderHeader('My account', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">Hello, <?= e($u['name']) ?></h1>
  <p class="muted">Role: <?= e(ucfirst($u['role'])) ?> &middot; KYC <?= statusBadge((string) $u['kyc_status']) ?>
    <?php if (!((int) ($u['mobile_verified'] ?? 0))): ?> &middot; <a href="<?= e(base('verify-otp.php')) ?>">Verify mobile</a><?php endif; ?></p>
  <div class="kpis" style="margin-top:16px">
    <div class="kpi"><small>Orders</small><b class="num"><?= count($orders) ?></b></div>
    <div class="kpi"><small>Saved cars</small><b class="num"><?= $saved ?></b></div>
    <div class="kpi"><small>Test drives</small><b class="num"><?= count($drives) ?></b></div>
    <div class="kpi"><small>Offers sent</small><b class="num"><?= count($myOffers) ?></b></div>
    <div class="kpi"><small>Conversations</small><b class="num"><?= $chats ?></b></div>
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
        <thead><tr><th>Car</th><th>Mode</th><th>Slot</th><th>Executive</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$drives): ?><tr><td colspan="5" class="empty">No test drives booked.</td></tr><?php endif; ?>
        <?php foreach ($drives as $d): ?>
          <tr><td><?= e($d['make'] . ' ' . $d['model']) ?></td><td><?= e(ucfirst($d['mode'])) ?></td>
            <td class="num"><?= e(date('d M Y', strtotime((string) $d['slot_date']))) ?>, <?= e($d['slot_time']) ?></td>
            <td><?= e((string) ($d['executive'] ?? 'To be assigned')) ?></td>
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

      <?php if ($myBids): ?>
      <h2 style="font-size:1.2rem;margin-top:24px">My auction bids</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Car</th><th>My bid</th><th>Top bid</th><th>Ends</th><th></th></tr></thead>
        <tbody><?php foreach ($myBids as $b): ?>
          <tr><td><?= e($b['year'] . ' ' . $b['make'] . ' ' . $b['model']) ?></td>
            <td class="num"><?= rupees($b['mybid']) ?><?= ((float) $b['mybid'] >= (float) $b['topbid']) ? ' <span class="badge ok">Highest</span>' : '' ?></td>
            <td class="num"><?= rupees($b['topbid']) ?> <small class="muted">(<?= (int) $b['bidcount'] ?>)</small></td>
            <td class="num"><?= !empty($b['auction_ends_at']) ? e(date('d M, h:i A', strtotime((string) $b['auction_ends_at']))) : '-' ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(base('car.php?id=' . (int) $b['listing_id'])) ?>">Bid</a></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>

      <h2 style="font-size:1.2rem;margin-top:24px">Saved searches &amp; alerts</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Search</th><th>Alerts</th><th></th></tr></thead>
        <tbody>
        <?php if (!$searches): ?><tr><td colspan="3" class="empty">No saved searches. Save any filter set from the <a href="<?= e(base('cars.php')) ?>">cars page</a>.</td></tr><?php endif; ?>
        <?php foreach ($searches as $s): ?>
          <tr><td><a href="<?= e(base('cars.php?' . $s['query_string'])) ?>"><?= e($s['title']) ?></a></td>
            <td><?= ((int) $s['alert_enabled'] ? statusBadge('verified') : statusBadge('pending')) ?></td>
            <td style="white-space:nowrap">
              <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="search_toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-ghost btn-sm"><?= (int) $s['alert_enabled'] ? 'Mute' : 'Unmute' ?></button></form>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this saved search?')"><?= csrfField() ?><input type="hidden" name="action" value="search_delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-ghost btn-sm">Delete</button></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>

      <?php if ($loans): ?>
      <h2 style="font-size:1.2rem;margin-top:24px">Loan applications</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Ref</th><th>Lender</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($loans as $ln): ?>
          <tr><td class="num"><?= e(refCode('LN', (int) $ln['id'])) ?></td><td><?= e($ln['lender']) ?> <small class="muted">(<?= e($ln['make'] . ' ' . $ln['model']) ?>)</small></td>
            <td class="num"><?= rupees($ln['amount']) ?></td><td><?= statusBadge((string) $ln['status']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>

      <?php if ($quotes): ?>
      <h2 style="font-size:1.2rem;margin-top:24px">Insurance policies</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Ref</th><th>Insurer</th><th>Premium</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($quotes as $qt): ?>
          <tr><td class="num"><?= e(refCode('IN', (int) $qt['id'])) ?></td><td><?= e($qt['insurer']) ?></td>
            <td class="num"><?= rupees($qt['premium']) ?></td><td><?= statusBadge((string) $qt['status']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>

      <?php if ($inspections): ?>
      <h2 style="font-size:1.2rem;margin-top:24px">My inspections</h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Ref</th><th>Car</th><th>Slot</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($inspections as $b): ?>
          <tr><td class="num"><?= e(refCode('INSP', (int) $b['id'])) ?></td><td><?= e($b['make'] . ' ' . $b['model']) ?></td>
            <td class="num"><?= e(date('d M Y', strtotime((string) $b['slot_date']))) ?></td><td><?= statusBadge((string) $b['status']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>

    <aside class="card card-pad sticky">
      <h2 style="font-size:1.2rem">Profile</h2>
      <div style="margin-bottom:12px"><small class="muted">Profile completeness: <b class="num"><?= $profilePct ?>%</b></small>
        <div style="background:var(--line);border-radius:6px;height:8px;margin-top:4px"><div style="width:<?= $profilePct ?>%;background:#16a34a;height:8px;border-radius:6px"></div></div></div>
      <form method="post">
        <?= csrfField() ?><input type="hidden" name="action" value="profile">
        <div style="margin-bottom:10px"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($u['name']) ?>"></div>
        <div style="margin-bottom:10px"><label class="form-label">Email</label><input class="form-control" value="<?= e($u['email']) ?>" disabled></div>
        <div style="margin-bottom:10px"><label class="form-label">Mobile</label><input class="form-control" name="mobile" value="<?= e((string) $u['mobile']) ?>"></div>
        <div style="margin-bottom:14px"><label class="form-label">City</label><input class="form-control" name="city" value="<?= e((string) $u['city']) ?>"></div>
        <button class="btn btn-primary btn-block" type="submit">Save profile</button>
      </form>
      <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
      <a class="btn btn-outline btn-block btn-sm" href="<?= e(base('wishlist.php')) ?>">Wishlist &amp; alerts</a>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:8px" href="<?= e(base('chat.php')) ?>">My messages</a>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:8px" href="<?= e(base('services.php')) ?>">Documents &amp; services</a>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:8px" href="<?= e(base('verify-otp.php')) ?>">Verify mobile (OTP)</a>
      <?php if (isRole('seller', 'dealer', 'admin')): ?><a class="btn btn-dark btn-block btn-sm" style="margin-top:8px" href="<?= e(base('seller/dashboard.php')) ?>">Seller portal</a><?php endif; ?>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
