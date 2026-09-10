<?php
// Buyer-booked independent inspection (SRS: inspection services).
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$car = findListing((int) ($_GET['listing'] ?? $_POST['listing_id'] ?? 0));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = insert('inspection_bookings', [
        'listing_id' => (int) ($_POST['listing_id'] ?? 0), 'user_id' => $u['id'],
        'mode' => ($_POST['mode'] ?? 'home') === 'hub' ? 'hub' : 'home',
        'slot_date' => (string) ($_POST['slot_date'] ?? date('Y-m-d')),
        'slot_time' => (string) ($_POST['slot_time'] ?? '11:00 AM'),
        'address' => trim((string) ($_POST['address'] ?? '')), 'status' => 'requested',
    ]);
    logActivity((int) $u['id'], 'inspection.booked', refCode('INSP', $id));
    flash('success', 'Inspection ' . refCode('INSP', $id) . ' booked. Our engineer will confirm the slot.');
    redirect(base('account.php'));
}

$mine = fetchAll('SELECT b.*, v.make, v.model, v.year FROM inspection_bookings b
    JOIN listings l ON l.id = b.listing_id JOIN vehicles v ON v.id = l.vehicle_id
    WHERE b.user_id = ? ORDER BY b.slot_date DESC', [$u['id']]);

renderHeader('Book an inspection', 'services');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">Book a 280-point inspection</h1>
  <p class="muted">Get any car independently checked at your home or a DRIVE24 hub before you buy.</p>
  <div class="split-3">
    <form class="card card-pad" method="post">
      <?= csrfField() ?>
      <label class="form-label">Car listing ID <?= $car ? '(<b>' . e(vehicleTitle($car)) . '</b>)' : '' ?></label>
      <input class="form-control num" type="number" name="listing_id" value="<?= $car ? (int) $car['id'] : '' ?>" placeholder="e.g. 3" required>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
        <div><label class="form-label">Mode</label><select class="form-select" name="mode"><option value="home">At my home</option><option value="hub">At DRIVE24 hub</option></select></div>
        <div><label class="form-label">Date</label><input class="form-control" type="date" name="slot_date" value="<?= e(date('Y-m-d', strtotime('+3 days'))) ?>" required></div>
        <div><label class="form-label">Time slot</label><select class="form-select" name="slot_time"><option>10:00 AM</option><option>01:00 PM</option><option>04:00 PM</option></select></div>
        <div><label class="form-label">Address</label><input class="form-control" name="address" placeholder="Pickup address"></div>
      </div>
      <button class="btn btn-primary" style="margin-top:14px" type="submit">Book inspection</button>
    </form>
    <aside class="card card-pad sticky">
      <h3 style="font-size:1.05rem">My inspections</h3>
      <?php if (!$mine): ?><p class="muted">No inspections booked yet.</p><?php endif; ?>
      <?php foreach ($mine as $b): ?>
        <div class="kv"><span><?= e(refCode('INSP', (int) $b['id'])) ?> &middot; <?= e($b['make'] . ' ' . $b['model']) ?><br><small class="muted num"><?= e(date('d M Y', strtotime((string) $b['slot_date']))) ?>, <?= e($b['slot_time']) ?></small></span>
        <span><?= statusBadge((string) $b['status']) ?></span></div>
      <?php endforeach; ?>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
