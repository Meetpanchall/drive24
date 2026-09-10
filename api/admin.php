<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';

// GET /api/admin.php?view=pending -> listings awaiting approval (admin only)
// GET /api/admin.php?view=stats   -> marketplace KPIs (admin only)
$u = apiUser();
if ($u === null || $u['role'] !== 'admin') { apiJson(['ok' => false, 'message' => 'Admin access only.'], 403); }
if (!dbReady()) { apiJson(['ok' => false, 'message' => 'Database unavailable.'], 503); }

$view = (string) ($_GET['view'] ?? 'pending');
if ($view === 'stats') {
    apiJson(['ok' => true, 'stats' => [
        'live' => (int) fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'approved'", [], 0),
        'pending' => (int) fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'pending'", [], 0),
        'orders' => (int) fetchValue('SELECT COUNT(*) FROM orders', [], 0),
        'revenue' => (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status NOT IN ('cancelled','returned')", [], 0),
        'users' => (int) fetchValue('SELECT COUNT(*) FROM users', [], 0),
    ]]);
}
$rows = fetchAll('SELECT l.id AS listing_id, l.price, l.created_at, v.make, v.model, v.year,
    u.name AS seller FROM listings l JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id
    WHERE l.status = ? ORDER BY l.created_at', ['pending']);
apiJson(['ok' => true, 'pending' => $rows]);
