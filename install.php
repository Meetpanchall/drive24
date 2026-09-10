<?php
require_once __DIR__ . '/includes/helpers.php';

$steps = [];
$ok = true;

$steps[] = ['PHP version ' . PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=')];
$steps[] = ['PDO MySQL driver', extension_loaded('pdo_mysql')];

$connected = dbReady();

// Always inspect the database we are ACTUALLY connected to - never trust a
// possibly edited config value for the schema name.
$liveDb = '';
$serverInfo = '';
if ($connected) {
    try {
        $liveDb = (string) db()->query('SELECT DATABASE()')->fetchColumn();
        $serverInfo = (string) db()->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    } catch (Throwable $e) { $connected = false; }
}
$connLabel = $connected ? ($serverInfo !== '' ? $serverInfo . ' / ' : '') . $liveDb : 'not connected';
$steps[] = ['MySQL connection (' . $connLabel . ')', $connected];

$tables = ['users', 'vehicles', 'listings', 'wishlists', 'saved_searches', 'offers', 'test_drives',
    'inspections', 'orders', 'payments', 'payouts', 'documents', 'leads', 'support_tickets', 'activity_log',
    'listing_images', 'reviews', 'chat_threads', 'chat_messages', 'loan_applications', 'insurance_quotes',
    'inspection_bookings', 'vehicle_history', 'notifications', 'password_resets', 'otp_codes', 'escrow_ledger',
    'vehicle_features', 'rc_transfers', 'bids', 'questions'];
$missing = [];
if ($connected) {
    foreach ($tables as $t) {
        $exists = (int) fetchValue('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$liveDb, $t], 0);
        if ($exists === 0) { $missing[] = $t; }
    }
    $steps[] = [count($tables) . ' tables imported', $missing === [], $missing ? 'Missing: ' . implode(', ', $missing) : ''];
    $steps[] = ['Seed data present', ((int) fetchValue('SELECT COUNT(*) FROM listings', [], 0)) > 0];
}

foreach ($steps as $s) { if (!$s[1]) { $ok = false; } }

$rehashed = 0;
if ($connected && $missing === [] && ($_GET['rehash'] ?? '') === '1') {
    $hash = password_hash('Drive24@2026', PASSWORD_DEFAULT);
    q('UPDATE users SET password_hash = ?', [$hash]);
    $rehashed = (int) fetchValue('SELECT COUNT(*) FROM users', [], 0);
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>DRIVE24 installer</title><link rel="stylesheet" href="<?= e(base('assets/css/app.css')) ?>"></head>
<body><div class="wrap section" style="max-width:820px">
  <h1>DRIVE24 setup check</h1>
  <p class="muted">Run this once after importing <code>database/schema.sql</code> into MySQL.</p>
  <div class="card card-pad">
    <?php foreach ($steps as $s): ?>
      <div class="kv"><span><?= e($s[0]) ?><?= isset($s[2]) && $s[2] ? '<br><small class="muted">' . e($s[2]) . '</small>' : '' ?></span>
        <span><?= $s[1] ? '<span class="badge ok">OK</span>' : '<span class="badge bad">Check</span>' ?></span></div>
    <?php endforeach; ?>
  </div>

  <?php if ($rehashed): ?>
    <div class="alert success">Passwords reset for <?= (int) $rehashed ?> demo users. Everyone can now sign in with <b>Drive24@2026</b>.</div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="alert success">Everything looks good. <a href="<?= e(base('index.php')) ?>">Open the marketplace</a>.</div>
    <a class="btn btn-dark" href="<?= e(base('install.php?rehash=1')) ?>">Set all demo passwords to Drive24@2026</a>
  <?php else: ?>
    <div class="alert error">Fix the items marked "Check" above.</div>
    <div class="card card-pad">
      <h3 style="font-size:1.02rem">Quick fix</h3>
      <ol><li>Create the database and import the schema:<br><code>mysql -u root -p &lt; database/schema.sql</code></li>
      <li>Set credentials in <code>config/config.php</code> or environment variables <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>.</li>
      <li>Reload this page.</li></ol>
    </div>
  <?php endif; ?>

  <div class="card card-pad" style="margin-top:16px">
    <h3 style="font-size:1.02rem">Demo accounts (password Drive24@2026)</h3>
    <div class="kv"><span>admin@drive24.in</span><span>Admin console</span></div>
    <div class="kv"><span>seller@drive24.in</span><span>Dealer portal</span></div>
    <div class="kv"><span>meet@example.com</span><span>Buyer</span></div>
  </div>

  <div class="card card-pad" style="margin-top:16px">
    <h3 style="font-size:1.02rem">Upgrading an older install?</h3>
    <p class="muted">Just reload any page: missing SRS tables (chat, reviews, loans, escrow...) are created automatically from <code>database/migrate.sql</code>. No data is touched.</p>
    <h3 style="font-size:1.02rem;margin-top:14px">JSON API (SRS endpoints)</h3>
    <div class="kv"><span><code>api/auth.php?action=signup|login</code></span><span>register + token login</span></div>
    <div class="kv"><span><code>api/listings.php / ?id=3</code></span><span>search, detail, create, update</span></div>
    <div class="kv"><span><code>api/offers.php, api/testdrives.php</code></span><span>negotiation + slots</span></div>
    <div class="kv"><span><code>api/checkout.php, api/chat.php, api/reviews.php</code></span><span>orders, messages, ratings</span></div>
    <div class="kv"><span><code>api/vin.php?vin=...</code></span><span>VIN decoding</span></div>
    <div class="kv"><span><code>api/admin.php?view=pending|stats</code></span><span>admin (token + role)</span></div>
  </div>
</div></body></html>
