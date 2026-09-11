<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);
$seller = fetchOne("SELECT * FROM users WHERE id = ? AND role IN ('seller','dealer') AND status = 'active'", [$id]);
if (!$seller) {
    renderHeader('Seller not found', '');
    echo '<div class="wrap section"><div class="card card-pad empty">This seller profile is unavailable. <a href="' . e(base('cars.php')) . '">Browse all cars</a>.</div></div>';
    renderFooter();
    exit;
}
$rate = sellerRating($id);
$live = fetchAll(LISTING_SELECT . " WHERE l.seller_id = ? AND l.status = 'approved' ORDER BY l.id DESC LIMIT 12", [$id]);
$sold = (int) fetchValue("SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'sold'", [$id], 0);
$reviews = fetchAll("SELECT r.*, u.name AS author FROM reviews r JOIN users u ON u.id = r.author_id
    WHERE r.target_user_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 10", [$id]);
$wish = wishlistIds();
$cmp = compareIds();

renderHeader(($seller['company'] ?: $seller['name']), '');
?>
<div class="wrap section">
  <div class="card card-pad" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <div style="width:72px;height:72px;border-radius:50%;background:#0b5cff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800">
      <?= e(mb_strtoupper(mb_substr((string) ($seller['company'] ?: $seller['name']), 0, 1))) ?>
    </div>
    <div style="flex:1;min-width:220px">
      <h1 style="font-size:1.5rem;margin:0"><?= e((string) ($seller['company'] ?: $seller['name'])) ?></h1>
      <div class="muted"><?= e((string) ($seller['city'] ?: 'India')) ?> &middot; member since <?= e(date('M Y', strtotime((string) $seller['created_at']))) ?></div>
      <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap">
        <?= $seller['kyc_status'] === 'verified' ? statusBadge('verified') : statusBadge('pending') ?>
        <span class="muted" style="font-size:13px"><?= $seller['kyc_status'] === 'verified' ? 'KYC verified' : 'KYC pending' ?></span>
      </div>
    </div>
    <div class="kpis" style="margin:0">
      <div class="kpi"><small>Rating</small><b class="num"><?= $rate['count'] ? '★ ' . $rate['avg'] : '-' ?></b></div>
      <div class="kpi"><small>Reviews</small><b class="num"><?= $rate['count'] ?></b></div>
      <div class="kpi"><small>Cars live</small><b class="num"><?= count($live) ?></b></div>
      <div class="kpi"><small>Cars sold</small><b class="num"><?= $sold ?></b></div>
    </div>
  </div>

  <h2 style="font-size:1.2rem;margin:22px 0 12px">Cars by this seller</h2>
  <?php if (!$live): ?><div class="card card-pad empty">No live cars right now.</div>
  <?php else: ?><div class="grid cars"><?php foreach ($live as $r) { carCard($r, $wish, $cmp); } ?></div><?php endif; ?>

  <h2 style="font-size:1.2rem;margin:22px 0 12px">Buyer reviews</h2>
  <?php if (!$reviews): ?><div class="card card-pad empty">No reviews yet.</div><?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <div class="card card-pad" style="margin-bottom:10px">
      <b>★ <?= (int) $r['rating'] ?>/5</b> &middot; <?= e((string) $r['author']) ?>
      <span class="muted num" style="font-size:12px"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></span>
      <?php if (!empty($r['title'])): ?><div><b><?= e((string) $r['title']) ?></b></div><?php endif; ?>
      <?php if (!empty($r['comment'])): ?><div class="muted"><?= e((string) $r['comment']) ?></div><?php endif; ?>
      <div style="margin-top:6px"><a class="btn btn-ghost btn-sm" href="<?= e(base('car.php?id=' . (int) $r['listing_id'])) ?>">View car</a></div>
    </div>
  <?php endforeach; ?>
</div>
<?php renderFooter(); ?>
