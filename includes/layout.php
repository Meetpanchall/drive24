<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function renderHeader(string $title, string $active = ''): void
{
    $u = user();
    $wish = count(wishlistIds());
    $cmp = count(compareIds());
    $nav = ['home' => ['Home', 'index.php'], 'cars' => ['Buy Cars', 'cars.php'], 'sell' => ['Sell Your Car', 'sell.php'],
            'finance' => ['Finance', 'finance.php'], 'services' => ['Services', 'services.php'], 'compare' => ['Compare', 'compare.php']];
    $unread = unreadNotifications();
    $q = trim((string) ($_GET['q'] ?? ''));
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
    <a class="brand-logo" href="<?= e(base('index.php')) ?>" aria-label="DRIVE24 home"><img src="<?= e(base('assets/img/logo-brand.svg')) ?>" alt="Drive24 - Rent, Drive, Explore" height="44"></a>
    <nav class="nav" aria-label="Primary">
      <?php foreach ($nav as $key => $item): ?>
        <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(base($item[1])) ?>"><?= e($item[0]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="head-actions">
      <form class="head-search" action="<?= e(base('cars.php')) ?>" method="get" role="search">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.8-3.8"/></svg>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search cars, brands, models..." aria-label="Search cars">
      </form>
      <a class="head-link" href="<?= e(base('compare.php')) ?>">Compare <span class="count-badge" data-compare-count><?= $cmp ?></span></a>
      <a class="head-link" href="<?= e(base('wishlist.php')) ?>">Saved <span class="count-badge" data-wishlist-count><?= $wish ?></span></a>
      <?php if ($u): ?>
        <a class="head-link" href="<?= e(base('chat.php')) ?>">Chat</a>
        <a class="head-link bell" href="<?= e(base('notifications.php')) ?>" title="Notifications" aria-label="Notifications">&#128276;<?php if ($unread > 0): ?> <span class="count-badge"><?= $unread ?></span><?php endif; ?></a>
        <a class="btn-signin" href="<?= e(base('account.php')) ?>"><?= e(explode(' ', $u['name'])[0]) ?></a>
        <?php if (in_array($u['role'], ['seller', 'dealer'], true)): ?><a class="head-link" href="<?= e(base('seller/dashboard.php')) ?>">Seller</a><?php endif; ?>
        <?php if ($u['role'] === 'admin'): ?><a class="head-link" href="<?= e(base('admin/index.php')) ?>">Admin</a><?php endif; ?>
        <a class="head-link" href="<?= e(base('logout.php')) ?>">Sign out</a>
      <?php else: ?>
        <a class="btn-signin" href="<?= e(base('login.php')) ?>">Sign In</a>
        <a class="btn-sellyour" href="<?= e(base('sell.php')) ?>"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16l1.5-4.5A2 2 0 0 1 8.4 10h7.2a2 2 0 0 1 1.9 1.5L19 16"/><path d="M4 16h16v3.5H4z"/><circle cx="8" cy="19.5" r="1.4"/><circle cx="16" cy="19.5" r="1.4"/></svg> Sell Your Car</a>
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
      <div class="foot-brand">
        <a href="<?= e(base('index.php')) ?>" aria-label="DRIVE24 home"><img src="<?= e(base('assets/img/logo-brand-white.svg')) ?>" alt="Drive24 - Rent, Drive, Explore" height="46"></a>
        <p>India's trusted used-car marketplace. Every car passes a 280-point inspection, comes with a verified history report and 7-day money-back promise.</p>
        <div class="socials">
          <a href="#" aria-label="Facebook" title="Facebook"><svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.7c0-.9.3-1.6 1.7-1.6h1.7V4.2c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1v2.6H7.8V14h2.7v8z"/></svg></a>
          <a href="#" aria-label="Instagram" title="Instagram"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1.3" fill="currentColor" stroke="none"/></svg></a>
          <a href="#" aria-label="YouTube" title="YouTube"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.5 12 5.5 12 5.5s-6 0-7.9.6A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.6 7.9.6 7.9.6s6 0 7.9-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 22.4 12 31 31 0 0 0 22 8.2zM10 15V9l5.2 3z"/></svg></a>
          <a href="#" aria-label="X" title="X"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-6.4L6.4 22H3.3l7.3-8.3L1.6 2H8l4.4 5.9L18.9 2zm-1.1 18h1.7L7.1 3.9H5.3L17.8 20z"/></svg></a>
          <a href="#" aria-label="LinkedIn" title="LinkedIn"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M20.4 20.4h-3.5v-5.6c0-1.3 0-3-1.9-3s-2.1 1.4-2.1 2.9v5.7H9.4V9h3.3v1.6h.1c.5-.9 1.7-1.9 3.4-1.9 3.6 0 4.2 2.4 4.2 5.4v6.3zM5.3 7.4a2 2 0 1 1 0-4.1 2 2 0 0 1 0 4.1zM7.1 20.4H3.6V9h3.5v11.4z"/></svg></a>
        </div>
      </div>
      <div><h4>Buy</h4>
        <a href="<?= e(base('cars.php')) ?>">All Cars <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('cars.php?body=SUV')) ?>">SUVs <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('cars.php?body=Sedan')) ?>">Sedans <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('cars.php?body=Hatchback')) ?>">Hatchbacks <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('compare.php')) ?>">Compare Cars <span class="chev">&rsaquo;</span></a></div>
      <div><h4>Sell</h4>
        <a href="<?= e(base('sell.php')) ?>">Instant Valuation <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('sell.php')) ?>">Sell Your Car <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('services.php')) ?>">RC Transfer <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('index.php#how')) ?>">How It Works <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('support.php')) ?>">FAQs <span class="chev">&rsaquo;</span></a></div>
      <div><h4>Company</h4>
        <a href="<?= e(base('about.php')) ?>">About Us <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('services.php')) ?>">Services <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('insurance.php')) ?>">Insurance <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('about.php#careers')) ?>">Careers <span class="chev">&rsaquo;</span></a>
        <a href="<?= e(base('support.php')) ?>">Contact Us <span class="chev">&rsaquo;</span></a></div>
    </div>
    <div class="foot-bottom">
      <span>&copy; <?= date('Y') ?> DRIVE24 Technologies Pvt. Ltd. All rights reserved.</span>
      <span class="pay-row"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 3v6c0 5-3.2 8.6-7 11-3.8-2.4-7-6-7-11V5z"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg> 100% Secure Payments</span>
      <span class="pay-chips"><span class="pay-chip italic">Razorpay</span><span class="pay-chip italic b">UPI</span><span class="pay-chip italic b navy">VISA</span><span class="pay-chip mc"><i></i><i></i></span></span>
      <span class="legal"><a href="<?= e(base('terms.php')) ?>">Terms</a><a href="<?= e(base('privacy.php')) ?>">Privacy</a></span>
    </div>
  </div>
</footer>
<div class="toast-host"></div>
<script src="<?= e(base('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php }
