<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$listingId = (int) ($_GET['listing'] ?? 0);
$car = $listingId ? findListing($listingId) : null;
$price = $car ? (float) $car['price'] : (float) ($_GET['price'] ?? 1000000);
$down = (float) ($_GET['down'] ?? $price * 0.2);
$rate = (float) ($_GET['rate'] ?? 9.5);
$tenure = (int) ($_GET['tenure'] ?? 60);
$emi = emiAmount($price - $down, $rate, $tenure);
$cars = dbReady() ? fetchAll(LISTING_SELECT . " WHERE l.status = 'approved' ORDER BY l.price LIMIT 8") : [];

$lenders = [
    ['HDFC Bank', 8.95, 84, 'Instant digital sanction'],
    ['ICICI Bank', 9.25, 72, 'Zero foreclosure after 12 EMIs'],
    ['Kotak Mahindra', 9.60, 72, 'Up to 90% funding'],
    ['Bajaj Finserv', 10.25, 60, 'Approval in 30 minutes'],
];

renderHeader('Car finance and EMI calculator', 'finance');
?>
<div class="wrap section">
  <h1 style="font-size:1.7rem">Car finance &amp; EMI calculator</h1>
  <p class="muted">Compare offers from 12+ lenders. EMI is computed on the server in PHP and updated live in the browser as you move the inputs.</p>

  <div class="split-3">
    <div class="card card-pad" data-emi-price="<?= (int) $price ?>">
      <form method="get" class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
        <div style="grid-column:1/-1">
          <label class="form-label">Choose a car from inventory</label>
          <select class="form-select" name="listing" data-autosubmit>
            <option value="0">Custom amount</option>
            <?php foreach ($cars as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $listingId === (int) $c['id'] ? 'selected' : '' ?>><?= e(vehicleTitle($c)) ?> &mdash; <?= rupees($c['price']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">Car price</label><input class="form-control num" type="number" name="price" value="<?= (int) $price ?>"></div>
        <div><label class="form-label">Down payment</label><input class="form-control num" type="number" name="down" value="<?= (int) $down ?>" data-emi-down></div>
        <div><label class="form-label">Interest rate (%)</label><input class="form-control num" type="number" step="0.05" name="rate" value="<?= e((string) $rate) ?>" data-emi-rate></div>
        <div><label class="form-label">Tenure (months)</label><select class="form-select" name="tenure" data-emi-tenure>
          <?php foreach ([36, 48, 60, 72, 84] as $t): ?><option <?= $tenure === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
        <div style="grid-column:1/-1"><button class="btn btn-outline btn-block btn-sm" type="submit">Recalculate on server</button></div>
      </form>

      <div class="card card-pad" style="margin-top:16px;background:var(--surface-low);border-color:#bfdbfe">
        <small class="muted">Monthly EMI</small>
        <div class="num" style="font-size:2rem;font-weight:800" data-emi-out><?= rupees($emi) ?></div>
        <div class="kv"><span>Loan amount</span><span class="num" data-emi-principal><?= rupees($price - $down) ?></span></div>
        <div class="kv"><span>Total payable</span><span class="num" data-emi-total><?= rupees($emi * $tenure) ?></span></div>
        <div class="kv"><span>Total interest</span><span class="num" data-emi-interest><?= rupees(($emi * $tenure) - ($price - $down)) ?></span></div>
        <div class="kv"><span>Down payment</span><span class="num" data-emi-echo="down"><?= rupees($down) ?></span></div>
        <div class="kv"><span>Tenure</span><span class="num" data-emi-echo="tenure"><?= $tenure ?> months</span></div>
      </div>
    </div>

    <aside>
      <div class="card card-pad">
        <h3 style="font-size:1.05rem">Lender offers</h3>
        <?php foreach ($lenders as [$name, $r, $maxT, $note]): $le = emiAmount($price - $down, $r, min($tenure, $maxT)); ?>
          <div style="border-bottom:1px solid var(--line);padding:10px 0">
            <div style="display:flex;justify-content:space-between;gap:10px"><b><?= e($name) ?></b><b class="num"><?= rupees($le) ?>/mo</b></div>
            <div class="muted" style="font-size:12.6px"><?= e((string) $r) ?>% &middot; up to <?= $maxT ?> months &middot; <?= e($note) ?></div>
          </div>
        <?php endforeach; ?>
        <a class="btn btn-primary btn-block" style="margin-top:12px" href="<?= e($car ? base('checkout.php?listing=' . (int) $car['id']) : base('cars.php')) ?>">Apply with this car</a>
      </div>
      <div class="card card-pad" style="margin-top:18px">
        <h3 style="font-size:1.05rem">Documents needed</h3>
        <div class="kv"><span>Identity</span><span>PAN + Aadhaar</span></div>
        <div class="kv"><span>Income</span><span>3 salary slips / ITR</span></div>
        <div class="kv"><span>Banking</span><span>6 month statement</span></div>
        <div class="kv"><span>Approval time</span><span>24 hours</span></div>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
