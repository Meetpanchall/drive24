<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['td_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['confirmed', 'completed', 'cancelled'], true) ? $_POST['status'] : 'confirmed';
    $exec = mb_substr(trim((string) ($_POST['executive'] ?? '')), 0, 120);
    q('UPDATE test_drives t JOIN listings l ON l.id = t.listing_id SET t.status = ?, t.executive = ? WHERE t.id = ? AND l.seller_id = ?', [$status, $exec !== '' ? $exec : null, $id, $u['id']]);
    flash('success', 'Test drive marked as ' . $status . '.');
    redirect(base('seller/testdrives.php'));
}

$rows = fetchAll('SELECT t.*, v.make, v.model, v.year, u.name AS buyer, u.mobile
    FROM test_drives t JOIN listings l ON l.id = t.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = t.user_id
    WHERE l.seller_id = ? ORDER BY t.slot_date DESC', [$u['id']]);
adminHeader('Test drive requests', 'testdrives', 'seller');
?>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Buyer</th><th>Car</th><th>Mode</th><th>Slot</th><th>Address</th><th>Executive</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No test drive requests.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr><td><b><?= e($r['buyer']) ?></b><div class="muted num" style="font-size:12px"><?= e((string) $r['mobile']) ?></div></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td><?= e(ucfirst($r['mode'])) ?></td>
      <td class="num"><?= e(date('d M Y', strtotime((string) $r['slot_date']))) ?>, <?= e($r['slot_time']) ?></td>
      <td class="muted"><?= e((string) $r['address']) ?></td>
      <td><?= e((string) ($r['executive'] ?? '-')) ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px"><?= csrfField() ?><input type="hidden" name="td_id" value="<?= (int) $r['id'] ?>">
        <select class="form-select" name="status" style="width:120px"><option value="confirmed">Confirm</option><option value="completed">Complete</option><option value="cancelled">Cancel</option></select>
        <input class="form-control" name="executive" style="width:120px" value="<?= e((string) ($r['executive'] ?? '')) ?>" placeholder="Executive">
        <button class="btn btn-primary btn-sm">Apply</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
