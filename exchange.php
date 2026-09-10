<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$listingId = (int) ($_GET['listing'] ?? $_POST['listing_id'] ?? 0);
$target = $listingId > 0 ? findListing($listingId) : null;
$quote = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $make = trim((string) ($_POST['make'] ?? ''));
    $model = trim((string) ($_POST['model'] ?? ''));
    $year = (int) ($_POST['year'] ?? date('Y'));
    $km = (int) ($_POST['km_driven'] ?? 0);
    $fuel = (string) ($_POST['fuel_type'] ?? 'Petrol');
    $avg = (float) fetchValue('SELECT AVG(l.price) FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE v.make = ?', [$make], 0);
    if ($avg <= 0) { $avg = (float) fetchValue('SELECT AVG(price) FROM listings', [], 800000); }
    $ageFactor = max(0.45, 1 - (((int) date('Y') - $year) * 0.07));
    $kmFactor = max(0.6, 1 - ($km / 250000));
    $fair = $avg * $ageFactor * $kmFactor;
    $bonus = min(20000, max(5000, (int) round($fair * 0.02)));
    $quote = ['low' => $fair * 0.94, 'high' => $fair * 1.06, 'fair' => $fair, 'bonus' => $bonus,
        'make' => $make, 'model' => $model, 'year' => $year, 'km' => $km];
    insert('leads', [
        'name' => $u['name'], 'mobile' => (string) ($u['mobile'] ?? ''), 'city' => trim((string) ($_POST['city'] ?? '')),
        'make' => $make, 'model' => $model, 'year' => $year, 'km_driven' => $km, 'fuel_type' => $fuel,
        'quote_low' => round($quote['low']), 'quote_high' => round($quote['high']), 'status' => 'new',
    ]);
    notifyAdmins('Exchange enquiry', $u['name'] . ': ' . $year . ' ' . $make . ' ' . $model, 'admin/customers.php');
    flash('success', 'Exchange quote generated. Our evaluator will call you within 2 hours to confirm.');
}

renderHeader('Exchange your car', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">Exchange your old car</h1>
  <?php if ($target): ?>
    <p class="muted">You are buying the <a href="<?= e(base('car.php?id=' . $target['id'])) ?>"><b><?= e(vehicleTitle($target)) ?></b></a>
      (<?= rupees($target['price']) ?>). An extra exchange bonus up to <b>&#8377;20,000</b> applies on this car.</p>
  <?php else: ?>
    <p class="muted">Get an instant quote for your current car and put it towards any car on DRIVE24, with a bonus up to <b>&#8377;20,000</b>.</p>
  <?php endif; ?>
  <div class="split-3">
    <div class="card card-pad">
      <h2 style="font-size:1.2rem">Your current car</h2>
      <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <?= csrfField() ?><input type="hidden" name="listing_id" value="<?= $listingId ?>">
        <div><label class="form-label">Brand</label><input class="form-control" name="make" required placeholder="Hyundai"></div>
        <div><label class="form-label">Model</label><input class="form-control" name="model" required placeholder="i20"></div>
        <div><label class="form-label">Year</label><input class="form-control num" type="number" name="year" value="2019" min="2000" max="<?= date('Y') ?>" required></div>
        <div><label class="form-label">KM driven</label><input class="form-control num" type="number" name="km_driven" value="48000" required></div>
        <div><label class="form-label">Fuel</label><select class="form-select" name="fuel_type"><option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option><option>Hybrid</option></select></div>
        <div><label class="form-label">City</label><input class="form-control" name="city" value="<?= e((string) ($u['city'] ?? '')) ?>" required></div>
        <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Get exchange quote</button></div>
      </form>
      <?php if ($quote): ?>
        <div class="card card-pad" style="margin-top:16px;background:var(--surface-low);border-color:#bfdbfe">
          <h3 style="font-size:1.05rem">Quote for your <?= e($quote['year'] . ' ' . $quote['make'] . ' ' . $quote['model']) ?></h3>
          <div class="num" style="font-size:1.8rem;font-weight:800"><?= rupees($quote['low']) ?> &ndash; <?= rupees($quote['high']) ?></div>
          <div class="kv"><span>Exchange bonus</span><b class="num">+ <?= rupees($quote['bonus']) ?></b></div>
          <div class="kv"><span>Effective value</span><b class="num"><?= rupees($quote['high'] + $quote['bonus']) ?></b></div>
          <p class="muted" style="font-size:13px">Benchmarked against live DRIVE24 inventory prices. The quote and bonus stay locked for 7 days after the evaluator confirms your car.</p>
          <?php if ($target): ?>
            <a class="btn btn-primary" href="<?= e(base('checkout.php?listing=' . $target['id'])) ?>">Continue buying the <?= e($target['model']) ?></a>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e(base('cars.php')) ?>">Browse cars to buy</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <aside class="card card-pad sticky">
      <h3 style="font-size:1.05rem">How exchange works</h3>
      <div class="kv"><span>1. Instant quote</span><span>Based on real market prices.</span></div>
      <div class="kv"><span>2. Free doorstep check</span><span>25-minute evaluation at your home.</span></div>
      <div class="kv"><span>3. Bonus</span><span>Up to &#8377;20,000 over market value.</span></div>
      <div class="kv"><span>4. Same-day swap</span><span>Old car picked up when the new one arrives.</span></div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
