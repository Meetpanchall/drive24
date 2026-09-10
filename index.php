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
$makes = filterOptions('make');
$bodies = filterOptions('body_type');
$cities = filterOptions('city');
$wish = wishlistIds();
$cmp = compareIds();

renderHeader('Buy and sell used cars online', 'home');
?>
<section class="hero">
  <div class="blob b1" data-depth="26"></div>
  <div class="blob b2" data-depth="16"></div>
  <div class="blob b3" data-depth="36"></div>
  <div class="float-card fc1" data-depth="12">&#9733; 4.8 buyer rating<small>2,40,000+ verified reviews</small></div>
  <div class="float-card fc2" data-depth="20">5-day money-back<small>500 km easy return promise</small></div>
  <div class="wrap">
    <h1>Buy a car you can actually trust.</h1>
    <p>Every DRIVE24 car clears a 280-point inspection, comes with a verified RC and history report, doorstep test drive and a 5-day money-back guarantee.</p>
    <div class="hero-stats">
      <div><b class="num"><span data-count="<?= $stats['cars'] ?>">0</span>+</b><span>Certified cars live</span></div>
      <div><b class="num"><span data-count="<?= $stats['cities'] ?>">0</span></b><span>Cities served</span></div>
      <div><b class="num"><span data-count="<?= $stats['sellers'] ?>">0</span></b><span>Verified sellers</span></div>
      <div><b class="num"><span data-count="<?= $stats['orders'] ?>">0</span></b><span>Cars delivered</span></div>
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
        <div style="display:flex;align-items:flex-end"><button class="btn btn-primary btn-block btn-lg" type="submit">Search cars</button></div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <?php foreach (array_slice($cities, 0, 6) as $c): ?>
          <a class="chip" href="<?= e(base('cars.php?city=' . urlencode((string) $c))) ?>"><?= e((string) $c) ?></a>
        <?php endforeach; ?>
      </div>
    </form>
  </div>
</section>

<div class="wrap"><div class="marquee" aria-hidden="true"><div class="track">
  <?php $brands = ['Hyundai', 'Maruti Suzuki', 'Tata', 'Mahindra', 'Toyota', 'Honda', 'Kia', 'Skoda', 'MG', 'Volkswagen', 'Renault']; ?>
  <?php for ($rep = 0; $rep < 2; $rep++): foreach ($brands as $b): ?>
    <span><?= e($b) ?></span><span class="dot">&#9679;</span>
  <?php endforeach; endfor; ?>
</div></div></div>

<section class="wrap section">
  <div class="reveal" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 class="sec-title">Featured certified cars</h2>
    <a class="btn btn-ghost btn-sm" style="margin-left:auto" href="<?= e(base('cars.php')) ?>">View all inventory &rarr;</a>
  </div>
  <?php if (!$featured): ?><div class="card card-pad empty">No live listings yet. Import <code>database/schema.sql</code> to load demo inventory.</div><?php endif; ?>
  <div class="grid cars"><?php foreach ($featured as $r) { carCard($r, $wish, $cmp); } ?></div>
</section>

<section class="wrap section">
  <h2 class="sec-title reveal">Why 2 lakh+ buyers choose DRIVE24</h2>
  <div class="grid four">
    <div class="card card-pad"><h3>280-point inspection</h3><p class="muted">Engine, structure, electricals and tyres are scored by certified engineers before a car goes live.</p></div>
    <div class="card card-pad"><h3>Transparent pricing</h3><p class="muted">AI valuation benchmarked against live market data, no hidden charges at checkout.</p></div>
    <div class="card card-pad"><h3>Finance in 24 hours</h3><p class="muted">Compare offers from 12+ lenders with instant EMI eligibility right on the car page.</p></div>
    <div class="card card-pad"><h3>RC transfer handled</h3><p class="muted">We complete RTO paperwork, insurance transfer and doorstep delivery end to end.</p></div>
  </div>
</section>

<section class="wrap section">
  <div class="split-3">
    <div>
      <h2 class="sec-title reveal">Freshly added</h2>
      <div class="grid cars"><?php foreach ($recent as $r) { carCard($r, $wish, $cmp); } ?></div>
    </div>
    <aside class="card card-pad sticky">
      <h3>Sell your car in 3 steps</h3>
      <div class="steps" style="flex-direction:column">
        <div class="step done">1. Get an instant AI price</div>
        <div class="step active">2. Free doorstep inspection</div>
        <div class="step">3. Same-day payment &amp; RC transfer</div>
      </div>
      <a class="btn btn-primary btn-block" style="margin-top:14px" href="<?= e(base('sell.php')) ?>">Get car valuation</a>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:8px" href="<?= e(base('finance.php')) ?>">Check EMI eligibility</a>
    </aside>
  </div>
</section>
<?php renderFooter(); ?>
