<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'kyc_status';
$allowed = ['pending', 'verified', 'rejected'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['form'] ?? '') === 'doc') {
        $docStatus = (string) ($_POST['doc_status'] ?? '');
        $doc = fetchOne('SELECT * FROM documents WHERE id = ?', [$id]);
        if ($doc && in_array($docStatus, ['pending', 'verified', 'rejected'], true)) {
            updateRow('documents', ['status' => $docStatus], 'id = ?', [$id]);
            notify((int) $doc['user_id'], 'Document ' . $docStatus, $doc['doc_name'], 'services.php');
            logActivity((int) $admin['id'], 'documents.updated', '#' . $id . ' -> ' . $docStatus);
            flash('success', 'Document #' . $id . ' marked ' . $docStatus . '.');
        }
    } else {
        $status = (string) ($_POST['status'] ?? '');
        if ($id > 0 && in_array($status, $allowed, true)) {
            $data = ['kyc_status' => $status];
            updateRow('users', $data, 'id = ?', [$id]);
            notify($id, 'KYC ' . $status, 'Your verification status is now ' . $status . '.', 'account.php');
            logActivity((int) $admin['id'], 'users.updated', '#' . $id . ' -> ' . $status);
            flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
        }
    }
    redirect(base('admin/kyc.php'));
}

$rows = fetchAll("SELECT u.*, (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) AS docs FROM users u ORDER BY FIELD(u.kyc_status,'pending','rejected','verified'), u.id DESC");
$docsByUser = [];
try {
    foreach (fetchAll('SELECT * FROM documents ORDER BY id DESC') as $d) { $docsByUser[(int) $d['user_id']][] = $d; }
} catch (Throwable $e) { /* listing_id column self-adds on next request */ }
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('KYC & compliance', 'kyc');
?>
<p class="muted">Verify identity documents before a seller can transact. Status drives access across the marketplace.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Mobile</th><th>Documents</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((int) $r['id'])) ?></td>
      <td><?= e((string) ((string) $r['name'])) ?></td>
      <td><?= e((string) ((string) $r['email'])) ?></td>
      <td><?= e((string) (ucfirst((string) $r['role']))) ?></td>
      <td class="num"><?= e((string) ((string) ($r['mobile'] ?? '-'))) ?></td>
      <td class="num"><?= e((string) ((int) $r['docs'])) ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select class="form-select" style="padding:6px 8px" name="status">
            <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r[$statusCol] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
      </td>
    </tr>
    <?php if (!empty($docsByUser[(int) $r['id']])): ?>
    <tr><td></td><td colspan="7">
      <?php foreach ($docsByUser[(int) $r['id']] as $d): ?>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:4px 0;border-bottom:1px dashed var(--line)">
          <span><?= e($d['doc_name']) ?></span>
          <?= !empty($d['file_url']) ? '<a target="_blank" href="' . e(base('assets/uploads/' . $d['file_url'])) . '">View</a>' : '<span class="muted">no file</span>' ?>
          <?= statusBadge((string) $d['status']) ?>
          <form method="post" style="display:inline-flex;gap:4px"><?= csrfField() ?><input type="hidden" name="form" value="doc"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button class="btn btn-outline btn-sm" name="doc_status" value="verified" type="submit">Verify</button>
            <button class="btn btn-ghost btn-sm" name="doc_status" value="rejected" type="submit">Reject</button>
          </form>
        </div>
      <?php endforeach; ?>
    </td></tr>
    <?php endif; ?>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
