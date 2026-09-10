<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';

// GET  /api/testdrives.php -> my test drives
// POST /api/testdrives.php {listing_id, datetime|slot_date, slot_time, location|address, mode}
$u = apiUser();
if ($u === null || !dbReady()) { apiJson(['ok' => false, 'message' => 'Sign in required.'], 401); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $rows = fetchAll('SELECT t.*, v.make, v.model FROM test_drives t JOIN listings l ON l.id = t.listing_id
        JOIN vehicles v ON v.id = l.vehicle_id WHERE t.user_id = ? ORDER BY t.slot_date DESC', [$u['id']]);
    apiJson(['ok' => true, 'test_drives' => $rows]);
}

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$car = findListing((int) ($in['listing_id'] ?? 0));
if (!$car) { apiJson(['ok' => false, 'message' => 'Valid listing_id required.'], 422); }
$date = (string) ($in['slot_date'] ?? substr((string) ($in['datetime'] ?? ''), 0, 10) ?: date('Y-m-d', strtotime('+2 days')));
$id = insert('test_drives', ['listing_id' => (int) $car['id'], 'user_id' => $u['id'],
    'mode' => ($in['mode'] ?? 'home') === 'hub' ? 'hub' : 'home', 'slot_date' => $date,
    'slot_time' => (string) ($in['slot_time'] ?? '11:00 AM'),
    'address' => mb_substr(trim((string) ($in['address'] ?? $in['location'] ?? '')), 0, 255), 'status' => 'requested']);
notify((int) $car['seller_id'], 'Test drive requested', $date . ' - ' . vehicleTitle($car), 'seller/testdrives.php');
apiJson(['ok' => true, 'testdrive_id' => $id], 201);
