<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$featured = dbReady() ? fetchAll(LISTING_SELECT . " WHERE l.status = 'approved' AND l.featured = 1 ORDER BY l.created_at DESC LIMIT 6") : [];
$recent   = dbReady() ? fetchAll(LISTING_SELECT . " WHERE l.status = 'approved' ORDER BY l.created_at DESC LIMIT 4") : [];
$stats = [
    'cars'    => dbReady() ? (int) fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'approved'") : 0,
    'cities'  => dbReady() ? (int) fetchValue("SELECT COUNT(DISTINCT v.city) FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.status = 'approved'") : 0,
    'orders'  => dbReady() ? (int) fetchValue('SELECT COUNT(*) FROM orders') : 0,
    'sellers' => dbReady() ? (int) fetchValue("SELECT COUNT(*) FROM users WHERE role IN ('seller','dealer')") : 0,
];
$auctions = dbReady() ? fetchAll(LISTING_SELECT . " WHERE l.status = 'approved' AND l.auction_enabled = 1 AND (l.auction_ends_at IS NULL OR l.auction_ends_at >= NOW()) ORDER BY l.auction_ends_at ASC LIMIT 3") : [];
$makes = filterOptions('make');
$bodies = filterOptions('body_type');
$cities = filterOptions('city');
$wish = wishlistIds();
$cmp = compareIds();

renderHeader('Buy and sell used cars online', 'home');
?>
<section class="hero hero-v2">
  <div class="blob b1" data-depth="26"></div>
  <div class="blob b2" data-depth="16"></div>
  <div class="blob b3" data-depth="36"></div>
  <div class="wrap">
    <div class="hero-grid">
      <div class="hero-copy">
        <span class="eyebrow"><span aria-hidden="true">&#9733;</span> India&apos;s Trusted Used-Car Marketplace</span>
        <h1>Buy a car you can <span class="hl">actually trust.</span></h1>
        <p>Every DRIVE24 car clears a 280-point inspection, comes with a verified RC and history report, doorstep test drive and a 5-day money-back guarantee.</p>
        <div class="hero-ctas">
          <a class="btn btn-primary btn-lg btn-shine" href="<?= e(base('cars.php')) ?>">Browse certified cars <span aria-hidden="true">&rarr;</span></a>
          <a class="btn btn-ghost-light btn-lg" href="<?= e(base('sell.php')) ?>">Sell your car</a>
        </div>
        <div class="ticks">
          <span><i>&#10003;</i>280-point inspection</span>
          <span><i>&#10003;</i>5-day money-back</span>
          <span><i>&#10003;</i>Free RC transfer</span>
        </div>
        <div class="hero-stats">
          <div><b class="num"><span data-count="<?= $stats['cars'] ?>">0</span>+</b><span>Certified cars live</span></div>
          <div><b class="num"><span data-count="<?= $stats['cities'] ?>">0</span></b><span>Cities served</span></div>
          <div><b class="num"><span data-count="<?= $stats['sellers'] ?>">0</span></b><span>Verified sellers</span></div>
          <div><b class="num"><span data-count="<?= $stats['orders'] ?>">0</span></b><span>Cars delivered</span></div>
        </div>
      </div>
      <div class="hero-visual" data-depth="8" aria-hidden="true">
        <div class="hero-frame">
          <img src="<?= e(base('assets/img/cars/car9.jpg')) ?>" alt="">
          <span class="hero-tag"><span class="live-dot"></span>280-point certified</span>
        </div>
        <div class="hero-chip hc1" data-depth="18">&#9733; 4.8 buyer rating<small>2,40,000+ verified reviews</small></div>
        <div class="hero-chip hc2" data-depth="26">EMI from &#8377;9,999/mo<small>12+ lender partners</small></div>
      </div>
    </div>

    <form class="search-panel" method="get" action="<?= e(base('cars.php')) ?>">
      <div class="search-grid">
        <div><label class="form-label">Keyword</label><input class="form-control" name="q" placeholder="Creta, Swift, Mumbai"></div>
        <div><label class="form-label">Brand</label><select class="form-select" name="make"><option value="">Any brand</option>
          <?php foreach ($makes as $m): ?><option><?= e((string) $m) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label">Body type</label><select class="form-select" name="body"><option value="">Any body</option>
          <?php foreach ($bodies as $b): ?><option><?= e((string) $b) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label">Budget up to</label><select class="form-select" name="max"><option value="">Any budget</option>
          <option value="500000">Under 5 Lakh</option><option value="800000">Under 8 Lakh</option>
          <option value="1200000">Under 12 Lakh</option><option value="1600000">Under 16 Lakh</option>
          <option value="2500000">Under 25 Lakh</option></select></div>
        <div style="display:flex;align-items:flex-end"><button class="btn btn-primary btn-block btn-lg btn-shine" type="submit"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="vertical-align:-3px"><circle cx="11" cy="11" r="6"/><path d="M15.5 15.5L20 20"/></svg> Search cars</button></div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <?php foreach (array_slice($cities, 0, 6) as $c): ?>
          <a class="chip" href="<?= e(base('cars.php?city=' . urlencode((string) $c))) ?>"><?= e((string) $c) ?></a>
        <?php endforeach; ?>
      </div>
    </form>
  </div>
</section>

<div class="wrap"><div class="marquee marquee-pause" aria-hidden="true"><div class="track">
  <?php $brands = ['Hyundai', 'Maruti Suzuki', 'Tata', 'Mahindra', 'Toyota', 'Honda', 'Kia', 'Skoda', 'MG', 'Volkswagen', 'Renault']; ?>
  <?php for ($rep = 0; $rep < 2; $rep++): foreach ($brands as $b): ?>
    <span><?= e($b) ?></span><span class="dot">&#9679;</span>
  <?php endforeach; endfor; ?>
</div></div></div>

<section class="wrap section">
  <div class="sec-head reveal">
    <span><small class="eyebrow-sec">Handpicked for you</small><h2 class="sec-title">Featured certified cars</h2></span>
    <a class="btn btn-ghost btn-sm" href="<?= e(base('cars.php')) ?>">View all inventory &rarr;</a>
  </div>
  <?php if (!$featured): ?><div class="card card-pad empty">No live listings yet. Import <code>database/schema.sql</code> to load demo inventory.</div><?php endif; ?>
  <div class="grid cars"><?php foreach ($featured as $r) { carCard($r, $wish, $cmp); } ?></div>
</section>

<?php if ($auctions): ?>
<section class="wrap section">
  <div class="sec-head reveal">
    <span><small class="eyebrow-sec"><span class="live-dot"></span> Bidding open now</small><h2 class="sec-title">Live auctions ending soon</h2></span>
    <a class="btn btn-ghost btn-sm" href="<?= e(base('cars.php')) ?>">All cars &rarr;</a>
  </div>
  <div class="grid cars"><?php foreach ($auctions as $r) { carCard($r, $wish, $cmp); } ?></div>
</section>
<?php endif; ?>

<section class="wrap section" id="how">
  <div class="sec-head reveal">
    <span><small class="eyebrow-sec">Simple &amp; secure</small><h2 class="sec-title">How it works</h2></span>
    <a class="btn btn-ghost btn-sm" href="<?= e(base('about.php')) ?>">Why DRIVE24 &rarr;</a>
  </div>
  <div class="how4 reveal">
    <div class="how-step">
      <div class="how-top"><span class="stat-ic" style="background:#0b5cff;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="6"/><path d="M15.5 15.5L20 20"/></svg></span><b style="color:#0b5cff">01</b></div>
      <b>Search &amp; compare</b>
      <small>Filter inspected cars by budget, brand and body type.</small>
    </div>
    <span class="how-arrow">&#8250;</span>
    <div class="how-step">
      <div class="how-top"><span class="stat-ic" style="background:#12b76a;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.4"/><path d="M12 4v3M5.5 16l2.6-1.5M18.5 16l-2.6-1.5"/></svg></span><b style="color:#12b76a">02</b></div>
      <b>Test drive at home</b>
      <small>Pick a slot and our executive brings the car over.</small>
    </div>
    <span class="how-arrow">&#8250;</span>
    <div class="how-step">
      <div class="how-top"><span class="stat-ic" style="background:#7c3aed;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/><path d="M9.5 12l2 2 3.5-3.5"/></svg></span><b style="color:#7c3aed">03</b></div>
      <b>Finance &amp; pay securely</b>
      <small>Compare 12+ lenders; money stays in escrow.</small>
    </div>
    <span class="how-arrow">&#8250;</span>
    <div class="how-step">
      <div class="how-top"><span class="stat-ic" style="background:#f97316;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 5h13v11H1zM14 9h4l3 3v4h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/></svg></span><b style="color:#f97316">04</b></div>
      <b>Delivery + RC transfer</b>
      <small>Doorstep delivery with free RC transfer.</small>
    </div>
  </div>
</section>

<section class="wrap section">
  <div class="sec-head reveal">
    <span><small class="eyebrow-sec">The DRIVE24 promise</small><h2 class="sec-title">Why 2 lakh+ buyers choose DRIVE24</h2></span>
  </div>
  <div class="grid four">
    <div class="card card-pad why-card"><span class="svc-ic lg" style="background:#e3f0fe;color:#0b5cff"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/><path d="M9.5 12l2 2 3.5-3.5"/></svg></span><h3>280-point inspection</h3><p class="muted">Engine, structure, electricals and tyres are scored by certified engineers before a car goes live.</p></div>
    <div class="card card-pad why-card"><span class="svc-ic lg" style="background:#e2f7ec;color:#12a15f"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12l-8 8-9-9V4h7z"/><circle cx="8.5" cy="8.5" r="1.4" fill="currentColor" stroke="none"/></svg></span><h3>Transparent pricing</h3><p class="muted">AI valuation benchmarked against live market data, no hidden charges at checkout.</p></div>
    <div class="card card-pad why-card"><span class="svc-ic lg" style="background:#f1e8fd;color:#7c3aed"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4 14h6l-1 8 9-12h-6z"/></svg></span><h3>Finance in 24 hours</h3><p class="muted">Compare offers from 12+ lenders with instant EMI eligibility right on the car page.</p></div>
    <div class="card card-pad why-card"><span class="svc-ic lg" style="background:#fef0dd;color:#f97316"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 13l2 2 4-4"/></svg></span><h3>RC transfer handled</h3><p class="muted">We complete RTO paperwork, insurance transfer and doorstep delivery end to end.</p></div>
  </div>
</section>

<section class="wrap section">
  <div class="split-3">
    <div>
      <div class="sec-head reveal">
        <span><small class="eyebrow-sec">Just arrived</small><h2 class="sec-title">Freshly added</h2></span>
        <a class="btn btn-ghost btn-sm" href="<?= e(base('cars.php')) ?>">View all &rarr;</a>
      </div>
      <div class="grid cars"><?php foreach ($recent as $r) { carCard($r, $wish, $cmp); } ?></div>
    </div>
    <aside class="card card-pad sticky sell-cta reveal-right">
      <span class="eyebrow">Sell in 3 steps</span>
      <h3>Sell your car the easy way</h3>
      <div class="sell-steps">
        <div><b>1</b><span>Get an instant AI price</span></div>
        <div><b>2</b><span>Free doorstep inspection</span></div>
        <div><b>3</b><span>Same-day payment &amp; RC transfer</span></div>
      </div>
      <a class="btn btn-light btn-block btn-shine" style="margin-top:14px" href="<?= e(base('sell.php')) ?>">Get car valuation</a>
      <a class="btn btn-ghost-light btn-block btn-sm" style="margin-top:8px" href="<?= e(base('finance.php')) ?>">Check EMI eligibility</a>
    </aside>
  </div>
</section>
<section class="wrap section">
  <div class="sec-head reveal">
    <span><small class="eyebrow-sec">2,40,000+ verified reviews</small><h2 class="sec-title">What our customers say</h2></span>
  </div>
  <div class="grid t-grid">
    <div class="card card-pad t-card reveal"><div class="t-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><p>&ldquo;The inspection report matched the car perfectly. RC transfer finished in 24 days without a single RTO visit.&rdquo;</p><div class="t-who"><span class="t-ava" style="background:#0b5cff">M</span><span><b>Meet P.</b><small class="muted">bought a Swift in Ahmedabad</small></span></div></div>
    <div class="card card-pad t-card reveal"><div class="t-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><p>&ldquo;Sold my i20 in one day - doorstep evaluation in the morning, money in the account by evening.&rdquo;</p><div class="t-who"><span class="t-ava" style="background:#12a15f">R</span><span><b>Riya S.</b><small class="muted">sold in Pune</small></span></div></div>
    <div class="card card-pad t-card reveal"><div class="t-stars">&#9733;&#9733;&#9733;&#9733;&#9734;</div><p>&ldquo;Loan approved in a day and the exchange bonus covered my insurance. Genuinely zero-hassle buying.&rdquo;</p><div class="t-who"><span class="t-ava" style="background:#7c3aed">K</span><span><b>Karan M.</b><small class="muted">bought a City in Delhi</small></span></div></div>
  </div>
</section>

<section class="wrap section">
  <div class="sec-head reveal">
    <span><small class="eyebrow-sec">Loans &amp; cover, sorted</small><h2 class="sec-title">Finance &amp; insurance partners</h2></span>
    <a class="btn btn-ghost btn-sm" href="<?= e(base('finance.php')) ?>">Calculate EMI &rarr;</a>
  </div>
  <div class="marquee marquee-pause marquee-light reveal" aria-hidden="true"><div class="track">
    <?php $lenders = ['HDFC Bank', 'ICICI Bank', 'Axis Bank', 'SBI', 'Bajaj Finserv', 'Tata Capital', 'Cholamandalam', 'ICICI Lombard', 'HDFC Ergo']; ?>
    <?php for ($rep = 0; $rep < 2; $rep++): foreach ($lenders as $b): ?>
      <span><?= e($b) ?></span><span class="dot">&#9679;</span>
    <?php endforeach; endfor; ?>
  </div></div>
</section>

<section class="wrap section">
  <div class="card card-pad app-band reveal">
    <div style="flex:1;min-width:240px">
      <span class="eyebrow">DRIVE24 on the go</span>
      <h2>Get the DRIVE24 app</h2>
      <p>Price-drop alerts, live auction bids and order tracking on your phone.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <span class="store-badge">Android - coming soon</span><span class="store-badge">iOS - coming soon</span>
      </div>
    </div>
    <div class="sms-chip">SMS <b>DRIVE24</b> to <b>56767</b><small>to get the download link</small></div>
  </div>
</section>
<?php renderFooter(); ?>
