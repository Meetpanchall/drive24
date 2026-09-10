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
$myRc = ($u && dbReady()) ? fetchAll('SELECT rc.*, o.order_no, v.make, v.model FROM rc_transfers rc JOIN orders o ON o.id = rc.order_id JOIN listings l ON l.id = rc.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE rc.buyer_id = ? ORDER BY rc.updated_at DESC', [$u['id']]) : [];
$services = [
    ['rc', 'RC transfer', 'End-to-end ownership transfer with the RTO, including Form 29/30 and smart card delivery.', '3,499', 'swap'],
    ['insurance', 'Insurance transfer', 'Transfer or renew the policy in your name with our partner insurers.', '999', 'shield'],
    ['noc', 'NOC &amp; re-registration', 'Inter-state NOC, road tax refund assistance and re-registration.', '5,999', 'doc'],
    ['duplicate_rc', 'Duplicate RC', 'Lost RC replacement handled with the transport department.', '2,499', 'doc'],
    ['hypothecation', 'Hypothecation removal', 'Loan closure paperwork and HP termination at the RTO.', '1,999', 'shield'],
    ['inspection', 'Doorstep inspection', '280-point inspection at your home with a digital report.', 'Free', 'car'],
];
function svcIcon(string $kind): string
{
    $p = [
        'swap' => '<path d="M4 8h13l-3-3M20 16H7l3 3" stroke-linecap="round" stroke-linejoin="round"/>',
        'shield' => '<path d="M12 2l7 3v6c0 5-3.2 8.6-7 11-3.8-2.4-7-6-7-11V5z"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>',
        'doc' => '<path d="M7 2h7l5 5v15H7z" stroke-linejoin="round"/><path d="M14 2v5h5M10 13h5M10 17h5" stroke-linecap="round"/>',
        'car' => '<path d="M5 16l1.5-4.5A2 2 0 0 1 8.4 10h7.2a2 2 0 0 1 1.9 1.5L19 16"/><path d="M4 16h16v3.5H4z"/><circle cx="8" cy="19.5" r="1.4"/><circle cx="16" cy="19.5" r="1.4"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' . ($p[$kind] ?? $p['doc']) . '</svg>';
}

renderHeader('RTO and ownership services', 'services');
?>
<div class="wrap section">
  <section class="svc-hero">
    <div class="svc-hero-txt">
      <div class="eyebrow">Need a car? We've got you covered</div>
      <h1>Vehicle services &amp;<br><span class="blue">document vault</span></h1>
      <p>Everything after the sale: RC transfer, insurance, NOC and your digital paperwork in one place.</p>
    </div>
    <div class="svc-hero-img"><img src="<?= e(base('assets/img/car5.jpg')) ?>" alt="SUV on the road"></div>
  </section>

  <div class="svc-grid">
    <?php foreach ($services as [$val, $title, $desc, $fee, $icon]): ?>
      <a class="svc-card" href="#request" data-svc="<?= $val ?>">
        <span class="svc-ic"><?= svcIcon($icon) ?></span>
        <b><?= $title ?></b>
        <small><?= $desc ?></small>
        <span class="svc-price"><?= $fee === 'Free' ? 'Free' : '&#8377;' . $fee ?> <span class="chev">&rsaquo;</span></span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="svc-split">
    <div class="svc-main">
      <div class="card card-pad" id="request">
        <div class="svc-block-head">
          <span class="svc-ic lg"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5" stroke-linecap="round"/></svg></span>
          <div><h2>Request a Service</h2><p class="muted">Choose your service and provide the necessary details. Our team will get back to you shortly.</p></div>
        </div>
        <div class="svc-block">
          <div class="svc-block-head sm">
            <span class="svc-ic"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16l1.5-4.5A2 2 0 0 1 8.4 10h7.2a2 2 0 0 1 1.9 1.5L19 16"/><path d="M4 16h16v3.5H4z"/><circle cx="8" cy="19.5" r="1.4"/><circle cx="16" cy="19.5" r="1.4"/></svg></span>
            <div><h3>RC Transfer</h3><p class="muted">Transfer ownership to your name with RTO support.</p></div>
            <span class="popular">Most Popular</span>
          </div>
          <form method="post" enctype="multipart/form-data" class="grid svc-form">
            <?= csrfField() ?><input type="hidden" name="kind" value="service">
            <div><label class="form-label">Service</label><select class="form-select" name="doc_type" id="svcSelect">
              <option value="rc">RC transfer</option><option value="insurance">Insurance transfer</option>
              <option value="noc">NOC / re-registration</option><option value="duplicate_rc">Duplicate RC</option>
              <option value="hypothecation">Hypothecation removal</option><option value="inspection">Doorstep inspection</option>
            </select></div>
            <div><label class="form-label">Reference / Vehicle Number</label><input class="form-control" name="doc_name" placeholder="MH01AB1234 - RC transfer" required></div>
            <div class="full"><label class="form-label">Attach RC / Supporting document (PDF or Image, Max 8 MB)</label><input class="form-control" type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
            <div class="full"><button class="btn-submit" type="submit"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg> Submit request</button></div>
          </form>
        </div>
        <div class="svc-block">
          <div class="svc-block-head sm">
            <span class="svc-ic"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-5.5 8-5.5s6.5 1.5 8 5.5"/></svg></span>
            <div><h3>KYC Verification</h3><p class="muted">Verify your identity for a smooth process.</p></div>
          </div>
          <form method="post" enctype="multipart/form-data" class="grid svc-form">
            <?= csrfField() ?><input type="hidden" name="kind" value="kyc">
            <div><label class="form-label">ID Type</label><select class="form-select" name="id_type"><option>PAN Card</option><option>Aadhaar Card</option><option>Dealer Licence</option><option>Driving Licence</option></select></div>
            <div><label class="form-label">ID Number</label><input class="form-control" name="id_number" placeholder="ABCDE1234F" required></div>
            <div class="full"><label class="form-label">Upload ID Scan (PDF or Image, Max 8 MB)</label><input class="form-control" type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
            <div class="full"><button class="btn-dark-lg" type="submit"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg> Upload KYC</button></div>
          </form>
        </div>
        <div class="svc-block" id="vault">
          <div class="svc-block-head sm">
            <span class="svc-ic"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></span>
            <div><h3>My Document Vault</h3><p class="muted">View and manage your RC, invoices, KYC and loan documents here.</p></div>
            <a class="btn-vault" href="#vault">View Vault <span aria-hidden="true">&rarr;</span></a>
          </div>
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
      </div>
    </div>
    <aside class="svc-aside">
      <div class="card card-pad">
        <h2>How It Works</h2>
        <p class="muted">Complete a few simple steps and we'll handle the rest.</p>
        <ol class="how-steps">
          <li><span class="n">1</span><span class="svc-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke-linecap="round"/></svg></span><div><b>Submit Request</b><small>Fill in the details and upload documents.</small></div></li>
          <li><span class="n">2</span><span class="svc-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 2h7l5 5v15H7z" stroke-linejoin="round"/><path d="M14 2v5h5M10 14l1.5 1.5L14.5 12" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><b>Verification</b><small>Our team will verify your documents and details.</small></div></li>
          <li><span class="n">3</span><span class="svc-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14.2 3h-4l-.4 2.7a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.5a7 7 0 0 0 0 2.4l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2 1.2l.4 2.7h4l.4-2.7a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"/></svg></span><div><b>Processing</b><small>We'll complete the RTO / insurer process on your behalf.</small></div></li>
          <li><span class="n">4</span><span class="svc-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><b>Get Documents</b><small>Download your updated documents digitally.</small></div></li>
        </ol>
      </div>
      <div class="svc-promo">
        <div class="svc-promo-txt">
          <h3>Digital. Secure. Hassle-Free.</h3>
          <p>All your car documents, in one place.</p>
          <a class="btn-promo" href="#vault">Go to Document Vault <span aria-hidden="true">&rarr;</span></a>
        </div>
        <svg class="svc-promo-art" viewBox="0 0 120 120" width="104" height="104" fill="none"><rect x="28" y="14" width="64" height="80" rx="8" fill="#ffffff" opacity=".92"/><path d="M40 32h40M40 44h40M40 56h28" stroke="#2f7bff" stroke-width="5" stroke-linecap="round"/><rect x="62" y="66" width="46" height="42" rx="8" fill="#0b5cff"/><path d="M85 74l14 6v10c0 9-6 15-14 19-8-4-14-10-14-19V80z" fill="#7db4ff"/><path d="M79 94l4.5 4.5L92 90" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <?php if ($myRc): ?>
      <div class="card card-pad">
        <h3 style="font-size:1.05rem">My RC transfers</h3>
        <?php foreach ($myRc as $rc): ?>
          <div class="kv"><span><a href="<?= e(base('order.php?id=' . (int) $rc['order_id'])) ?>"><?= e($rc['order_no']) ?></a><br><small class="muted"><?= e($rc['make'] . ' ' . $rc['model']) ?></small></span>
            <small><?= e(ucwords(str_replace('_', ' ', (string) $rc['status']))) ?></small></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
