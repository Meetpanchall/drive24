<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = user();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $name = trim((string) ($_POST['name'] ?? ($u['name'] ?? '')));
    $email = trim((string) ($_POST['email'] ?? ($u['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($subject === '' || $message === '' || $name === '') {
        flash('error', 'Please fill your name, a subject and a message.');
    } else {
        insert('support_tickets', [
            'user_id' => $u['id'] ?? null,
            'subject' => mb_substr($subject, 0, 160),
            'category' => 'general', 'priority' => 'medium',
            'message' => "From: $name" . ($email !== '' ? " <$email>" : '') . ($phone !== '' ? " ($phone)" : '') . "\n\n" . mb_substr($message, 0, 2000),
            'status' => 'open',
        ]);
        notifyAdmins('Contact enquiry', $subject . ' - ' . $name, 'admin/support.php');
        flash('success', 'Message sent. We reply within 24 hours.');
        redirect(base('contact.php'));
    }
}

renderHeader('Contact us', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.7rem">Contact us</h1>
  <p class="muted">Talk to a human - sales, support, or anything in between.</p>
  <div class="split-3" style="margin-top:16px">
    <div>
      <div class="card card-pad">
        <h2 style="font-size:1.15rem">Send a message</h2>
        <form method="post">
          <?= csrfField() ?>
          <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
            <div><label class="form-label">Your name</label><input class="form-control" name="name" value="<?= e((string) ($u['name'] ?? '')) ?>" required></div>
            <div><label class="form-label">Phone</label><input class="form-control num" name="phone" value="<?= e((string) ($u['mobile'] ?? '')) ?>"></div>
          </div>
          <div style="margin-top:10px"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e((string) ($u['email'] ?? '')) ?>"></div>
          <div style="margin-top:10px"><label class="form-label">Subject</label><input class="form-control" name="subject" required maxlength="160"></div>
          <div style="margin-top:10px"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="4" required maxlength="2000"></textarea></div>
          <button class="btn btn-primary" style="margin-top:12px" type="submit">Send message</button>
        </form>
      </div>
    </div>
    <aside>
      <div class="card card-pad">
        <h2 style="font-size:1.15rem">Reach us directly</h2>
        <div class="kv"><span>Helpline</span><b class="num"><?= e((string) setting('helpline', '1800 200 2424')) ?></b></div>
        <div class="kv"><span>Hours</span><span><?= e((string) setting('helpline_hours', '9 AM - 9 PM, all days')) ?></span></div>
        <div class="kv"><span>Email</span><b><?= e((string) setting('support_email', 'care@drive24.in')) ?></b></div>
        <div class="kv"><span>Head office</span><span>DRIVE24 Motors, SG Highway, Ahmedabad 380015</span></div>
      </div>
      <div class="card card-pad" style="margin-top:14px">
        <h2 style="font-size:1.15rem">Prefer self-serve?</h2>
        <a class="btn btn-outline btn-block btn-sm" href="<?= e(base('support.php')) ?>">Help centre &amp; FAQs</a>
        <?php if ($u): ?><a class="btn btn-outline btn-block btn-sm" style="margin-top:8px" href="<?= e(base('support.php')) ?>">Track my tickets</a><?php endif; ?>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
