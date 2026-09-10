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
  <h1 style="font-size:1.7rem">Help &amp; support</h1>
  <div class="split-3">
    <div class="card card-pad">
      <h2 style="font-size:1.15rem">Raise a ticket</h2>
      <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <?= csrfField() ?>
        <div><label class="form-label">Category</label><select class="form-select" name="category">
          <option value="order">Order / delivery</option><option value="payment">Payment or refund</option>
          <option value="rc">RC transfer</option><option value="listing">My listing</option><option value="general">General</option>
        </select></div>
        <div><label class="form-label">Subject</label><input class="form-control" name="subject" required></div>
        <div><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
        <div style="grid-column:1/-1"><label class="form-label">Describe the issue</label><textarea class="form-control" name="message" rows="4" required></textarea></div>
        <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Submit ticket</button></div>
      </form>

      <?php if ($tickets): ?>
        <h2 style="font-size:1.15rem;margin-top:20px">My tickets</h2>
        <div class="table-wrap" style="border:0"><table class="data">
          <thead><tr><th>Ticket</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th></tr></thead>
          <tbody><?php foreach ($tickets as $t): ?>
            <tr><td class="num"><?= e(refCode('TKT', (int) $t['id'])) ?></td><td><?= e($t['subject']) ?></td>
              <td><?= e(ucfirst((string) $t['category'])) ?></td><td><?= e(ucfirst((string) ($t['priority'] ?? 'medium'))) ?></td><td><?= statusBadge((string) $t['status']) ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>
    <aside>
      <div class="card card-pad">
        <h3 style="font-size:1.05rem">Frequently asked</h3>
        <?php foreach ($faqs as [$qn, $an]): ?>
          <div style="border-bottom:1px solid var(--line);padding:9px 0"><b><?= e($qn) ?></b><div class="muted" style="font-size:13.4px"><?= e($an) ?></div></div>
        <?php endforeach; ?>
      </div>
      <div class="card card-pad" style="margin-top:18px">
        <h3 style="font-size:1.05rem">Talk to us</h3>
        <div class="kv"><span>Phone</span><span class="num">1800 200 2424</span></div>
        <div class="kv"><span>Email</span><span>care@drive24.in</span></div>
        <div class="kv"><span>Hours</span><span>9 AM - 9 PM, all days</span></div>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
