<?php
require_once __DIR__ . '/includes/rentals.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$filter = (string) ($_GET['status'] ?? '');
$sql = 'SELECT r.*, v.make, v.model, v.year, v.image FROM rentals r JOIN listings l ON l.id = r.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE r.user_id = ?';
$params = [(int) $u['id']];
if ($filter !== '') { $sql .= ' AND r.status = ?'; $params[] = $filter; }
$rows = dbReady() ? fetchAll($sql . ' ORDER BY r.created_at DESC', $params) : [];

renderHeader('My rentals', 'rent');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">My rentals</h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 18px">
    <?php foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'active' => 'On trip', 'returned' => 'Returned', 'settled' => 'Settled', 'cancelled' => 'Cancelled'] as $val => $label): ?>
      <a class="chip <?= $filter === $val ? 'active' : '' ?>" href="<?= e(base('my-rentals.php' . ($val ? '?status=' . $val : ''))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a class="btn btn-primary btn-sm" style="margin-left:auto" href="<?= e(base('rent.php')) ?>">Rent a car</a>
  </div>
  <?php if (!$rows): ?><div class="card card-pad empty">No rentals here yet. <a href="<?= e(base('rent.php')) ?>">Find your first self-drive car.</a></div><?php endif; ?>
  <div class="grid" style="grid-template-columns:1fr">
    <?php foreach ($rows as $o): ?>
      <div class="card card-pad" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <img src="<?= e(listingImage($o)) ?>" alt="" style="width:150px;height:94px;object-fit:cover;border-radius:10px">
        <div style="flex:1;min-width:190px">
          <b><?= e($o['year'] . ' ' . $o['make'] . ' ' . $o['model']) ?></b>
          <div class="muted num" style="font-size:13px"><?= e($o['booking_no']) ?> &middot; <?= e(date('d M, h:i A', strtotime((string) $o['pickup_at']))) ?> &rarr; <?= e(date('d M, h:i A', strtotime((string) $o['return_at']))) ?></div>
          <div class="muted" style="font-size:13px"><?= e($o['pickup_location']) ?> &rarr; <?= e($o['return_location']) ?></div>
        </div>
        <div class="num" style="font-weight:700"><?= rupees($o['total_charged']) ?></div>
        <div><?= statusBadge((string) $o['status']) ?></div>
        <a class="btn btn-outline btn-sm" href="<?= e(base('rental.php?id=' . (int) $o['id'])) ?>"><?= $o['status'] === 'active' ? 'Open trip' : 'Details' ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php renderFooter(); ?>
