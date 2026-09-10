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
    ['HDFC Bank', 8.95, 84, 'Instant digital sanction', 'H', '#004481'],
    ['ICICI Bank', 9.25, 72, 'Zero foreclosure after 12 EMIs', 'I', '#B02A30'],
    ['Kotak Mahindra', 9.60, 72, 'Up to 90% funding', 'K', '#0B4DA2'],
    ['Bajaj Finserv', 10.25, 60, 'Approval in 30 minutes', 'B', '#0B3C8A'],
];

renderHeader('Car finance and EMI calculator', 'finance');
?>
<div class="wrap section">
  <section class="fin-hero">
    <div class="fin-hero-txt">
      <span class="plan-pill"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01" stroke-linecap="round"/></svg> Plan Your Ride</span>
      <h1>Car finance &amp; <span class="blue">EMI calculator</span></h1>
      <p>Compare offers from 12+ lenders. EMI is computed on the server in PHP and updated live in the browser as you move the inputs.</p>
      <div class="fin-checks">
        <span><i>&#10003;</i> No hidden charges</span>
        <span><i>&#10003;</i> Multiple lenders</span>
        <span><i>&#10003;</i> Instant results</span>
      </div>
    </div>
    <div class="fin-hero-img"><img src="<?= e(base('assets/img/car1.jpg')) ?>" alt="Blue SUV"><span class="script">Your Dream Car<br>Closer</span></div>
  </section>

  <div class="fin-split">
    <div class="card card-pad fin-cal" data-emi-price="<?= (int) $price ?>">
      <div class="svc-block-head">
        <span class="svc-ic lg"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01" stroke-linecap="round"/></svg></span>
        <div><h2>Calculate Your EMI</h2><p class="muted">Enter the details below to get your estimated monthly EMI and see lender offers.</p></div>
      </div>
      <form method="get" class="grid fin-form">
        <div><label class="form-label">Choose a car from inventory</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16l1.5-4.5A2 2 0 0 1 8.4 10h7.2a2 2 0 0 1 1.9 1.5L19 16"/><path d="M4 16h16v3.5H4z"/><circle cx="8" cy="19.5" r="1.4"/><circle cx="16" cy="19.5" r="1.4"/></svg>
          <select class="form-select" name="listing" data-autosubmit>
            <option value="0">Custom amount</option>
            <?php foreach ($cars as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $listingId === (int) $c['id'] ? 'selected' : '' ?>><?= e(vehicleTitle($c)) ?> &mdash; <?= rupees($c['price']) ?></option>
            <?php endforeach; ?>
          </select></div></div>
        <div><label class="form-label">Car price (&#8377;)</label>
          <div class="in-wrap"><span class="in-sym">&#8377;</span><input class="form-control num" type="number" name="price" value="<?= (int) $price ?>"></div></div>
        <div><label class="form-label">Down payment (&#8377;)</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 7v10M16 13h5v4h-5a2 2 0 0 1 0-4z" stroke-linejoin="round"/><circle cx="17.5" cy="15" r=".8" fill="currentColor"/></svg><input class="form-control num" type="number" name="down" value="<?= (int) $down ?>" data-emi-down></div></div>
        <div><label class="form-label">Interest rate (%)</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/></svg><input class="form-control num" type="number" step="0.05" name="rate" value="<?= e((string) $rate) ?>" data-emi-rate></div></div>
        <div><label class="form-label">Tenure (months)</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke-linecap="round"/></svg><select class="form-select" name="tenure" data-emi-tenure>
          <?php foreach ([36, 48, 60, 72, 84] as $t): ?><option <?= $tenure === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div></div>
        <div class="full"><button class="btn-create" type="submit"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01" stroke-linecap="round"/></svg> Recalculate on server <span aria-hidden="true">&rarr;</span></button></div>
      </form>

      <div class="emi-result">
        <div class="emi-top"><span class="emi-ic"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 7v10M16 13h5v4h-5a2 2 0 0 1 0-4z" stroke-linejoin="round"/></svg></span>
          <div><small>Monthly EMI</small><div class="num emi-big" data-emi-out><?= rupees($emi) ?></div></div></div>
        <div class="emi-grid">
          <div><span class="k">Loan amount</span><b class="num" data-emi-principal><?= rupees($price - $down) ?></b></div>
          <div><span class="k">Total payable</span><b class="num" data-emi-total><?= rupees($emi * $tenure) ?></b></div>
          <div><span class="k">Total interest</span><b class="num" data-emi-interest><?= rupees(($emi * $tenure) - ($price - $down)) ?></b></div>
          <div><span class="k">Down payment</span><b class="num" data-emi-echo="down"><?= rupees($down) ?></b></div>
          <div><span class="k">Tenure</span><b class="num" data-emi-echo="tenure"><?= $tenure ?> months</b></div>
        </div>
      </div>
      <div class="pre-strip"><span class="svc-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 3v6c0 5-3.2 8.6-7 11-3.8-2.4-7-6-7-11V5z"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Get pre-approved in minutes and drive your dream car today!</span></div>
    </div>

    <aside class="fin-aside">
      <div class="card card-pad">
        <h3><span class="star">&#9733;</span> Top Lender Offers</h3>
        <?php foreach ($lenders as [$name, $r, $maxT, $note, $letter, $color]): $le = emiAmount($price - $down, $r, min($tenure, $maxT)); ?>
          <a class="lender" href="<?= e(base('loan-apply.php' . ($car ? '?listing=' . (int) $car['id'] : ''))) ?>">
            <span class="lender-logo" style="background:<?= $color ?>"><?= $letter ?></span>
            <span class="lender-txt"><b><?= e($name) ?></b><small><?= e((string) $r) ?>% &middot; up to <?= $maxT ?> months &middot; <?= e($note) ?></small></span>
            <b class="num"><?= rupees($le) ?>/mo</b><span class="chev">&rsaquo;</span>
          </a>
        <?php endforeach; ?>
        <a class="btn-create" style="margin-top:12px" href="<?= e($car ? base('checkout.php?listing=' . (int) $car['id']) : base('cars.php')) ?>"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-6 9 6M4 9v10M20 9v10M8 12v5M12 12v5M16 12v5M2 21h20"/></svg> Apply with this car</a>
      </div>
      <div class="card card-pad">
        <h3><span class="svc-ic sm"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 2h7l5 5v15H7z" stroke-linejoin="round"/><path d="M14 2v5h5" /></svg></span> Documents needed</h3>
        <div class="doc-row"><span class="doc-ic" style="color:#7c3aed;background:#f1e8ff"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-5.5 8-5.5s6.5 1.5 8 5.5" stroke-linecap="round"/></svg></span><span>Identity</span><b>PAN + Aadhaar</b></div>
        <div class="doc-row"><span class="doc-ic" style="color:#d97706;background:#fef3e2"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v10M12 17.5h.01M10.3 5.2L2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 5.2a2 2 0 0 0-3.4 0z" stroke-linejoin="round"/></svg></span><span>Income</span><b>3 salary slips / ITR</b></div>
        <div class="doc-row"><span class="doc-ic" style="color:#059669;background:#e6f7ef"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4" stroke-linecap="round"/></svg></span><span>Banking</span><b>6 month statement</b></div>
        <div class="doc-row"><span class="doc-ic" style="color:#7c3aed;background:#f1e8ff"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span>Approval time</span><b>24 hours</b></div>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
