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
