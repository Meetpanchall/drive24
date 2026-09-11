<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

$id = (int) ($_GET['id'] ?? 0);
$car = fetchOne('SELECT l.*, v.* FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.id = ? AND l.seller_id = ?', [$id, $u['id']]);
if (!$car) { flash('error', 'Listing not found.'); redirect(base('seller/listings.php')); }
if (in_array($car['status'], ['sold', 'reserved'], true)) { flash('error', 'Sold listings cannot be edited.'); redirect(base('seller/listings.php')); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    updateRow('vehicles', [
        'make' => trim((string) $_POST['make']), 'model' => trim((string) $_POST['model']),
        'variant' => trim((string) ($_POST['variant'] ?? '')), 'year' => (int) $_POST['year'],
        'body_type' => (string) $_POST['body_type'], 'fuel_type' => (string) $_POST['fuel_type'],
        'transmission' => (string) $_POST['transmission'], 'km_driven' => (int) $_POST['km_driven'],
        'owners' => (int) ($_POST['owners'] ?? 1), 'color' => trim((string) ($_POST['color'] ?? '')),
        'reg_number' => trim((string) ($_POST['reg_number'] ?? '')), 'reg_state' => trim((string) ($_POST['reg_state'] ?? '')),
        'vin' => trim((string) ($_POST['vin'] ?? '')), 'engine_cc' => (int) ($_POST['engine_cc'] ?? 0),
        'power_bhp' => trim((string) ($_POST['power_bhp'] ?? '')), 'mileage_kmpl' => (float) ($_POST['mileage_kmpl'] ?? 0),
        'seats' => (int) ($_POST['seats'] ?? 5), 'city' => trim((string) ($_POST['city'] ?? '')),
        'area' => trim((string) ($_POST['area'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
    ], 'id = ?', [(int) $car['vehicle_id']]);

    // Photos: delete ticked ones, append new uploads (cap 8 total).
    foreach ((array) ($_POST['del_photo'] ?? []) as $pid) {
        q('DELETE FROM listing_images WHERE id = ? AND listing_id = ?', [(int) $pid, $id]);
    }
    $count = (int) fetchValue('SELECT COUNT(*) FROM listing_images WHERE listing_id = ?', [$id], 0);
    if (!empty($_FILES['photos']['name'][0] ?? '')) {
        $labels = ['Front three-quarter', 'Rear three-quarter', 'Interior', 'Odometer', 'VIN plate', 'Engine bay', 'Tyres', 'Extra'];
        $i = 0;
        foreach ($_FILES['photos']['name'] as $k => $nm) {
            if ($count >= 8) { break; }
            if ($nm === '') { continue; }
            $f = ['name' => $nm, 'type' => $_FILES['photos']['type'][$k], 'tmp_name' => $_FILES['photos']['tmp_name'][$k],
                'error' => $_FILES['photos']['error'][$k], 'size' => $_FILES['photos']['size'][$k]];
            $saved = saveUpload($f, 'car');
            if ($saved !== null) {
                insert('listing_images', ['listing_id' => $id, 'image' => $saved, 'label' => $labels[$count] ?? 'Photo ' . ($count + 1), 'sort_order' => $count]);
                $count++;
            }
            $i++;
        }
    }
    $first = fetchOne('SELECT image FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id LIMIT 1', [$id]);
    if ($first) { updateRow('vehicles', ['image' => 'uploads/' . $first['image']], 'id = ?', [(int) $car['vehicle_id']]); }

    $ldata = ['price' => (float) ($_POST['price'] ?? $car['price'])];
    if (!empty($_FILES['model_3d']['name'] ?? '')) {
        $m = saveModel3D($_FILES['model_3d']);
        if ($m !== null) { $ldata['model_3d'] = $m; }
    }
    if (!empty($_FILES['model_3d_interior']['name'] ?? '')) {
        $m = saveModel3D($_FILES['model_3d_interior']);
        if ($m !== null) { $ldata['model_3d_interior'] = $m; }
    }
    // Fixing a rejected listing resubmits it for approval automatically.
    if ($car['status'] === 'rejected') { $ldata['status'] = 'pending'; $ldata['rejection_note'] = null; }
    updateRow('listings', $ldata, 'id = ?', [$id]);
    logActivity((int) $u['id'], 'listing.updated', 'Listing #' . $id);
    flash('success', $car['status'] === 'rejected' ? 'Saved and resubmitted for approval.' : 'Listing updated.');
    redirect(base('seller/edit-listing.php?id=' . $id));
}

$car = fetchOne('SELECT l.*, v.* FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.id = ?', [$id]);
$photos = fetchAll('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$id]);
adminHeader('Edit listing', 'listings', 'seller');
$v = fn(string $k) => e((string) ($car[$k] ?? ''));
?>
<p class="muted">Listing <?= e(refCode('LST', $id)) ?> &middot; <?= statusBadge((string) $car['status']) ?>
  <?php if (!empty($car['rejection_note'])): ?><span class="muted"> - was rejected: <?= e((string) $car['rejection_note']) ?>. Saving resubmits it.</span><?php endif; ?>
  &middot; <a href="<?= e(base('car.php?id=' . $id)) ?>">Preview live page</a></p>
<form method="post" enctype="multipart/form-data" class="card card-pad grid" style="grid-template-columns:repeat(3,1fr);gap:12px">
  <?= csrfField() ?>
  <div><label class="form-label">Brand</label><input class="form-control" name="make" value="<?= $v('make') ?>" required></div>
  <div><label class="form-label">Model</label><input class="form-control" name="model" value="<?= $v('model') ?>" required></div>
  <div><label class="form-label">Variant</label><input class="form-control" name="variant" value="<?= $v('variant') ?>"></div>
  <div><label class="form-label">Year</label><input class="form-control num" type="number" name="year" value="<?= $v('year') ?>" required></div>
  <div><label class="form-label">Body type</label><select class="form-select" name="body_type"><?php foreach (['Hatchback', 'Sedan', 'SUV', 'MUV', 'Luxury'] as $o): ?><option <?= $car['body_type'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
  <div><label class="form-label">Fuel</label><select class="form-select" name="fuel_type"><?php foreach (['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'] as $o): ?><option <?= $car['fuel_type'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
  <div><label class="form-label">Transmission</label><select class="form-select" name="transmission"><?php foreach (['Manual', 'Automatic'] as $o): ?><option <?= $car['transmission'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
  <div><label class="form-label">KM driven</label><input class="form-control num" type="number" name="km_driven" value="<?= $v('km_driven') ?>" required></div>
  <div><label class="form-label">Owners</label><input class="form-control num" type="number" name="owners" value="<?= $v('owners') ?>"></div>
  <div><label class="form-label">Colour</label><input class="form-control" name="color" value="<?= $v('color') ?>"></div>
  <div><label class="form-label">Registration no.</label><input class="form-control" name="reg_number" value="<?= $v('reg_number') ?>"></div>
  <div><label class="form-label">Reg. state</label><input class="form-control" name="reg_state" value="<?= $v('reg_state') ?>"></div>
  <div><label class="form-label">VIN / chassis</label><input class="form-control" name="vin" value="<?= $v('vin') ?>"></div>
  <div><label class="form-label">Engine (cc)</label><input class="form-control num" type="number" name="engine_cc" value="<?= $v('engine_cc') ?>"></div>
  <div><label class="form-label">Power</label><input class="form-control" name="power_bhp" value="<?= $v('power_bhp') ?>"></div>
  <div><label class="form-label">Mileage (kmpl)</label><input class="form-control num" type="number" step="0.1" name="mileage_kmpl" value="<?= $v('mileage_kmpl') ?>"></div>
  <div><label class="form-label">Seats</label><input class="form-control num" type="number" name="seats" value="<?= $v('seats') ?>"></div>
  <div><label class="form-label">City</label><input class="form-control" name="city" value="<?= $v('city') ?>"></div>
  <div><label class="form-label">Area / locality</label><input class="form-control" name="area" value="<?= $v('area') ?>"></div>
  <div><label class="form-label">Price (Rs)</label><input class="form-control num" type="number" name="price" value="<?= (int) $car['price'] ?>" required></div>
  <div style="grid-column:1/-1"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= $v('description') ?></textarea></div>
  <div style="grid-column:1/-1">
    <label class="form-label">Photos (<?= count($photos) ?>/8 - tick to delete)</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
      <?php if (!$photos): ?><span class="muted">No photos yet.</span><?php endif; ?>
      <?php foreach ($photos as $ph): ?>
        <label style="text-align:center;font-size:12px">
          <img src="<?= e(base('assets/uploads/' . $ph['image'])) ?>" alt="" style="width:110px;height:70px;object-fit:cover;border-radius:8px;display:block">
          <input type="checkbox" name="del_photo[]" value="<?= (int) $ph['id'] ?>"> delete
        </label>
      <?php endforeach; ?>
    </div>
    <input class="form-control" type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple>
  </div>
  <div><label class="form-label">Exterior 3D <?= !empty($car['model_3d']) ? '(replace)' : '(none yet)' ?></label><input class="form-control" type="file" name="model_3d" accept=".glb,.gltf"></div>
  <div><label class="form-label">Interior 3D <?= !empty($car['model_3d_interior']) ? '(replace)' : '(none yet)' ?></label><input class="form-control" type="file" name="model_3d_interior" accept=".glb,.gltf"></div>
  <div style="display:flex;align-items:flex-end;gap:8px">
    <button class="btn btn-primary" type="submit">Save changes</button>
    <a class="btn btn-ghost" href="<?= e(base('seller/listings.php')) ?>">Back</a>
  </div>
</form>
<?php adminFooter(); ?>
