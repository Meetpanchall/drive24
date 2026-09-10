<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

$types = ['rc' => 'Registration certificate (RC)', 'insurance' => 'Insurance policy', 'service' => 'Service book', 'pollution' => 'Pollution certificate', 'other' => 'Other'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $lid = (int) ($_POST['listing_id'] ?? 0);
    $type = (string) ($_POST['doc_type'] ?? '');
    $owned = fetchOne('SELECT l.id, v.make, v.model, v.year FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.id = ? AND l.seller_id = ?', [$lid, $u['id']]);
    $file = saveDocument($_FILES['document'] ?? [], 'listingdoc');
    if (!$owned) {
        flash('error', 'Please choose one of your listings.');
    } elseif (!isset($types[$type])) {
        flash('error', 'Please choose a document type.');
    } elseif ($file === null) {
        flash('error', 'Upload failed. Send a JPG, PNG or PDF under 8 MB.');
    } else {
        insert('documents', ['user_id' => $u['id'], 'listing_id' => $lid, 'doc_type' => $type,
            'doc_name' => $types[$type] . ' - ' . $owned['year'] . ' ' . $owned['make'] . ' ' . $owned['model'],
            'file_url' => $file, 'status' => 'pending']);
        notifyAdmins('Listing document uploaded', $types[$type] . ' for listing #' . $lid, 'admin/kyc.php');
        flash('success', 'Document uploaded. It shows as verified on the car page after our check.');
    }
    redirect(base('seller/documents.php'));
}

$cars = fetchAll('SELECT l.id, v.make, v.model, v.year FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.seller_id = ? ORDER BY l.id DESC', [$u['id']]);
$docs = fetchAll('SELECT d.*, v.make, v.model, v.year FROM documents d LEFT JOIN listings l ON l.id = d.listing_id LEFT JOIN vehicles v ON v.id = l.vehicle_id WHERE d.user_id = ? AND d.listing_id IS NOT NULL ORDER BY d.id DESC', [$u['id']]);
adminHeader('Listing documents', 'documents', 'seller');
?>
<div class="card card-pad" style="margin-bottom:16px">
  <h3 style="margin-top:0">Upload a document for a listing</h3>
  <?php if (!$cars): ?><p class="muted">List a car first, then attach its RC and insurance here.</p><?php else: ?>
  <form method="post" enctype="multipart/form-data" class="grid" style="grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end">
    <?= csrfField() ?>
    <div><label class="form-label">Listing</label>
      <select class="form-select" name="listing_id"><?php foreach ($cars as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['year'] . ' ' . $c['make'] . ' ' . $c['model']) ?> (#<?= (int) $c['id'] ?>)</option><?php endforeach; ?></select></div>
    <div><label class="form-label">Document type</label>
      <select class="form-select" name="doc_type"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label">File (JPG / PNG / PDF, max 8 MB)</label><input class="form-control" type="file" name="document" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
    <div><button class="btn btn-primary" type="submit">Upload</button></div>
  </form>
  <?php endif; ?>
</div>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Listing</th><th>Document</th><th>File</th><th>Status</th><th>Uploaded</th></tr></thead>
  <tbody>
  <?php if (!$docs): ?><tr><td colspan="5" class="empty">No listing documents yet.</td></tr><?php endif; ?>
  <?php foreach ($docs as $d): ?>
    <tr>
      <td><a href="<?= e(base('car.php?id=' . (int) $d['listing_id'])) ?>"><?= e(trim(($d['year'] ?? '') . ' ' . ($d['make'] ?? '') . ' ' . ($d['model'] ?? ''))) ?> (#<?= (int) $d['listing_id'] ?>)</a></td>
      <td><?= e($d['doc_name']) ?></td>
      <td><?= !empty($d['file_url']) ? '<a target="_blank" href="' . e(base('assets/uploads/' . $d['file_url'])) . '">View</a>' : '<span class="muted">-</span>' ?></td>
      <td><?= statusBadge((string) $d['status']) ?></td>
      <td class="num"><?= e(date('d M Y', strtotime((string) $d['created_at']))) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
