<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$stats = [
    'cars' => dbReady() ? (int) fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'approved'", [], 0) : 0,
    'cities' => dbReady() ? (int) fetchValue('SELECT COUNT(DISTINCT city) FROM vehicles', [], 0) : 0,
    'orders' => dbReady() ? (int) fetchValue('SELECT COUNT(*) FROM orders', [], 0) : 0,
];

renderHeader('About us', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.7rem">About DRIVE24</h1>
  <p class="muted" style="max-width:720px">India's trusted used-car marketplace. Every car passes a 280-point inspection, comes with a verified history report and a 7-day money-back promise - so you can buy and sell with total confidence.</p>
  <div class="kpis" style="margin-top:16px">
    <div class="kpi"><small>Live cars</small><b class="num"><?= number_format($stats['cars']) ?></b></div>
    <div class="kpi"><small>Cities</small><b class="num"><?= number_format($stats['cities']) ?></b></div>
    <div class="kpi"><small>Orders delivered</small><b class="num"><?= number_format($stats['orders']) ?></b></div>
    <div class="kpi"><small>Inspection points</small><b class="num">280</b></div>
  </div>
  <div class="split-3" style="margin-top:18px">
    <div class="card card-pad">
      <h2 style="font-size:1.2rem">How it works</h2>
      <div class="kv"><span>1. Search &amp; compare</span><span>Filter inspected cars by budget, brand and body type.</span></div>
      <div class="kv"><span>2. Test drive at home</span><span>Pick a slot and our executive brings the car over.</span></div>
      <div class="kv"><span>3. Finance &amp; pay securely</span><span>Compare 12+ lenders; money stays in escrow.</span></div>
      <div class="kv"><span>4. Delivery + RC transfer</span><span>Doorstep delivery with free RC transfer.</span></div>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?= e(base('cars.php')) ?>">Browse cars</a>
        <a class="btn btn-outline" href="<?= e(base('sell.php')) ?>">Sell your car</a>
      </div>
    </div>
    <aside class="card card-pad sticky" id="careers">
      <h3 style="font-size:1.05rem">Careers at DRIVE24</h3>
      <p class="muted" style="font-size:13.5px">We are hiring inspection engineers, city managers and PHP developers across India.</p>
      <div class="kv"><span>Open roles</span><span class="num">12</span></div>
      <div class="kv"><span>Write to</span><span>jobs@drive24.in</span></div>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:10px" href="<?= e(base('support.php')) ?>">Contact us</a>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
