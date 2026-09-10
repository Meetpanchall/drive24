<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$quote = null;
$created = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'valuation') {
        $make = trim((string) ($_POST['make'] ?? ''));
        $model = trim((string) ($_POST['model'] ?? ''));
        $year = (int) ($_POST['year'] ?? date('Y'));
        $km = (int) ($_POST['km_driven'] ?? 0);
        $fuel = (string) ($_POST['fuel_type'] ?? 'Petrol');

        // Benchmark against real inventory averages in MySQL
        $avg = (float) fetchValue('SELECT AVG(l.price) FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE v.make = ?', [$make], 0);
        if ($avg <= 0) { $avg = (float) fetchValue('SELECT AVG(price) FROM listings', [], 800000); }
        $ageFactor = max(0.45, 1 - (((int) date('Y') - $year) * 0.07));
        $kmFactor = max(0.6, 1 - ($km / 250000));
        $fair = $avg * $ageFactor * $kmFactor;

        $quote = ['low' => $fair * 0.94, 'high' => $fair * 1.06, 'fair' => $fair, 'make' => $make, 'model' => $model];

        insert('leads', [
            'name' => trim((string) ($_POST['name'] ?? '')), 'mobile' => trim((string) ($_POST['mobile'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')), 'make' => $make, 'model' => $model,
            'year' => $year, 'km_driven' => $km, 'fuel_type' => $fuel,
            'quote_low' => round($quote['low']), 'quote_high' => round($quote['high']), 'status' => 'new',
        ]);
        flash('success', 'Valuation generated and your enquiry was saved. Our advisor will call you.');
    }

    if ($action === 'listing') {
        $u = requireLogin();
        $vehicleId = insert('vehicles', [
            'make' => trim((string) $_POST['make']), 'model' => trim((string) $_POST['model']),
            'variant' => trim((string) ($_POST['variant'] ?? '')), 'year' => (int) $_POST['year'],
            'body_type' => (string) $_POST['body_type'], 'fuel_type' => (string) $_POST['fuel_type'],
            'transmission' => (string) $_POST['transmission'], 'km_driven' => (int) $_POST['km_driven'],
            'owners' => (int) ($_POST['owners'] ?? 1), 'color' => trim((string) ($_POST['color'] ?? '')),
            'reg_number' => trim((string) ($_POST['reg_number'] ?? '')), 'reg_state' => trim((string) ($_POST['reg_state'] ?? '')),
            'vin' => trim((string) ($_POST['vin'] ?? '')), 'engine_cc' => (int) ($_POST['engine_cc'] ?? 0),
            'power_bhp' => trim((string) ($_POST['power_bhp'] ?? '')), 'mileage_kmpl' => (float) ($_POST['mileage_kmpl'] ?? 0),
            'seats' => (int) ($_POST['seats'] ?? 5), 'city' => trim((string) ($_POST['city'] ?? '')),
            'image' => 'car' . random_int(1, 6) . '.svg',
            'description' => trim((string) ($_POST['description'] ?? '')),
        ]);
        $created = insert('listings', [
            'vehicle_id' => $vehicleId, 'seller_id' => $u['id'],
            'price' => (float) $_POST['price'], 'original_price' => (float) $_POST['price'],
            'status' => 'pending', 'certified' => 0, 'inspection_score' => 0,
        ]);
        logActivity((int) $u['id'], 'listing.created', 'Listing #' . $created);
        flash('success', 'Listing submitted. It goes live as soon as our team approves it.');
        redirect(base('seller/listings.php'));
    }
}

renderHeader('Sell your car', 'sell');
?>
<div class="wrap section">
  <h1 style="font-size:1.8rem">Sell your car at the best price</h1>
  <p class="muted">Instant AI valuation benchmarked against live DRIVE24 inventory, free doorstep inspection and same-day payment.</p>

  <div class="steps" style="margin:16px 0 22px">
    <div class="step done">1. Instant valuation</div>
    <div class="step active">2. Free inspection</div>
    <div class="step">3. Accept best offer</div>
    <div class="step">4. Payment &amp; RC transfer</div>
  </div>

  <div class="split-3">
    <div>
      <div class="card card-pad">
        <h2 style="font-size:1.2rem">Get an instant price estimate</h2>
        <form method="post" class="grid" style="grid-template-columns:repeat(3,1fr);gap:12px">
          <?= csrfField() ?><input type="hidden" name="action" value="valuation">
          <div><label class="form-label">Brand</label><input class="form-control" name="make" value="Hyundai" required></div>
          <div><label class="form-label">Model</label><input class="form-control" name="model" value="Creta" required></div>
          <div><label class="form-label">Year</label><input class="form-control num" type="number" name="year" value="2020" required></div>
          <div><label class="form-label">KM driven</label><input class="form-control num" type="number" name="km_driven" value="45000" required></div>
          <div><label class="form-label">Fuel</label><select class="form-select" name="fuel_type"><option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option><option>Hybrid</option></select></div>
          <div><label class="form-label">City</label><input class="form-control" name="city" value="Ahmedabad"></div>
          <div><label class="form-label">Your name</label><input class="form-control" name="name" required></div>
          <div><label class="form-label">Mobile</label><input class="form-control num" name="mobile" required></div>
          <div style="display:flex;align-items:flex-end"><button class="btn btn-primary btn-block" type="submit">Get price</button></div>
        </form>

        <?php if ($quote): ?>
          <div class="card card-pad" style="margin-top:16px;background:var(--surface-low);border-color:#bfdbfe">
            <h3 style="font-size:1.05rem">Estimated price for your <?= e($quote['make'] . ' ' . $quote['model']) ?></h3>
            <div class="num" style="font-size:1.8rem;font-weight:800"><?= rupees($quote['low']) ?> &ndash; <?= rupees($quote['high']) ?></div>
            <p class="muted" style="margin-top:6px">Fair market value <b class="num"><?= rupees($quote['fair']) ?></b>, calculated from average listed prices for this brand in our MySQL inventory adjusted for age and usage.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="card card-pad" style="margin-top:18px">
        <h2 style="font-size:1.2rem">List your car on the marketplace</h2>
        <p class="muted">Saved directly into the <code>vehicles</code> and <code>listings</code> tables with status <b>pending</b> until an admin approves it.</p>
        <?php if (user() === null): ?>
          <div class="alert info">Please <a href="<?= e(base('login.php')) ?>">sign in</a> or <a href="<?= e(base('register.php')) ?>">create a seller account</a> to publish a listing.</div>
        <?php endif; ?>
        <form method="post" class="grid" style="grid-template-columns:repeat(3,1fr);gap:12px">
          <?= csrfField() ?><input type="hidden" name="action" value="listing">
          <div><label class="form-label">Brand</label><input class="form-control" name="make" required></div>
          <div><label class="form-label">Model</label><input class="form-control" name="model" required></div>
          <div><label class="form-label">Variant</label><input class="form-control" name="variant"></div>
          <div><label class="form-label">Year</label><input class="form-control num" type="number" name="year" required></div>
          <div><label class="form-label">Body type</label><select class="form-select" name="body_type"><option>Hatchback</option><option>Sedan</option><option>SUV</option><option>MUV</option><option>Luxury</option></select></div>
          <div><label class="form-label">Fuel</label><select class="form-select" name="fuel_type"><option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option><option>Hybrid</option></select></div>
          <div><label class="form-label">Transmission</label><select class="form-select" name="transmission"><option>Manual</option><option>Automatic</option></select></div>
          <div><label class="form-label">KM driven</label><input class="form-control num" type="number" name="km_driven" required></div>
          <div><label class="form-label">Owners</label><input class="form-control num" type="number" name="owners" value="1"></div>
          <div><label class="form-label">Colour</label><input class="form-control" name="color"></div>
          <div><label class="form-label">Registration no.</label><input class="form-control" name="reg_number"></div>
          <div><label class="form-label">Reg. state</label><input class="form-control" name="reg_state"></div>
          <div><label class="form-label">VIN / chassis</label><input class="form-control" name="vin"></div>
          <div><label class="form-label">Engine (cc)</label><input class="form-control num" type="number" name="engine_cc"></div>
          <div><label class="form-label">Power</label><input class="form-control" name="power_bhp" placeholder="118 bhp"></div>
          <div><label class="form-label">Mileage (kmpl)</label><input class="form-control num" type="number" step="0.1" name="mileage_kmpl"></div>
          <div><label class="form-label">Seats</label><input class="form-control num" type="number" name="seats" value="5"></div>
          <div><label class="form-label">City</label><input class="form-control" name="city"></div>
          <div><label class="form-label">Expected price</label><input class="form-control num" type="number" name="price" required></div>
          <div style="grid-column:1/-1"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
          <div style="grid-column:1/-1"><button class="btn btn-dark btn-lg" type="submit">Submit listing for approval</button></div>
        </form>
      </div>
    </div>

    <aside class="card card-pad sticky">
      <h3 style="font-size:1.05rem">Why sell with DRIVE24</h3>
      <div class="kv"><span>Average selling time</span><b class="num">6 days</b></div>
      <div class="kv"><span>Payment</span><b>Same day, escrow backed</b></div>
      <div class="kv"><span>RC transfer</span><b>Handled by us</b></div>
      <div class="kv"><span>Listing fee</span><b>Free</b></div>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:12px" href="<?= e(base('services.php')) ?>">RTO &amp; ownership services</a>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
