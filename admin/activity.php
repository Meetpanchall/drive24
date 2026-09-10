<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$q = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($q !== '') {
    $where = 'WHERE (a.action LIKE ? OR a.detail LIKE ? OR u.name LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$rows = fetchAll('SELECT a.*, u.name, u.role FROM activity_log a LEFT JOIN users u ON u.id = a.user_id '
    . $where . ' ORDER BY a.id DESC LIMIT 200', $params);

adminHeader('Audit log', 'activity');
?>
<form method="get" class="card card-pad" style="display:flex;gap:12px;align-items:flex-end">
  <div style="flex:1"><label class="form-label">Search logins, approvals, payments...</label>
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="e.g. listing.approved"></div>
  <button class="btn btn-primary" type="submit">Search</button>
</form>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>When</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="6" class="empty">No activity recorded.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr><td class="num"><?= (int) $r['id'] ?></td>
      <td class="num"><?= e(date('d M Y, H:i', strtotime((string) $r['created_at']))) ?></td>
      <td><?= e((string) ($r['name'] ?? 'Guest')) ?> <small class="muted">(<?= e((string) ($r['role'] ?? '-')) ?>)</small></td>
      <td><code><?= e($r['action']) ?></code></td>
      <td class="muted"><?= e((string) ($r['detail'] ?? '')) ?></td>
      <td class="num"><?= e((string) ($r['ip'] ?? '-')) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
