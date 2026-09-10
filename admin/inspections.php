<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $kind = (string) ($_POST['kind'] ?? '');
    if ($kind === 'booking') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($id > 0 && in_array($status, ['requested', 'confirmed', 'completed', 'cancelled'], true)) {
            updateRow('inspection_bookings', ['status' => $status], 'id = ?', [$id]);
            $b = fetchOne('SELECT * FROM inspection_bookings WHERE id = ?', [$id]);
            if ($b) { notify((int) $b['user_id'], 'Inspection ' . $status, 'Booking ' . refCode('INSP', $id), 'account.php'); }
            logActivity((int) $admin['id'], 'inspection.booking', '#' . $id . ' -> ' . $status);
            flash('success', 'Inspection booking #' . $id . ' marked ' . $status . '.');
        }
    } elseif ($kind === 'report') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($id > 0 && in_array($status, ['scheduled', 'completed', 'failed'], true)) {
            $scores = [];
            foreach (['score', 'engine_score', 'exterior_score', 'interior_score', 'electrical_score', 'tyres_score'] as $k) {
                if (isset($_POST[$k]) && $_POST[$k] !== '') { $scores[$k] = max(0, min(100, (int) $_POST[$k])); }
            }
            updateRow('inspections', array_merge($scores, [
                'status' => $status,
                'inspector' => trim((string) ($_POST['inspector'] ?? '')) ?: null,
                'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null,
            ]), 'id = ?', [$id]);
            $rep = fetchOne('SELECT * FROM inspections WHERE id = ?', [$id]);
            if ($rep) {
                q('UPDATE listings l JOIN vehicles v ON v.id = l.vehicle_id SET l.inspection_score = ? WHERE v.id = ?', [(int) ($scores['score'] ?? $rep['score']), (int) $rep['vehicle_id']]);
            }
            logActivity((int) $admin['id'], 'inspection.report', '#' . $id . ' -> ' . $status);
            flash('success', 'Inspection report #' . $id . ' saved. Car page scores refreshed.');
        }
    }
    redirect(base('admin/inspections.php'));
}

$bookings = [];
try {
    $bookings = fetchAll('SELECT b.*, v.make, v.model, v.year, u.name AS customer, u.mobile
        FROM inspection_bookings b JOIN listings l ON l.id = b.listing_id JOIN vehicles v ON v.id = l.vehicle_id
        JOIN users u ON u.id = b.user_id ORDER BY b.slot_date DESC, b.id DESC LIMIT 100');
} catch (Throwable $e) { /* table created on next request */ }
$reports = fetchAll('SELECT i.*, v.make, v.model, v.year FROM inspections i JOIN vehicles v ON v.id = i.vehicle_id ORDER BY i.id DESC LIMIT 100');

adminHeader('Inspections', 'inspections');
?>
<h2 style="font-size:1.1rem">Inspection bookings (<?= count($bookings) ?>)</h2>
<div class="table-wrap"><table class="data">
  <thead><tr><th>#</th><th>Vehicle</th><th>Customer</th><th>Mode</th><th>Slot</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$bookings): ?><tr><td colspan="7" class="empty">No bookings yet - buyers can book from Services or the car page.</td></tr><?php endif; ?>
  <?php foreach ($bookings as $r): ?>
    <tr>
      <td class="num"><?= e(refCode('INSP', (int) $r['id'])) ?></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td><?= e((string) $r['customer']) ?><div class="muted num" style="font-size:12px"><?= e((string) $r['mobile']) ?></div></td>
      <td><?= e(ucfirst((string) $r['mode'])) ?></td>
      <td class="num"><?= e(date('d M Y', strtotime((string) $r['slot_date']))) ?>, <?= e($r['slot_time']) ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px"><?= csrfField() ?>
        <input type="hidden" name="kind" value="booking"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <?php foreach (['requested', 'confirmed', 'completed', 'cancelled'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select><button class="btn btn-dark btn-sm" type="submit">Save</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>

<h2 style="font-size:1.1rem;margin-top:24px">280-point diagnostic reports (<?= count($reports) ?>)</h2>
<div class="table-wrap"><table class="data">
  <thead><tr><th>#</th><th>Vehicle</th><th>Inspector</th><th>Score</th><th>Engine</th><th>Body</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$reports): ?><tr><td colspan="8" class="empty">No reports yet.</td></tr><?php endif; ?>
  <?php foreach ($reports as $r): ?>
    <tr>
      <td class="num"><?= (int) $r['id'] ?></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td><?= e((string) ($r['inspector'] ?? '-')) ?></td>
      <td class="num"><b><?= (int) $r['score'] ?>/100</b></td>
      <td class="num"><?= (int) $r['engine_score'] ?></td>
      <td class="num"><?= (int) $r['exterior_score'] ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px;align-items:center"><?= csrfField() ?>
        <input type="hidden" name="kind" value="report"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <input class="form-control num" style="width:64px;padding:6px 8px" type="number" min="0" max="100" name="score" value="<?= (int) $r['score'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <?php foreach (['scheduled', 'completed', 'failed'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select><button class="btn btn-dark btn-sm" type="submit">Save</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
