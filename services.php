<?php
require_once __DIR__ . '/includes/layout.php';

$u = user();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $u = requireLogin();
    $kind = (string) ($_POST['kind'] ?? 'service');
    if ($kind === 'kyc') {
        $file = !empty($_FILES['doc_file']['name'] ?? '') ? saveDocument($_FILES['doc_file'], 'kyc') : null;
        insert('documents', [
            'user_id' => $u['id'],
            'doc_type' => 'kyc',
            'doc_name' => trim((string) ($_POST['id_type'] ?? 'ID proof')) . ' - ' . trim((string) ($_POST['id_number'] ?? '')),
            'file_url' => $file,
            'status' => 'pending',
        ]);
        flash('success', 'KYC document uploaded. Compliance usually verifies within 24 hours.');
    } else {
        $file = !empty($_FILES['doc_file']['name'] ?? '') ? saveDocument($_FILES['doc_file'], 'doc') : null;
        insert('documents', [
            'user_id' => $u['id'],
            'doc_type' => (string) ($_POST['doc_type'] ?? 'rc'),
            'doc_name' => trim((string) ($_POST['doc_name'] ?? 'Service request')),
            'file_url' => $file,
            'status' => 'pending',
        ]);
        flash('success', 'Service request created. Track it in your document vault below.');
    }
    redirect(base('services.php'));
}

$docs = ($u && dbReady()) ? fetchAll('SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC', [$u['id']]) : [];
$services = [
    ['RC transfer', 'End-to-end ownership transfer with the RTO, including Form 29/30 and smart card delivery.', '3,499'],
    ['Insurance transfer', 'Transfer or renew the policy in your name with our partner insurers.', '999'],
    ['NOC &amp; re-registration', 'Inter-state NOC, road tax refund assistance and re-registration.', '5,999'],
    ['Duplicate RC', 'Lost RC replacement handled with the transport department.', '2,499'],
    ['Hypothecation removal', 'Loan closure paperwork and HP termination at the RTO.', '1,999'],
    ['Doorstep inspection', '280-point inspection at your home with a digital report.', 'Free'],
];

renderHeader('RTO and ownership services', 'services');
?>
<div class="wrap section">
  <h1 style="font-size:1.7rem">Vehicle services &amp; document vault</h1>
  <p class="muted">Everything after the sale: RC transfer, insurance, NOC and your digital paperwork in one place.</p>

  <div class="grid four" style="margin-top:18px">
    <?php foreach ($services as [$title, $desc, $fee]): ?>
      <div class="card card-pad">
        <h3 style="font-size:1.02rem"><?= $title ?></h3>
        <p class="muted" style="font-size:13.6px"><?= $desc ?></p>
        <div class="num" style="font-weight:700"><?= $fee === 'Free' ? 'Free' : rupees((float) str_replace(',', '', $fee)) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="split-3">
    <div class="card card-pad">
      <h2 style="font-size:1.15rem">Request a service</h2>
      <form method="post" enctype="multipart/form-data" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <?= csrfField() ?><input type="hidden" name="kind" value="service">
        <div><label class="form-label">Service</label><select class="form-select" name="doc_type">
          <option value="rc">RC transfer</option><option value="insurance">Insurance transfer</option>
          <option value="noc">NOC / re-registration</option><option value="duplicate_rc">Duplicate RC</option>
          <option value="hypothecation">Hypothecation removal</option><option value="inspection">Doorstep inspection</option>
        </select></div>
        <div><label class="form-label">Reference / vehicle number</label><input class="form-control" name="doc_name" placeholder="MH01AB1234 - RC transfer" required></div>
        <div style="grid-column:1/-1"><label class="form-label">Attach RC / supporting document (PDF or image, max 8 MB)</label><input class="form-control" type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
        <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Submit request</button></div>
      </form>

      <h2 style="font-size:1.15rem;margin-top:22px">KYC verification</h2>
      <form method="post" enctype="multipart/form-data" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <?= csrfField() ?><input type="hidden" name="kind" value="kyc">
        <div><label class="form-label">ID type</label><select class="form-select" name="id_type"><option>PAN card</option><option>Aadhaar card</option><option>Dealer licence</option><option>Driving licence</option></select></div>
        <div><label class="form-label">ID number</label><input class="form-control" name="id_number" placeholder="ABCDE1234F" required></div>
        <div style="grid-column:1/-1"><label class="form-label">Upload scan (PDF or image)</label><input class="form-control" type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
        <div style="grid-column:1/-1"><button class="btn btn-dark" type="submit">Upload KYC</button></div>
      </form>

      <h2 style="font-size:1.15rem;margin-top:22px">My document vault</h2>
      <?php if (!$docs): ?><p class="muted">Sign in to see your RC, invoices, KYC and loan documents here.</p><?php endif; ?>
      <?php if ($docs): ?>
        <div class="table-wrap" style="border:0"><table class="data">
          <thead><tr><th>Document</th><th>Type</th><th>File</th><th>Status</th><th>Created</th></tr></thead>
          <tbody><?php foreach ($docs as $d): ?>
            <tr><td><?= e($d['doc_name']) ?></td><td><?= e(strtoupper((string) $d['doc_type'])) ?></td>
              <td><?= !empty($d['file_url']) ? '<a target="_blank" href="' . e(base('assets/uploads/' . $d['file_url'])) . '">View</a>' : '<span class="muted">-</span>' ?></td>
              <td><?= statusBadge((string) $d['status']) ?></td>
              <td class="num"><?= e(date('d M Y', strtotime((string) $d['created_at']))) ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>
    <aside class="card card-pad sticky">
      <h3 style="font-size:1.05rem">RC transfer timeline</h3>
      <div class="steps" style="flex-direction:column">
        <div class="step done">Documents collected</div>
        <div class="step active">RTO submission</div>
        <div class="step">Smart card printing</div>
        <div class="step">Delivered to you</div>
      </div>
      <p class="muted" style="font-size:13px;margin-top:10px">Typical completion: 21 to 30 working days depending on the RTO.</p>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
