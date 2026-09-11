<?php
require_once __DIR__ . '/../includes/rentals.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$allowed = ['pending', 'confirmed', 'active', 'returned', 'settled', 'cancelled'];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $to = (string) ($_POST['to'] ?? '');
    $r = findRental($id);
    if ($r && in_array($to, $allowed, true)) {
        q('UPDATE rentals SET status = ? WHERE id = ?', [$to, $id]);
        notify((int) $r['user_id'], 'Rental ' . $to, $r['booking_no'] . ' updated by DRIVE24 ops.', 'rental.php?id=' . $id);
        logActivity((int) $admin['id'], 'rental.status', '#' . $id . ' -> ' . $to);
        flash('success', 'Rental #' . $id . ' moved to ' . $to . '.');
    }
    redirect(base('admin/rentals.php'));
}

$filter = (string) ($_GET['status'] ?? '');
$sql = 'SELECT r.*, v.make, v.model, v.year, u.name AS customer, u.mobile FROM rentals r
    JOIN listings l ON l.id = r.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = r.user_id';
$params = [];
if ($filter !== '') { $sql .= ' WHERE r.status = ?'; $params[] = $filter; }
$rows = fetchAll($sql . ' ORDER BY r.created_at DESC LIMIT 200', $params);

adminHeader('Rental bookings', 'rentals');
?>
<div class="admin-top"><h1>Rental bookings</h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-left:auto">
    <?php foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'active' => 'On trip', 'returned' => 'Returned', 'settled' => 'Settled', 'cancelled' => 'Cancelled'] as $val => $label): ?>
      <a class="chip <?= $filter === $val ? 'active' : '' ?>" href="<?= e(base('admin/rentals.php' . ($val ? '?status=' . $val : ''))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Booking</th><th>Customer</th><th>Car</th><th>Window</th><th>Charged</th><th>Refund</th><th>Status</th><th>Move to</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">No rental bookings yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><b><?= e($r['booking_no']) ?></b><br><small class="muted"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></small></td>
      <td><?= e($r['customer']) ?><br><small class="muted num"><?= e((string) ($r['mobile'] ?? '')) ?></small></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?><br><small class="muted"><?= e($r['pickup_location']) ?> &rarr; <?= e($r['return_location']) ?></small></td>
      <td class="num"><small><?= e(date('d M H:i', strtotime((string) $r['pickup_at']))) ?><br><?= e(date('d M H:i', strtotime((string) $r['return_at']))) ?></small></td>
      <td class="num"><?= rupees($r['total_charged']) ?></td>
      <td class="num"><?= $r['refund_amount'] !== null ? rupees($r['refund_amount']) . ' ' . statusBadge((string) ($r['refund_status'] ?? 'processing')) : '<span class="muted">-</span>' ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select class="form-select" name="to" style="max-width:150px">
            <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(rentalStatusLabel($s)) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
