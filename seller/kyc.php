<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

$types = ['pan' => 'PAN card', 'aadhaar' => 'Aadhaar card', 'gst' => 'GST certificate (dealers)', 'cheque' => 'Cancelled cheque', 'address' => 'Address proof'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $type = (string) ($_POST['doc_type'] ?? '');
    $file = saveDocument($_FILES['document'] ?? [], 'kyc');
    if (!isset($types[$type])) {
        flash('error', 'Please choose a document type.');
    } elseif ($file === null) {
        flash('error', 'Upload failed. Send a JPG, PNG or PDF under 8 MB.');
    } else {
        insert('documents', ['user_id' => $u['id'], 'doc_type' => $type,
            'doc_name' => $types[$type] . ' - ' . $u['name'], 'file_url' => $file, 'status' => 'pending']);
        updateRow('users', ['kyc_status' => 'pending'], 'id = ?', [$u['id']]);
        notifyAdmins('KYC submitted', $u['name'] . ' uploaded ' . $types[$type], 'admin/kyc.php');
        flash('success', 'Document uploaded. Our compliance team verifies KYC within 24 hours.');
    }
    redirect(base('seller/kyc.php'));
}

$me = fetchOne('SELECT kyc_status FROM users WHERE id = ?', [$u['id']]);
$docs = fetchAll('SELECT * FROM documents WHERE user_id = ? AND (order_id IS NULL OR order_id = 0) AND listing_id IS NULL ORDER BY id DESC', [$u['id']]);
adminHeader('KYC verification', 'kyc', 'seller');
?>
<div class="card card-pad" style="margin-bottom:16px">
  <div class="kv"><span>Current status</span><span><?= statusBadge((string) ($me['kyc_status'] ?? 'pending')) ?></span></div>
  <p class="muted" style="font-size:13px">Verified sellers get the trust badge on every listing, faster payouts and higher search ranking. Upload a clear photo or PDF of each document.</p>
</div>
<div class="card card-pad" style="margin-bottom:16px">
  <h3 style="margin-top:0">Upload a document</h3>
  <form method="post" enctype="multipart/form-data" class="grid" style="grid-template-columns:1fr 1fr auto;gap:10px;align-items:end">
    <?= csrfField() ?>
    <div><label class="form-label">Document type</label>
      <select class="form-select" name="doc_type"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label">File (JPG / PNG / PDF, max 8 MB)</label><input class="form-control" type="file" name="document" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
    <div><button class="btn btn-primary" type="submit">Upload</button></div>
  </form>
</div>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Document</th><th>Type</th><th>File</th><th>Status</th><th>Uploaded</th></tr></thead>
  <tbody>
  <?php if (!$docs): ?><tr><td colspan="5" class="empty">No documents yet.</td></tr><?php endif; ?>
  <?php foreach ($docs as $d): ?>
    <tr>
      <td><?= e($d['doc_name']) ?></td>
      <td><?= e($types[$d['doc_type']] ?? (string) $d['doc_type']) ?></td>
      <td><?= !empty($d['file_url']) ? '<a target="_blank" href="' . e(base('assets/uploads/' . $d['file_url'])) . '">View</a>' : '<span class="muted">-</span>' ?></td>
      <td><?= statusBadge((string) $d['status']) ?></td>
      <td class="num"><?= e(date('d M Y', strtotime((string) $d['created_at']))) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
