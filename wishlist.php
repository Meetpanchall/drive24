<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete_search') {
        q('DELETE FROM saved_searches WHERE id = ? AND user_id = ?', [(int) $_POST['search_id'], $u['id']]);
        flash('success', 'Saved search removed.');
    } elseif ($action === 'toggle_alert') {
        q('UPDATE saved_searches SET alert_enabled = 1 - alert_enabled WHERE id = ? AND user_id = ?', [(int) $_POST['search_id'], $u['id']]);
        flash('success', 'Alert preference updated.');
    }
    redirect(base('wishlist.php'));
}

$cars = fetchAll(LISTING_SELECT . ' JOIN wishlists w ON w.listing_id = l.id WHERE w.user_id = ? ORDER BY w.created_at DESC', [$u['id']]);
$searches = fetchAll('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC', [$u['id']]);
$wish = wishlistIds();
$cmp = compareIds();

renderHeader('Saved cars and alerts', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">Wishlist &amp; alerts</h1>
  <div class="split-3">
    <div>
      <h2 style="font-size:1.15rem">Saved cars (<?= count($cars) ?>)</h2>
      <?php if (!$cars): ?><div class="card card-pad empty">Nothing saved yet. <a href="<?= e(base('cars.php')) ?>">Browse cars</a> and tap the heart icon.</div><?php endif; ?>
      <div class="grid cars"><?php foreach ($cars as $r) { carCard($r, $wish, $cmp); } ?></div>
    </div>
    <aside class="card card-pad sticky">
      <h2 style="font-size:1.15rem">Saved searches</h2>
      <?php if (!$searches): ?><p class="muted">No saved searches. Save one from the filters on the buy page.</p><?php endif; ?>
      <?php foreach ($searches as $s): ?>
        <div style="border-bottom:1px solid var(--line);padding:10px 0">
          <a href="<?= e(base('cars.php?' . $s['query_string'])) ?>"><b><?= e($s['title']) ?></b></a>
          <div class="muted" style="font-size:12.4px"><?= e(str_replace('&', ' &middot; ', (string) $s['query_string'])) ?></div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="toggle_alert"><input type="hidden" name="search_id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-outline btn-sm"><?= ((int) $s['alert_enabled'] === 1) ? 'Alerts on' : 'Alerts off' ?></button></form>
            <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="delete_search"><input type="hidden" name="search_id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-ghost btn-sm">Delete</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
