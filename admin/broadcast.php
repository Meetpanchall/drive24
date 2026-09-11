<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$counts = [
    'all'    => (int) fetchValue("SELECT COUNT(*) FROM users WHERE status = 'active'", [], 0),
    'buyer'  => (int) fetchValue("SELECT COUNT(*) FROM users WHERE status = 'active' AND role = 'buyer'", [], 0),
    'seller' => (int) fetchValue("SELECT COUNT(*) FROM users WHERE status = 'active' AND role = 'seller'", [], 0),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $link = trim((string) ($_POST['link'] ?? ''));
    $aud = (string) ($_POST['audience'] ?? 'all');
    if ($title === '' || $body === '') {
        flash('error', 'Title and message are required.');
    } elseif (!isset($counts[$aud])) {
        flash('error', 'Unknown audience.');
    } else {
        $where = $aud === 'all' ? "status = 'active'" : "status = 'active' AND role = '" . $aud . "'";
        $n = q("INSERT INTO notifications (user_id, title, body, link) SELECT id, ?, ?, ? FROM users WHERE $where", [$title, $body, $link])->rowCount();
        logActivity((int) $admin['id'], 'broadcast.sent', $aud . ' x' . $n . ': ' . substr($title, 0, 60));
        flash('success', 'Broadcast sent to ' . $n . ' ' . ($aud === 'all' ? 'users' : $aud . 's') . '.');
    }
    redirect(base('admin/broadcast.php'));
}

$recent = fetchAll('SELECT n.*, u.name, u.role FROM notifications n JOIN users u ON u.id = n.user_id ORDER BY n.id DESC LIMIT 30');
adminHeader('Broadcast', 'broadcast');
?>
<p class="muted">One message to many inboxes - offers, downtime, policy changes. Only active users receive it; suspended accounts are skipped.</p>
<form method="post" class="card card-pad" style="max-width:640px">
  <?= csrfField() ?>
  <div style="margin-bottom:12px">
    <label class="form-label">Audience</label>
    <select class="form-select" name="audience">
      <option value="all">Everyone active (<?= $counts['all'] ?>)</option>
      <option value="buyer">Buyers (<?= $counts['buyer'] ?>)</option>
      <option value="seller">Sellers (<?= $counts['seller'] ?>)</option>
    </select>
  </div>
  <div style="margin-bottom:12px">
    <label class="form-label">Title</label>
    <input class="form-control" name="title" maxlength="160" required placeholder="e.g. Festive offer: free doorstep test drives">
  </div>
  <div style="margin-bottom:12px">
    <label class="form-label">Message</label>
    <textarea class="form-control" name="body" rows="3" maxlength="400" required placeholder="Keep it short - it lands in the notification bell."></textarea>
  </div>
  <div style="margin-bottom:12px">
    <label class="form-label">Link (optional)</label>
    <input class="form-control" name="link" maxlength="255" placeholder="e.g. cars.php or rent.php">
  </div>
  <button class="btn btn-primary" type="submit" onclick="return confirm('Send this broadcast now?')">Send broadcast</button>
</form>

<h2 style="font-size:1.15rem;margin-top:26px">Latest notifications</h2>
<div class="table-wrap"><table class="data">
  <thead><tr><th>To</th><th>Title</th><th>Message</th><th>Sent</th></tr></thead>
  <tbody>
  <?php if (!$recent): ?><tr><td colspan="4" class="empty">Nothing sent yet.</td></tr><?php endif; ?>
  <?php foreach ($recent as $n): ?>
    <tr>
      <td><?= e((string) $n['name']) ?> <small class="muted">(<?= e((string) $n['role']) ?>)</small></td>
      <td><?= e((string) $n['title']) ?></td>
      <td><?= e((string) ($n['body'] ?? '')) ?></td>
      <td class="num" style="white-space:nowrap"><?= e(date('d M H:i', strtotime((string) $n['created_at']))) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
