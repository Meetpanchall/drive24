<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$leadAllowed = ['new', 'contacted', 'inspection', 'closed'];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    if (($_POST['form'] ?? '') === 'question_delete') {
        $qid = (int) ($_POST['id'] ?? 0);
        q('DELETE FROM questions WHERE id = ?', [$qid]);
        logActivity((int) $admin['id'], 'enquiry.deleted', 'question #' . $qid);
        flash('success', 'Question #' . $qid . ' removed.');
    } elseif (($_POST['form'] ?? '') === 'lead') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = (string) ($_POST['status'] ?? '');
        if ($id > 0 && in_array($st, $leadAllowed, true)) {
            updateRow('leads', ['status' => $st], 'id = ?', [$id]);
            logActivity((int) $admin['id'], 'lead.updated', '#' . $id . ' -> ' . $st);
            flash('success', 'Lead #' . $id . ' moved to ' . $st . '.');
        }
    }
    redirect(base('admin/enquiries.php'));
}

$questions = fetchAll('SELECT q.*, v.make, v.model, v.year, u.name AS asker, s.name AS seller
    FROM questions q JOIN listings l ON l.id = q.listing_id JOIN vehicles v ON v.id = l.vehicle_id
    JOIN users u ON u.id = q.user_id JOIN users s ON s.id = l.seller_id
    ORDER BY q.id DESC LIMIT 100');
$leads = fetchAll('SELECT * FROM leads ORDER BY id DESC LIMIT 100');

adminHeader('Enquiries & leads', 'enquiries');
?>
<h2 style="font-size:1.15rem">Buyer questions on cars (<?= count($questions) ?>)</h2>
<p class="muted">Everything buyers ask sellers. Remove spam or abuse - the seller answers from their own panel.</p>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Car</th><th>Asker</th><th>Question</th><th>Answer</th><th></th></tr></thead>
  <tbody>
  <?php if (!$questions): ?><tr><td colspan="5" class="empty">No questions yet.</td></tr><?php endif; ?>
  <?php foreach ($questions as $q): ?>
    <tr>
      <td><a href="<?= e(base('car.php?id=' . (int) $q['listing_id'])) ?>"><?= e($q['year'] . ' ' . $q['make'] . ' ' . $q['model']) ?></a>
        <div class="muted" style="font-size:12px">seller: <?= e((string) $q['seller']) ?></div></td>
      <td><?= e((string) $q['asker']) ?><div class="muted num" style="font-size:12px"><?= e(date('d M Y', strtotime((string) $q['created_at']))) ?></div></td>
      <td><?= e((string) $q['question']) ?></td>
      <td><?= !empty($q['answer']) ? e((string) $q['answer']) : statusBadge('pending') ?></td>
      <td><form method="post" onsubmit="return confirm('Delete this question?')"><?= csrfField() ?>
        <input type="hidden" name="form" value="question_delete"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
        <button class="btn btn-outline btn-sm" type="submit">Delete</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>

<h2 style="font-size:1.15rem;margin-top:26px">Valuation leads (<?= count($leads) ?>)</h2>
<p class="muted">Sell-page "Get price" enquiries. Work them new &rarr; contacted &rarr; inspection &rarr; closed.</p>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Lead</th><th>Car</th><th>Quote</th><th>Status</th><th>Move to</th></tr></thead>
  <tbody>
  <?php if (!$leads): ?><tr><td colspan="5" class="empty">No leads yet.</td></tr><?php endif; ?>
  <?php foreach ($leads as $l): ?>
    <tr>
      <td><b><?= e((string) $l['name']) ?></b><div class="muted num" style="font-size:12px"><?= e((string) $l['mobile']) ?> &middot; <?= e((string) ($l['city'] ?? '')) ?></div>
        <div class="muted num" style="font-size:12px"><?= e(date('d M Y', strtotime((string) $l['created_at']))) ?></div></td>
      <td><?= e(trim((string) ($l['year'] ?? '') . ' ' . (string) ($l['make'] ?? '') . ' ' . (string) ($l['model'] ?? ''))) ?><div class="muted num" style="font-size:12px"><?= $l['km_driven'] !== null ? number_format((int) $l['km_driven']) . ' km' : '' ?> <?= e((string) ($l['fuel_type'] ?? '')) ?></div></td>
      <td class="num"><?= $l['quote_low'] ? rupees($l['quote_low']) . ' - ' . rupees($l['quote_high']) : '<span class="muted">-</span>' ?></td>
      <td><?= statusBadge((string) $l['status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px"><?= csrfField() ?>
        <input type="hidden" name="form" value="lead"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <?php foreach ($leadAllowed as $s): ?><option value="<?= e($s) ?>" <?= $l['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-dark btn-sm" type="submit">Save</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
