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
            'payments' => ['Payments &amp; refunds', 'admin/payments.php'],
            'offers'   => ['Offers', 'admin/offers.php'],
            'payouts'  => ['Seller payouts', 'admin/payouts.php'],
        ],
        'People' => [
            'customers' => ['Customers', 'admin/customers.php'],
            'sellers'   => ['Sellers &amp; dealers', 'admin/sellers.php'],
            'kyc'       => ['KYC &amp; compliance', 'admin/kyc.php'],
            'support'   => ['Support tickets', 'admin/support.php'],
        ],
        'Insights' => [
            'reports' => ['Analytics &amp; reports', 'admin/reports.php'],
        ],
    ];
    $sellerMenu = [
        'Seller portal' => [
            'dashboard'  => ['Dashboard', 'seller/dashboard.php'],
            'listings'   => ['My listings', 'seller/listings.php'],
            'offers'     => ['Offers', 'seller/offers.php'],
            'testdrives' => ['Test drives', 'seller/testdrives.php'],
            'payouts'    => ['Payouts', 'seller/payouts.php'],
        ],
        'Shortcuts' => [
            'new' => ['List a new car', 'sell.php'],
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
    <a href="<?= e(base('logout.php')) ?>">Sign out</a>
  </aside>
  <section class="admin-main">
    <div class="admin-top">
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
