<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $listing = fetchOne('SELECT * FROM listings WHERE id = ?', [$id]);
    if ($listing) {
        if ($action === 'approve') {
            updateRow('listings', [
                'status' => 'approved',
                'inspection_score' => max(0, min(100, (int) ($_POST['score'] ?? 85))),
                'certified' => isset($_POST['certified']) ? 1 : 0,
                'featured' => isset($_POST['featured']) ? 1 : 0,
                'rejection_note' => null,
            ], 'id = ?', [$id]);
            notify((int) $listing['seller_id'], 'Listing approved', 'Your car is now live on DRIVE24.', 'seller/listings.php');
            alertSavedSearches($id);
            logActivity((int) $admin['id'], 'listing.approved', '#' . $id);
            flash('success', 'Listing #' . $id . ' is now live.');
        } elseif ($action === 'reject') {
            updateRow('listings', ['status' => 'rejected', 'rejection_note' => trim((string) ($_POST['reason'] ?? 'Incomplete details'))], 'id = ?', [$id]);
            notify((int) $listing['seller_id'], 'Listing needs changes', trim((string) ($_POST['reason'] ?? '')), 'seller/listings.php');
            logActivity((int) $admin['id'], 'listing.rejected', '#' . $id);
            flash('success', 'Listing #' . $id . ' rejected with a reason.');
        }
    }
    redirect(base('admin/approvals.php'));
}

$rows = fetchAll('SELECT l.*, v.make, v.model, v.variant, v.year, v.km_driven, v.fuel_type, v.transmission, v.city, v.vin, v.reg_number, u.name AS seller, u.role AS seller_role
    FROM listings l JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id
    WHERE l.status = ? ORDER BY l.created_at', ['pending']);

adminHeader('Listing approvals', 'approvals');
?>
<p class="muted">Every new seller submission waits here. Approving publishes it to buyers instantly; rejecting sends the reason back to the seller.</p>
<?php if (!$rows): ?><div class="card card-pad empty">Queue is clear - nothing waiting for review.</div><?php endif; ?>
<?php foreach ($rows as $r): ?>
<div class="card card-pad" style="margin-top:14px">
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start">
    <div style="flex:1;min-width:240px">
      <h2 style="font-size:1.15rem"><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model'] . ' ' . $r['variant']) ?></h2>
      <div class="meta"><span class="num"><?= rupees($r['price']) ?></span><span><?= number_format((int) $r['km_driven']) ?> km</span>
        <span><?= e((string) $r['fuel_type']) ?></span><span><?= e((string) $r['transmission']) ?></span><span><?= e((string) $r['city']) ?></span></div>
      <div class="muted" style="font-size:13px;margin-top:6px">Seller: <b><?= e((string) $r['seller']) ?></b> (<?= e((string) $r['seller_role']) ?>)
        &middot; VIN <span class="num"><?= e((string) ($r['vin'] ?: 'not provided')) ?></span>
        &middot; Reg <span class="num"><?= e((string) ($r['reg_number'] ?: '-')) ?></span></div>
    </div>
    <form method="post" class="card card-pad" style="background:var(--surface-low);min-width:280px;flex:1">
      <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
        <div><label class="form-label">Inspection score</label><input class="form-control num" type="number" min="0" max="100" name="score" value="85"></div>
        <div><label class="form-label">Reject reason</label><input class="form-control" name="reason" value="Photos or documents incomplete"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
        <label class="chip"><input type="checkbox" name="certified" value="1" checked> Certified</label>
        <label class="chip"><input type="checkbox" name="featured" value="1"> Featured</label>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button class="btn btn-ok btn-sm" type="submit" name="action" value="approve">Approve &amp; publish</button>
        <button class="btn btn-outline btn-sm" type="submit" name="action" value="reject">Reject</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php adminFooter(); ?>
