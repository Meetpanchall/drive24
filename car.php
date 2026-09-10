<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $u = requireLogin();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'offer') {
        insert('offers', [
            'listing_id' => $id, 'buyer_id' => $u['id'],
            'amount' => (float) ($_POST['amount'] ?? 0),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'status' => 'new',
        ]);
        flash('success', 'Your offer was sent to the seller.');
    } elseif ($action === 'testdrive') {
        insert('test_drives', [
            'listing_id' => $id, 'user_id' => $u['id'],
            'mode' => ($_POST['mode'] ?? 'home') === 'hub' ? 'hub' : 'home',
            'slot_date' => (string) ($_POST['slot_date'] ?? date('Y-m-d')),
            'slot_time' => (string) ($_POST['slot_time'] ?? '11:00 AM'),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'status' => 'requested',
        ]);
        flash('success', 'Test drive requested. Our advisor will confirm the slot shortly.');
    }
    redirect(base('car.php?id=' . $id));
}

$car = findListing($id);
if ($car === null) {
    renderHeader('Car not found', 'cars');
    echo '<div class="wrap section"><div class="card card-pad empty">This car is no longer available. <a href="' . e(base('cars.php')) . '">Browse all cars</a>.</div></div>';
    renderFooter();
    exit;
}
q('UPDATE listings SET views = views + 1 WHERE id = ?', [$id]);
$inspection = fetchOne('SELECT * FROM inspections WHERE vehicle_id = ? ORDER BY id DESC', [(int) $car['vehicle_id']]);
$similar = fetchAll(LISTING_SELECT . " WHERE l.status = 'approved' AND l.id <> ? AND (v.body_type = ? OR v.make = ?) ORDER BY ABS(l.price - ?) LIMIT 3", [$id, $car['body_type'], $car['make'], (float) $car['price']]);
$wish = wishlistIds();
$cmp = compareIds();
$saved = in_array($id, $wish, true);
$emi = emiAmount((float) $car['price'] * 0.8, 9.5, 60);

renderHeader(vehicleTitle($car), 'cars');
?>
<div class="wrap section">
  <p class="muted" style="font-size:13px"><a href="<?= e(base('index.php')) ?>">Home</a> / <a href="<?= e(base('cars.php')) ?>">Used cars</a> / <?= e(vehicleTitle($car)) ?></p>
  <div class="split-3">
    <div>
      <div class="card" style="overflow:hidden">
        <img src="<?= e(listingImage($car)) ?>" alt="<?= e(vehicleTitle($car)) ?>" style="width:100%;aspect-ratio:16/10;object-fit:cover">
        <div class="card-pad">
          <h1 style="font-size:1.55rem"><?= e(vehicleTitle($car)) ?></h1>
          <div class="meta">
            <span><?= number_format((int) $car['km_driven']) ?> km</span><span><?= e((string) $car['fuel_type']) ?></span>
            <span><?= e((string) $car['transmission']) ?></span><span><?= (int) $car['owners'] ?> owner</span>
            <span><?= e((string) $car['city']) ?></span><span><?= (int) $car['views'] ?> views</span>
          </div>
          <p class="muted" style="margin-top:12px"><?= e((string) $car['description']) ?></p>
        </div>
      </div>

      <div class="card card-pad" style="margin-top:18px">
        <h2 style="font-size:1.2rem">Specifications</h2>
        <div class="spec-grid">
          <div><small>Registration</small><b class="num"><?= e((string) $car['reg_number']) ?></b></div>
          <div><small>Reg. state</small><b><?= e((string) $car['reg_state']) ?></b></div>
          <div><small>Engine</small><b class="num"><?= (int) $car['engine_cc'] ?> cc</b></div>
          <div><small>Power</small><b><?= e((string) $car['power_bhp']) ?></b></div>
          <div><small>Mileage</small><b class="num"><?= e((string) $car['mileage_kmpl']) ?> kmpl</b></div>
          <div><small>Seats</small><b class="num"><?= (int) $car['seats'] ?></b></div>
          <div><small>Colour</small><b><?= e((string) $car['color']) ?></b></div>
          <div><small>Insurance till</small><b class="num"><?= e(date('M Y', strtotime((string) $car['insurance_valid_till']))) ?></b></div>
          <div><small>VIN</small><b class="num"><?= e((string) $car['vin']) ?></b></div>
        </div>
      </div>

      <?php if ($inspection): ?>
      <div class="card card-pad" style="margin-top:18px">
        <h2 style="font-size:1.2rem">280-point inspection report</h2>
        <p class="muted">Inspected by <?= e((string) $inspection['inspector']) ?> on <?= e(date('d M Y', strtotime((string) $inspection['inspected_on']))) ?> &middot; <?= statusBadge((string) $inspection['status']) ?></p>
        <div class="spec-grid">
          <?php foreach (['engine_score' => 'Engine &amp; transmission', 'exterior_score' => 'Exterior &amp; body', 'interior_score' => 'Interior', 'electrical_score' => 'Electricals', 'tyres_score' => 'Tyres &amp; brakes'] as $key => $label): ?>
            <div><small><?= $label ?></small><b class="num"><?= (int) $inspection[$key] ?>/100</b></div>
          <?php endforeach; ?>
          <div><small>Overall score</small><b class="num"><?= (int) $inspection['score'] ?>/100</b></div>
        </div>
        <div class="kv" style="margin-top:10px"><span>Accident history</span><span><?= e((string) $inspection['accident_history']) ?></span></div>
        <div class="kv"><span>Engineer remarks</span><span><?= e((string) $inspection['remarks']) ?></span></div>
      </div>
      <?php endif; ?>

      <div class="card card-pad" style="margin-top:18px">
        <h2 style="font-size:1.2rem">Book a test drive</h2>
        <form method="post" class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
          <?= csrfField() ?><input type="hidden" name="action" value="testdrive">
          <div><label class="form-label">Mode</label><select class="form-select" name="mode"><option value="home">At my home</option><option value="hub">At DRIVE24 hub</option></select></div>
          <div><label class="form-label">Date</label><input class="form-control" type="date" name="slot_date" value="<?= e(date('Y-m-d', strtotime('+2 days'))) ?>" required></div>
          <div><label class="form-label">Time slot</label><select class="form-select" name="slot_time"><option>10:00 AM</option><option>11:00 AM</option><option>02:00 PM</option><option>04:30 PM</option><option>06:00 PM</option></select></div>
          <div><label class="form-label">Address / landmark</label><input class="form-control" name="address" placeholder="Where should we bring the car?"></div>
          <div style="grid-column:1/-1"><button class="btn btn-dark" type="submit">Request test drive</button></div>
        </form>
      </div>

      <?php if ($similar): ?>
        <h2 style="margin-top:26px;font-size:1.2rem">Similar cars</h2>
        <div class="grid cars"><?php foreach ($similar as $s) { carCard($s, $wish, $cmp); } ?></div>
      <?php endif; ?>
    </div>

    <aside>
      <div class="card card-pad sticky">
        <div class="price num" style="font-size:1.7rem;font-weight:800"><?= rupees($car['price']) ?></div>
        <?php if ($car['original_price'] && (float) $car['original_price'] > (float) $car['price']): ?>
          <div class="muted num" style="font-size:13px"><s><?= rupees($car['original_price']) ?></s> &middot; you save <?= rupees((float) $car['original_price'] - (float) $car['price']) ?></div>
        <?php endif; ?>
        <div class="muted num" style="font-size:13.5px;margin-top:4px">EMI from <b><?= rupees($emi) ?></b>/month</div>
        <div style="display:flex;gap:8px;margin-top:14px">
          <a class="btn btn-primary" style="flex:1" href="<?= e(base('checkout.php?listing=' . $id)) ?>">Book now</a>
          <button class="btn btn-outline fav <?= $saved ? 'on' : '' ?>" style="position:static;width:auto;border-radius:10px" type="button" data-wishlist="<?= $id ?>">&#10084; Save</button>
        </div>
        <button class="btn btn-outline btn-block btn-sm" style="margin-top:8px" type="button" data-compare="<?= $id ?>">Add to compare</button>
        <div class="kv" style="margin-top:12px"><span>Seller</span><span><?= e((string) ($car['seller_company'] ?: $car['seller_name'])) ?></span></div>
        <div class="kv"><span>Inspection score</span><span class="num"><?= (int) $car['inspection_score'] ?>/100</span></div>
        <div class="kv"><span>Certified</span><span><?= ((int) $car['certified'] === 1) ? statusBadge('verified') : statusBadge('pending') ?></span></div>
      </div>

      <div class="card card-pad" style="margin-top:18px" data-emi-price="<?= (int) $car['price'] ?>">
        <h3 style="font-size:1.05rem">EMI calculator</h3>
        <label class="form-label">Down payment</label>
        <input class="form-control num" type="number" data-emi-down value="<?= (int) ((float) $car['price'] * 0.2) ?>">
        <label class="form-label" style="margin-top:8px">Interest rate (%)</label>
        <input class="form-control num" type="number" step="0.1" data-emi-rate value="9.5">
        <label class="form-label" style="margin-top:8px">Tenure (months)</label>
        <select class="form-select" data-emi-tenure><option>36</option><option>48</option><option selected>60</option><option>72</option><option>84</option></select>
        <div class="kv" style="margin-top:10px"><span>Monthly EMI</span><b class="num" data-emi-out>-</b></div>
        <div class="kv"><span>Loan amount</span><span class="num" data-emi-principal>-</span></div>
        <div class="kv"><span>Total interest</span><span class="num" data-emi-interest>-</span></div>
        <a class="btn btn-outline btn-block btn-sm" style="margin-top:10px" href="<?= e(base('finance.php?listing=' . $id)) ?>">Compare lender offers</a>
      </div>

      <div class="card card-pad" style="margin-top:18px">
        <h3 style="font-size:1.05rem">Make an offer</h3>
        <form method="post">
          <?= csrfField() ?><input type="hidden" name="action" value="offer">
          <label class="form-label">Your offer</label>
          <input class="form-control num" type="number" name="amount" value="<?= (int) ((float) $car['price'] * 0.95) ?>" required>
          <label class="form-label" style="margin-top:8px">Message</label>
          <textarea class="form-control" name="message" rows="3" placeholder="Tell the seller why"></textarea>
          <button class="btn btn-dark btn-block" style="margin-top:10px" type="submit">Send offer</button>
        </form>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
