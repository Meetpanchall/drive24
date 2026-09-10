<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, ['pending', 'approved', 'rejected'], true)) {
        updateRow('reviews', ['status' => $status], 'id = ?', [$id]);
        logActivity((int) $admin['id'], 'review.moderated', '#' . $id . ' -> ' . $status);
        flash('success', 'Review #' . $id . ' marked ' . $status . '.');
    }
    redirect(base('admin/reviews.php'));
}

$rows = [];
try {
    $rows = fetchAll('SELECT r.*, v.make, v.model, v.year, a.name AS author, t.name AS target
        FROM reviews r JOIN listings l ON l.id = r.listing_id JOIN vehicles v ON v.id = l.vehicle_id
        JOIN users a ON a.id = r.author_id JOIN users t ON t.id = r.target_user_id
        ORDER BY FIELD(r.status, "pending", "approved", "rejected"), r.id DESC');
} catch (Throwable $e) { /* table created on next request */ }

adminHeader('Reviews and ratings', 'reviews');
?>
<p class="muted">Buyer and seller reviews go live on car pages and profiles only after approval here.</p>
<div class="table-wrap"><table class="data">
  <thead><tr><th>#</th><th>Car</th><th>From &rarr; To</th><th>Rating</th><th>Review</th><th>Status</th><th>Moderate</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">No reviews submitted yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= (int) $r['id'] ?></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td><small><?= e((string) $r['author']) ?> (<?= e((string) $r['reviewer_role']) ?>)<br>&rarr; <?= e((string) $r['target']) ?></small></td>
      <td class="num" style="color:#b45309"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></td>
      <td><b><?= e((string) ($r['title'] ?? '')) ?></b><div class="muted"><?= e((string) ($r['comment'] ?? '')) ?></div></td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select><button class="btn btn-dark btn-sm" type="submit">Save</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
