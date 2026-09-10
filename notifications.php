<?php
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
if (isset($_GET['read'])) {
    q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [(int) $_GET['read'], $u['id']]);
    $n = fetchOne('SELECT * FROM notifications WHERE id = ?', [(int) $_GET['read']]);
    redirect(base(ltrim((string) ($n['link'] ?? 'notifications.php'), '/') ?: 'notifications.php'));
}
if (isset($_GET['all'])) {
    q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$u['id']]);
    redirect(base('notifications.php'));
}
$onlyUnread = isset($_GET['unread']);
$rows = fetchAll('SELECT * FROM notifications WHERE user_id = ?' . ($onlyUnread ? ' AND is_read = 0' : '') . ' ORDER BY created_at DESC LIMIT 100', [$u['id']]);
function notifIcon(string $title): string
{
    $t = strtolower($title);
    if (str_contains($t, 'offer') || str_contains($t, 'bid') || str_contains($t, 'outbid')) { return '&#128176;'; }
    if (str_contains($t, 'test drive')) { return '&#128663;'; }
    if (str_contains($t, 'price') || str_contains($t, 'match')) { return '&#128200;'; }
    if (str_contains($t, 'order') || str_contains($t, 'deliver') || str_contains($t, 'return') || str_contains($t, 'payment') || str_contains($t, 'rc ')) { return '&#128230;'; }
    if (str_contains($t, 'kyc') || str_contains($t, 'document') || str_contains($t, 'listing') || str_contains($t, 'approv')) { return '&#128196;'; }
    if (str_contains($t, 'question') || str_contains($t, 'answer') || str_contains($t, 'message')) { return '&#128172;'; }
    if (str_contains($t, 'ticket') || str_contains($t, 'support')) { return '&#127911;'; }
    return '&#128276;';
}

renderHeader('Notifications', '');
?>
<div class="wrap section" style="max-width:820px">
  <div style="display:flex;align-items:center;gap:12px">
    <h1 style="font-size:1.6rem">Notifications</h1>
    <a class="chip <?= $onlyUnread ? '' : 'active' ?>" href="<?= e(base('notifications.php')) ?>">All</a>
    <a class="chip <?= $onlyUnread ? 'active' : '' ?>" href="<?= e(base('notifications.php?unread=1')) ?>">Unread</a>
    <a class="btn btn-ghost btn-sm" style="margin-left:auto" href="<?= e(base('notifications.php?all=1')) ?>">Mark all read</a>
  </div>
  <?php if (!$rows): ?><div class="card card-pad empty">You are all caught up - offers, test drives and order updates land here.</div><?php endif; ?>
  <?php foreach ($rows as $n): ?>
    <a class="card card-pad" style="display:block;margin-top:10px;<?= (int) $n['is_read'] ? '' : 'border-left:4px solid var(--primary);' ?>" href="<?= e(base('notifications.php?read=' . (int) $n['id'])) ?>">
      <b><?= notifIcon((string) $n['title']) ?> <?= e($n['title']) ?></b>
      <?php if ($n['body']): ?><div class="muted"><?= e($n['body']) ?></div><?php endif; ?>
      <small class="muted num"><?= e(date('d M Y, H:i', strtotime((string) $n['created_at']))) ?></small>
    </a>
  <?php endforeach; ?>
</div>
<?php renderFooter(); ?>
