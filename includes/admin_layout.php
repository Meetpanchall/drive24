<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function adminHeader(string $title, string $active = '', string $area = 'admin'): void
{
    $u = user();
    $adminMenu = [
        'Operations' => [
            'dashboard'   => ['Dashboard', 'admin/index.php'],
            'approvals'   => ['Listing approvals', 'admin/approvals.php'],
            'vehicles'    => ['Vehicle inventory', 'admin/vehicles.php'],
            'inspections' => ['Inspections', 'admin/inspections.php'],
            'testdrives'  => ['Test drives', 'admin/testdrives.php'],
        ],
        'Commerce' => [
            'orders'   => ['Orders', 'admin/orders.php'],
            'rentals'  => ['Rentals', 'admin/rentals.php'],
            'payments' => ['Payments &amp; refunds', 'admin/payments.php'],
            'offers'   => ['Offers', 'admin/offers.php'],
            'enquiries' => ['Enquiries &amp; leads', 'admin/enquiries.php'],
            'payouts'  => ['Seller payouts', 'admin/payouts.php'],
            'finance'  => ['Loan applications', 'admin/finance.php'],
        ],
        'People' => [
            'customers' => ['Customers', 'admin/customers.php'],
            'sellers'   => ['Sellers &amp; dealers', 'admin/sellers.php'],
            'kyc'       => ['KYC &amp; compliance', 'admin/kyc.php'],
            'documents' => ['Documents registry', 'admin/documents.php'],
            'support'   => ['Support tickets', 'admin/support.php'],
            'complaints' => ['Complaints &amp; disputes', 'admin/complaints.php'],
            'reviews'   => ['Reviews &amp; ratings', 'admin/reviews.php'],
            'broadcast' => ['Broadcast', 'admin/broadcast.php'],
        ],
        'Insights' => [
            'reports'  => ['Analytics &amp; reports', 'admin/reports.php'],
            'activity' => ['Audit log', 'admin/activity.php'],
            'settings' => ['System settings', 'admin/settings.php'],
        ],
    ];
    $sellerMenu = [
        'Seller portal' => [
            'dashboard'  => ['Dashboard', 'seller/dashboard.php'],
            'listings'   => ['My listings', 'seller/listings.php'],
            'offers'     => ['Offers', 'seller/offers.php'],
            'orders'     => ['Sales orders', 'seller/orders.php'],
            'testdrives' => ['Test drives', 'seller/testdrives.php'],
            'questions'  => ['Buyer questions', 'seller/questions.php'],
            'documents'  => ['Documents', 'seller/documents.php'],
            'kyc'        => ['KYC verification', 'seller/kyc.php'],
            'payouts'    => ['Payouts', 'seller/payouts.php'],
        ],
        'Shortcuts' => [
            'new' => ['List a new car', 'sell.php'],
            'bulk' => ['Bulk CSV upload', 'seller/bulk-upload.php'],
            'chat' => ['Messages', 'chat.php'],
            'site' => ['Back to marketplace', 'index.php'],
        ],
    ];
    $menu = $area === 'admin' ? $adminMenu : $sellerMenu;
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | DRIVE24 <?= e(ucfirst($area)) ?></title>
<link rel="icon" href="<?= e(base('assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(base('assets/css/app.css')) ?>">
</head>
<body data-base="<?= e(rtrim(base(''), '/')) ?>" data-csrf="<?= e(csrfToken()) ?>">
<div class="preloader" id="preloader" aria-hidden="true">
  <div class="pre-inner">
    <svg class="pre-car" viewBox="0 0 220 100" width="220" height="100" aria-hidden="true" focusable="false">
      <defs>
        <linearGradient id="preGlass" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#bfe3ff"/><stop offset="1" stop-color="#38bdf8"/>
        </linearGradient>
        <linearGradient id="preBeam" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stop-color="#fef9c3" stop-opacity=".8"/><stop offset="1" stop-color="#fef9c3" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <g class="pre-speed" stroke="#38bdf8" stroke-width="4" stroke-linecap="round" opacity=".6">
        <path d="M6 40 h22 M2 54 h30 M10 68 h18" fill="none"/>
      </g>
      <polygon class="pre-beam" points="200,58 219,50 219,70 200,64" fill="url(#preBeam)"/>
      <g class="pre-body">
        <path d="M14 64 C14 58 18 55 24 54 L40 52 L54 34 C56 31 59 30 63 30 L128 30 C132 30 135 31 137 34 L150 50 L192 52 C198 52 202 56 202 62 L202 64 C202 68 199 70 195 70 L19 70 C16 70 14 67 14 64 Z" fill="#ffffff"/>
        <path d="M64 35 L126 35 L136 48 L58 48 Z" fill="url(#preGlass)"/>
        <path d="M96 35 L92 48" stroke="#0a1c48" stroke-width="3"/>
        <circle cx="200" cy="60" r="4" fill="#fde047"/>
        <rect x="15" y="57" width="6" height="7" rx="2" fill="#f87171"/>
        <circle cx="58" cy="70" r="17" fill="#080f2c"/>
        <circle cx="162" cy="70" r="17" fill="#080f2c"/>
        <g class="pre-wheel">
          <circle cx="58" cy="70" r="13" fill="#131f45"/>
          <circle cx="58" cy="70" r="13" fill="none" stroke="#3b517f" stroke-width="2"/>
          <path d="M58 61 v18 M49 70 h18 M52 64 l12 12 M64 64 L52 76" stroke="#9cc4ff" stroke-width="2.5" stroke-linecap="round"/>
          <circle cx="58" cy="70" r="2.5" fill="#e0ecff"/>
        </g>
        <g class="pre-wheel">
          <circle cx="162" cy="70" r="13" fill="#131f45"/>
          <circle cx="162" cy="70" r="13" fill="none" stroke="#3b517f" stroke-width="2"/>
          <path d="M162 61 v18 M153 70 h18 M156 64 l12 12 M168 64 L156 76" stroke="#9cc4ff" stroke-width="2.5" stroke-linecap="round"/>
          <circle cx="162" cy="70" r="2.5" fill="#e0ecff"/>
        </g>
      </g>
      <line class="pre-road" x1="0" y1="88" x2="220" y2="88" stroke="#38bdf8" stroke-width="4" stroke-linecap="round" stroke-dasharray="16 14" opacity=".8"/>
    </svg>
    <div class="pre-brand">DRIVE<span>24</span></div>
    <div class="pre-bar"><i></i></div>
    <div class="pre-status" id="preStatus">Warming up the engine&hellip;</div>
  </div>
</div>
<div class="admin-shell">
  <aside class="admin-side">
    <a class="brand" href="<?= e(base('index.php')) ?>"><img src="<?= e(base('assets/img/logo.svg')) ?>" alt="" width="30" height="30">DRIVE24</a>
    <?php foreach ($menu as $group => $items): ?>
      <div class="group"><?= e($group) ?></div>
      <?php foreach ($items as $key => $item): ?>
        <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(base($item[1])) ?>"><?= $item[0] ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="group">Session</div>
    <?php if ($area === 'admin'): ?><a class="<?= $active === 'profile' ? 'active' : '' ?>" href="<?= e(base('admin/profile.php')) ?>">My profile</a><?php endif; ?>
    <a href="<?= e(base('logout.php')) ?>">Sign out</a>
  </aside>
  <div class="side-overlay" aria-hidden="true"></div>
  <section class="admin-main">
    <div class="admin-top">
      <button class="admin-toggle" type="button" aria-label="Open menu" aria-expanded="false">&#9776;</button>
      <h1><?= e($title) ?></h1>
      <div style="margin-left:auto" class="muted"><?= e($u['name'] ?? '') ?> &middot; <?= e(ucfirst((string) ($u['role'] ?? ''))) ?></div>
    </div>
    <?php if (!dbReady()): ?><div class="alert error">MySQL is not connected. Import <code>database/schema.sql</code> first.</div><?php endif; ?>
    <?php foreach (flash() as $msg): ?><div class="alert <?= e($msg['type']) ?>"><?= e($msg['message']) ?></div><?php endforeach; ?>
<?php }

function adminFooter(): void
{
    ?>
  </section>
</div>
<div class="toast-host"></div>
<script src="<?= e(base('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php }
