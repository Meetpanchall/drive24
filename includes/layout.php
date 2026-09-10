<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function renderHeader(string $title, string $active = ''): void
{
    $u = user();
    $wish = count(wishlistIds());
    $cmp = count(compareIds());
    $nav = ['home' => ['Home', 'index.php'], 'cars' => ['Buy cars', 'cars.php'], 'sell' => ['Sell car', 'sell.php'],
            'finance' => ['Finance', 'finance.php'], 'services' => ['Services', 'services.php'], 'compare' => ['Compare', 'compare.php']];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | DRIVE24 - Buy &amp; sell used cars</title>
<meta name="description" content="DRIVE24 is an online used-car marketplace: 280-point inspected cars, instant valuation, finance, RC transfer and doorstep delivery.">
<link rel="icon" href="<?= e(base('assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(base('assets/css/app.css')) ?>">
</head>
<body data-base="<?= e(rtrim(base(''), '/')) ?>" data-csrf="<?= e(csrfToken()) ?>">
<header class="site-head">
  <div class="wrap bar">
    <button class="menu-btn" type="button" aria-label="Menu">&#9776;</button>
    <a class="brand" href="<?= e(base('index.php')) ?>"><img src="<?= e(base('assets/img/logo.svg')) ?>" alt="" width="34" height="34">DRIVE24</a>
    <nav class="nav">
      <?php foreach ($nav as $key => $item): ?>
        <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(base($item[1])) ?>"><?= e($item[0]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="head-actions">
      <a class="icon-pill" href="<?= e(base('compare.php')) ?>">Compare <span class="count" data-compare-count><?= $cmp ?></span></a>
      <a class="icon-pill" href="<?= e(base('wishlist.php')) ?>">Saved <span class="count" data-wishlist-count><?= $wish ?></span></a>
      <?php if ($u): ?>
        <a class="icon-pill" href="<?= e(base('account.php')) ?>"><?= e(explode(' ', $u['name'])[0]) ?></a>
        <?php if (in_array($u['role'], ['seller', 'dealer'], true)): ?><a class="btn btn-outline btn-sm" href="<?= e(base('seller/dashboard.php')) ?>">Seller</a><?php endif; ?>
        <?php if ($u['role'] === 'admin'): ?><a class="btn btn-dark btn-sm" href="<?= e(base('admin/index.php')) ?>">Admin</a><?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(base('logout.php')) ?>">Sign out</a>
      <?php else: ?>
        <a class="btn btn-outline btn-sm" href="<?= e(base('login.php')) ?>">Sign in</a>
        <a class="btn btn-primary btn-sm" href="<?= e(base('sell.php')) ?>">Sell your car</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main>
<?php if (!dbReady()): ?>
  <div class="wrap" style="padding-top:16px">
    <div class="alert error"><b>MySQL is not connected.</b> Import <code>database/schema.sql</code> and check <code>config/config.php</code>, then open <a href="<?= e(base('install.php')) ?>">install.php</a> to finish setup.</div>
  </div>
<?php endif; ?>
<?php foreach (flash() as $msg): ?>
  <div class="wrap" style="padding-top:14px"><div class="alert <?= e($msg['type']) ?>"><?= e($msg['message']) ?></div></div>
<?php endforeach; ?>
<?php }

function renderFooter(): void
{
    ?>
</main>
<footer class="site-foot">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <div class="brand" style="color:#fff"><img src="<?= e(base('assets/img/logo.svg')) ?>" alt="" width="30" height="30">DRIVE24</div>
        <p style="margin-top:10px;max-width:320px">India's trusted used-car marketplace. Every car passes a 280-point inspection, comes with a verified history report and 5-day money-back promise.</p>
      </div>
      <div><h4>Buy</h4>
        <a href="<?= e(base('cars.php')) ?>">All cars</a><a href="<?= e(base('cars.php?body=SUV')) ?>">SUVs</a>
        <a href="<?= e(base('finance.php')) ?>">Car finance</a><a href="<?= e(base('compare.php')) ?>">Compare cars</a></div>
      <div><h4>Sell</h4>
        <a href="<?= e(base('sell.php')) ?>">Instant valuation</a><a href="<?= e(base('seller/dashboard.php')) ?>">Seller portal</a>
        <a href="<?= e(base('services.php')) ?>">RC transfer</a></div>
      <div><h4>Company</h4>
        <a href="<?= e(base('support.php')) ?>">Help centre</a><a href="<?= e(base('services.php')) ?>">Services</a>
        <a href="<?= e(base('login.php')) ?>">Sign in</a></div>
    </div>
    <div class="foot-bottom"><span>&copy; <?= date('Y') ?> DRIVE24 Technologies Pvt. Ltd.</span><span>Privacy &middot; Terms &middot; PCI-DSS aligned payments</span></div>
  </div>
</footer>
<div class="toast-host"></div>
<script src="<?= e(base('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php }
