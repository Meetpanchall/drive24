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
  <div class="fin-hero abt-hero">
    <div class="fin-hero-txt">
      <span class="plan-pill">About Drive24</span>
      <h1>Your Trusted <span class="blue">Car Rental</span> Marketplace</h1>
      <p>India's trusted used-car marketplace. Every car passes a 280-point inspection, comes with a verified history report and a 7-day money-back promise - so you can buy and sell with total confidence.</p>
    </div>
    <div class="fin-hero-img">
      <img src="<?= e(base('assets/img/cars/car9.jpg')) ?>" alt="Blue SUV on a mountain road">
      <span class="script">Drive<br>Your<br>Dreams</span>
    </div>
  </div>

  <div class="abt-grid">
    <div class="abt-main">
      <div class="stat3">
        <div class="card stat-card">
          <span class="stat-ic" style="background:#e3f0fe;color:#0b5cff"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16l1.2-4.2A2 2 0 0 1 8.1 10h7.8a2 2 0 0 1 1.9 1.4L19 16"/><rect x="4" y="16" width="16" height="4" rx="1.5"/><circle cx="8" cy="20" r="1.4" fill="currentColor" stroke="none"/><circle cx="16" cy="20" r="1.4" fill="currentColor" stroke="none"/></svg></span>
          <span class="stat-txt"><small>Live cars</small><b class="num"><?= number_format($stats['cars']) ?></b></span>
          <a class="go-chip" style="background:#eef4ff;color:#0b5cff" href="<?= e(base('cars.php')) ?>" aria-label="Browse live cars">&#8250;</a>
        </div>
        <div class="card stat-card">
          <span class="stat-ic" style="background:#e2f7ec;color:#12a15f"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="7" height="12" rx="1"/><rect x="13" y="4" width="7" height="16" rx="1"/><path d="M6.5 11h2M6.5 14h2M15.5 8h2M15.5 11h2M15.5 14h2"/></svg></span>
          <span class="stat-txt"><small>Cities</small><b class="num"><?= number_format($stats['cities']) ?></b></span>
          <a class="go-chip" style="background:#eafaf1;color:#12a15f" href="<?= e(base('cars.php')) ?>" aria-label="Browse cities">&#8250;</a>
        </div>
        <div class="card stat-card">
          <span class="stat-ic" style="background:#f1e8fd;color:#7c3aed"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2.5" width="6" height="3.5" rx="1"/><path d="M9 12l2 2 4-4"/></svg></span>
          <span class="stat-txt"><small>Orders delivered</small><b class="num"><?= number_format($stats['orders']) ?></b></span>
          <a class="go-chip" style="background:#f4effe;color:#7c3aed" href="<?= e(base('cars.php')) ?>" aria-label="See delivered orders">&#8250;</a>
        </div>
      </div>

      <div class="card card-pad how-card">
        <div class="how-head">
          <span class="svc-ic lg" style="background:#0b5cff;color:#fff"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
          <span><b>How it works</b><small>Getting your dream car is simple. Follow these 4 easy steps and drive with confidence.</small></span>
        </div>
        <div class="how4">
          <div class="how-step">
            <div class="how-top"><span class="stat-ic" style="background:#0b5cff;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="6"/><path d="M15.5 15.5L20 20"/></svg></span><b style="color:#0b5cff">01</b></div>
            <b>Search &amp; Compare</b>
            <small>Browse our wide range of certified cars and compare features, prices and ratings.</small>
          </div>
          <span class="how-arrow">&#8250;</span>
          <div class="how-step">
            <div class="how-top"><span class="stat-ic" style="background:#12b76a;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.4"/><path d="M12 4v3M5.5 16l2.6-1.5M18.5 16l-2.6-1.5"/></svg></span><b style="color:#12b76a">02</b></div>
            <b>Test drive at home</b>
            <small>Pick a slot and our executive brings the car over for a hassle-free test drive.</small>
          </div>
          <span class="how-arrow">&#8250;</span>
          <div class="how-step">
            <div class="how-top"><span class="stat-ic" style="background:#7c3aed;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/><path d="M9.5 12l2 2 3.5-3.5"/></svg></span><b style="color:#7c3aed">03</b></div>
            <b>Finance &amp; pay securely</b>
            <small>Get easy financing options and make secure payments online or at the time of delivery.</small>
          </div>
          <span class="how-arrow">&#8250;</span>
          <div class="how-step">
            <div class="how-top"><span class="stat-ic" style="background:#f97316;color:#fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 5h13v11H1zM14 9h4l3 3v4h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/></svg></span><b style="color:#f97316">04</b></div>
            <b>Delivery + RC transfer</b>
            <small>We deliver your car and handle RC transfer for a smooth ownership experience.</small>
          </div>
        </div>
        <div class="how-strip">
          <span class="stat-ic sm-solid"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/><path d="M9.5 12l2 2 3.5-3.5"/></svg></span>
          <span>Every car is thoroughly inspected, verified and comes with a 7-day money-back promise.</span>
          <a class="btn btn-outline btn-sm" href="<?= e(base('support.php')) ?>">Learn More <span aria-hidden="true">&rarr;</span></a>
        </div>
      </div>
    </div>

    <aside class="abt-rail">
      <div class="card stat-card">
        <span class="stat-ic" style="background:#fef0dd;color:#f97316"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/><path d="M9.5 12l2 2 3.5-3.5"/></svg></span>
        <span class="stat-txt"><small>Inspection points</small><b class="num">280</b></span>
        <a class="go-chip" style="background:#fff4e8;color:#f97316" href="<?= e(base('support.php')) ?>" aria-label="About our inspection">&#8250;</a>
      </div>
      <div class="card card-pad rail-card" id="careers">
        <div class="rail-head">
          <span class="svc-ic sm" style="background:#e3eefd;color:#0b5cff"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3 2.8-4.5 5.5-4.5s4.9 1.5 5.5 4.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.6c2.9.1 4.4 1.6 5 4.4"/></svg></span>
          <b>Careers at DRIVE24</b>
        </div>
        <p class="muted" style="font-size:13px">We are hiring inspection engineers, city managers and PHP developers across India.</p>
        <div class="kv"><span><span class="kv-ic" style="background:#e3eefd;color:#0b5cff"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg></span>Open roles</span><span class="num">12</span></div>
        <div class="kv"><span><span class="kv-ic" style="background:#e3eefd;color:#0b5cff"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></span>Write to</span><span>jobs@drive24.in</span></div>
        <a class="btn btn-primary btn-block btn-sm" style="margin-top:10px" href="<?= e(base('support.php')) ?>">Apply Now <span aria-hidden="true">&rarr;</span></a>
      </div>
      <div class="card card-pad rail-card">
        <div class="rail-head">
          <span class="svc-ic sm" style="background:#e3eefd;color:#0b5cff"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></span>
          <b>Get in Touch</b>
        </div>
        <p class="muted" style="font-size:13px">Have questions? We'd love to hear from you.</p>
        <a class="btn btn-outline btn-block btn-sm" href="<?= e(base('support.php')) ?>">Contact us <span aria-hidden="true">&rarr;</span></a>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
