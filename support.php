<?php
require_once __DIR__ . '/includes/layout.php';

$u = user();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    insert('support_tickets', [
        'user_id' => $u['id'] ?? null,
        'subject' => trim((string) ($_POST['subject'] ?? '')),
        'category' => (string) ($_POST['category'] ?? 'general'),
        'priority' => in_array($_POST['priority'] ?? 'medium', ['low', 'medium', 'high'], true) ? (string) $_POST['priority'] : 'medium',
        'message' => trim((string) ($_POST['message'] ?? '')),
        'status' => 'open',
    ]);
    flash('success', 'Ticket raised. Our support team replies within 4 working hours.');
    redirect(base('support.php'));
}

$tickets = ($u && dbReady()) ? fetchAll('SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC', [$u['id']]) : [];
$faqs = [
    ['Is every car inspected?', 'Yes. Each car clears a 280-point inspection and the full report is on the car page.'],
    ['Can I return the car?', 'You get a 7-day easy return on every DRIVE24 certified car.'],
    ['How long does RC transfer take?', 'Usually 21 to 30 working days. You can track it in Services.'],
    ['Do you finance used cars?', 'Yes, with 12+ lending partners and approvals in 24 hours.'],
];

renderHeader('Help and support', '');
?>
<div class="wrap section">
  <div class="sup-hero">
    <div class="sup-hero-txt">
      <small>Help &amp; Support</small>
      <h1>We&apos;re here to help!</h1>
      <p>Have a question, issue or need assistance? Submit a ticket and our team will get back to you as soon as possible.</p>
    </div>
    <svg class="sup-bubbles" width="150" height="96" viewBox="0 0 150 96" aria-hidden="true">
      <rect x="18" y="6" width="66" height="46" rx="14" fill="#1f7bff"/>
      <path d="M40 52l-3 12 13-12z" fill="#1f7bff"/>
      <circle cx="38" cy="29" r="4" fill="#fff"/><circle cx="52" cy="29" r="4" fill="#fff"/><circle cx="66" cy="29" r="4" fill="#fff"/>
      <rect x="72" y="38" width="66" height="46" rx="14" fill="#ffffff" stroke="#d6e4fb" stroke-width="2"/>
      <path d="M112 84l3 11-13-11z" fill="#ffffff"/>
      <circle cx="92" cy="61" r="4" fill="#1f7bff"/><circle cx="106" cy="61" r="4" fill="#1f7bff"/><circle cx="120" cy="61" r="4" fill="#1f7bff"/>
    </svg>
    <div class="sup-hero-img">
      <img src="<?= e(base('assets/img/cars/car9.jpg')) ?>" alt="Blue SUV">
    </div>
  </div>

  <div class="sup-grid">
    <div class="card card-pad ticket-card">
      <div class="how-head">
        <span class="svc-ic lg" style="background:#0b5cff;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12l-8 8-9-9V4h7z"/><circle cx="8.5" cy="8.5" r="1.4" fill="currentColor" stroke="none"/></svg></span>
        <span><b>Raise a Ticket</b><small>Fill in the details below to get your query resolved.</small></span>
      </div>
      <form method="post" class="tix-form">
        <?= csrfField() ?>
        <div><label class="form-label">Category <span class="req">*</span></label>
          <div class="in-wrap"><span class="in-ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg></span>
          <select class="form-select" name="category" required>
            <option value="" disabled selected>Select category</option>
            <option value="order">Order / delivery</option><option value="payment">Payment or refund</option>
            <option value="rc">RC transfer</option><option value="listing">My listing</option><option value="general">General</option>
          </select></div>
        </div>
        <div><label class="form-label">Subject <span class="req">*</span></label>
          <div class="in-wrap"><span class="in-ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12l-8 8-9-9V4h7z"/><circle cx="8.5" cy="8.5" r="1.4" fill="currentColor" stroke="none"/></svg></span>
          <input class="form-control" name="subject" placeholder="Enter subject" required></div>
        </div>
        <div class="full"><label class="form-label">Priority <span class="req">*</span></label>
          <div class="in-wrap"><span class="in-ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/></svg></span>
          <select class="form-select" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
        </div>
        <div class="full"><label class="form-label">Description <span class="req">*</span></label>
          <div class="in-wrap"><span class="in-ic top"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"/></svg></span>
          <textarea class="form-control" name="message" rows="5" maxlength="1000" placeholder="Describe your issue or question in detail..." data-count="tixCount" required></textarea></div>
          <small class="char-n muted"><span id="tixCount">0</span>/1000</small>
        </div>
        <div class="full tix-actions">
          <button type="button" class="btn btn-outline btn-sm" data-soon="File attachments are coming soon."><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11l-8.5 8.5a5 5 0 0 1-7-7L13 5a3.5 3.5 0 0 1 5 5l-7.5 7.5a1.8 1.8 0 0 1-2.5-2.5L14.5 8.5"/></svg> Attach File <small>(Optional)</small></button>
          <button class="btn btn-primary" type="submit"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg> Submit Ticket</button>
        </div>
      </form>
      <script>
      (function () {
        var ta = document.querySelector('[data-count="tixCount"]');
        var out = document.getElementById('tixCount');
        if (ta && out) { ta.addEventListener('input', function () { out.textContent = ta.value.length; }); }
      })();
      </script>

      <?php if ($tickets): ?>
        <h2 style="font-size:1.1rem;margin:20px 0 8px">My tickets</h2>
        <div class="table-wrap" style="border:0"><table class="data">
          <thead><tr><th>Ticket</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th></tr></thead>
          <tbody><?php foreach ($tickets as $t): ?>
            <tr><td class="num"><?= e(refCode('TKT', (int) $t['id'])) ?></td><td><?= e($t['subject']) ?></td>
              <td><?= e(ucfirst((string) $t['category'])) ?></td><td><?= e(ucfirst((string) ($t['priority'] ?? 'medium'))) ?></td><td><?= statusBadge((string) $t['status']) ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>

    <aside class="sup-rail">
      <div class="card card-pad rail-card">
        <div class="rail-head">
          <span class="stat-ic" style="background:#e3f0fe;color:#0b5cff;width:40px;height:40px"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"><circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.3a2.6 2.6 0 0 1 4.9.9c0 1.7-2.4 2-2.4 3.3"/><circle cx="12" cy="17" r=".4" fill="currentColor"/></svg></span>
          <span><b>Frequently Asked Questions</b><small class="muted" style="display:block;font-size:12.5px">Find quick answers to common questions.</small></span>
        </div>
        <?php foreach ($faqs as [$qn, $an]): ?>
          <details class="faq-row">
            <summary>
              <span class="kv-ic" style="background:#eaf1fe;color:#0b5cff"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.3a2.6 2.6 0 0 1 4.9.9c0 1.7-2.4 2-2.4 3.3"/><circle cx="12" cy="17" r=".4" fill="currentColor"/></svg></span>
              <b><?= e($qn) ?></b><span class="chev" aria-hidden="true">&#8250;</span>
            </summary>
            <p class="muted"><?= e($an) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
      <div class="card card-pad rail-card">
        <div class="rail-head">
          <span class="stat-ic" style="background:#12a15f;color:#fff;width:40px;height:40px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.5 19.5 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.34 1.84.57 2.8.7a2 2 0 0 1 1.7 2z"/></svg></span>
          <span><b>Need More Help?</b><small class="muted" style="display:block;font-size:12.5px">Get in touch with our support team.</small></span>
        </div>
        <div class="help-line">
          <span class="kv-ic" style="background:#f1e8fd;color:#7c3aed"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.5 19.5 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.34 1.84.57 2.8.7a2 2 0 0 1 1.7 2z"/></svg></span>
          <span><b class="num">1800 200 2424</b><small>9 AM - 9 PM, all days</small></span>
        </div>
        <div class="help-line">
          <span class="kv-ic" style="background:#eaf1fe;color:#0b5cff"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></span>
          <span><b>care@drive24.in</b><small>We reply within 24 hours</small></span>
        </div>
        <a class="live-chat" href="#" data-soon="Live chat is coming soon.">
          <span class="kv-ic" style="background:#d7e6fd;color:#0b5cff"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13a8 8 0 0 1 16 0"/><rect x="2" y="13" width="4" height="7" rx="1.5"/><rect x="18" y="13" width="4" height="7" rx="1.5"/><path d="M20 20a4 4 0 0 1-4 2h-2"/></svg></span>
          <span><b>Live Chat</b><small>Chat with our support team</small></span>
        </a>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
