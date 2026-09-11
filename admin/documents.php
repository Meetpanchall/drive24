<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$allowed = ['pending', 'verified', 'rejected'];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $st = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($st, $allowed, true)) {
        updateRow('documents', ['status' => $st], 'id = ?', [$id]);
        $d = fetchOne('SELECT user_id, doc_name FROM documents WHERE id = ?', [$id]);
        if ($d && (int) $d['user_id'] > 0) { notify((int) $d['user_id'], 'Document ' . $st, (string) $d['doc_name'] . ' was marked ' . $st . '.', 'services.php'); }
        logActivity((int) $admin['id'], 'documents.updated', '#' . $id . ' -> ' . $st);
        flash('success', 'Document #' . $id . ' marked ' . $st . '.');
    }
    redirect(base('admin/documents.php' . (!empty($_GET['type']) ? '?type=' . urlencode((string) $_GET['type']) : '')));
}

$types = array_column(fetchAll('SELECT DISTINCT doc_type FROM documents ORDER BY doc_type'), 'doc_type');
$filter = (string) ($_GET['type'] ?? '');
$sql = 'SELECT d.*, u.name AS owner, u.role, o.order_no FROM documents d JOIN users u ON u.id = d.user_id LEFT JOIN orders o ON o.id = d.order_id';
$params = [];
if ($filter !== '' && in_array($filter, $types, true)) { $sql .= ' WHERE d.doc_type = ?'; $params[] = $filter; }
$rows = fetchAll($sql . ' ORDER BY d.id DESC LIMIT 200', $params);

adminHeader('Documents registry', 'documents');
?>
<p class="muted">Every file on the platform - KYC, RC, handover photos, agreements, loans. Verify or reject; the owner is notified.</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <a class="chip <?= $filter === '' ? 'active' : '' ?>" href="<?= e(base('admin/documents.php')) ?>">All</a>
  <?php foreach ($types as $t): ?>
    <a class="chip <?= $filter === $t ? 'active' : '' ?>" href="<?= e(base('admin/documents.php?type=' . urlencode($t))) ?>"><?= e(ucfirst(str_replace('_', ' ', $t))) ?></a>
  <?php endforeach; ?>
</div>
<div class="table-wrap"><table class="data">
  <thead><tr><th>File</th><th>Type</th><th>Owner</th><th>Linked</th><th>Uploaded</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">No documents yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php if (!empty($r['file_url'])): ?><a href="<?= e(base('assets/uploads/' . $r['file_url'])) ?>" target="_blank" rel="noopener"><?= e((string) $r['doc_name']) ?> &nearr;</a>
        <?php else: ?><?= e((string) $r['doc_name']) ?><?php endif; ?></td>
      <td><?= e(ucfirst(str_replace('_', ' ', (string) $r['doc_type']))) ?></td>
      <td><?= e((string) $r['owner']) ?> <small class="muted">(<?= e((string) $r['role']) ?>)</small></td>
      <td class="num" style="font-size:12px"><?= !empty($r['order_no']) ? e($r['order_no']) : (!empty($r['listing_id']) ? 'LST-' . (int) $r['listing_id'] : '-') ?></td>
      <td class="num" style="white-space:nowrap"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px"><?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-dark btn-sm" type="submit">Save</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
