<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['open', 'processing', 'completed'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $priority = (string) ($_POST['priority'] ?? '');
    if ($id > 0 && in_array($status, $allowed, true)) {
        $data = ['status' => $status];
        if (in_array($priority, ['low', 'medium', 'high'], true)) { $data['priority'] = $priority; }
        updateRow('support_tickets', $data, 'id = ?', [$id]);
        $t = fetchOne('SELECT user_id FROM support_tickets WHERE id = ?', [$id]);
        if ($t && (int) $t['user_id'] > 0) { notify((int) $t['user_id'], 'Support ticket updated', 'Ticket ' . refCode('TKT', $id) . ' is now ' . $status, 'support.php'); }
        logActivity((int) $admin['id'], 'support_tickets.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
    }
    redirect(base('admin/support.php'));
}

$rows = fetchAll("SELECT t.*, u.name AS customer, u.email FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.id DESC");
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Support tickets', 'support');
?>
<p class="muted">Every ticket raised from the marketplace help centre lands here.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Subject</th><th>Category</th><th>Priority</th><th>Customer</th><th>Message</th><th>Raised</th><th>SLA</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="10" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) (refCode('TKT', (int) $r['id']))) ?></td>
      <td><?= e((string) ((string) $r['subject'])) ?></td>
      <td><?= e((string) (ucfirst((string) $r['category']))) ?></td>
      <td><?= $r['priority'] === 'high' ? statusBadge('flagged') : ($r['priority'] === 'medium' ? statusBadge('pending') : statusBadge('open')) ?> <small class="muted"><?= e(ucfirst((string) ($r['priority'] ?? 'medium'))) ?></small></td>
      <td><?= e((string) ((string) ($r['customer'] ?? 'Guest'))) ?></td>
      <td class="muted"><?= e((string) (mb_substr((string) $r['message'], 0, 70))) ?></td>
      <td class="num"><?= e((string) (date('d M Y', strtotime((string) $r['created_at'])))) ?></td>
      <td><?= $r[$statusCol] === 'open' && (time() - strtotime((string) $r['created_at'])) > 48 * 3600 ? statusBadge('expired') : statusBadge('confirmed') ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select class="form-select" style="padding:6px 8px" name="status">
            <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r[$statusCol] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
          </select>
          <select class="form-select" style="padding:6px 8px" name="priority" title="Priority">
            <?php foreach (['low', 'medium', 'high'] as $pr): ?><option value="<?= $pr ?>" <?= ($r['priority'] ?? 'medium') === $pr ? 'selected' : '' ?>><?= ucfirst($pr) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
