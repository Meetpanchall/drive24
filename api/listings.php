<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';
header('Content-Type: application/json');

// GET    /api/listings.php?make=Kia&max=1500000          search inventory
// GET    /api/listings.php?id=3                          one listing + inspection + history + rating
// POST   /api/listings.php  {vin,title...}               create (seller, -> pending)
// PUT    /api/listings.php?id=3 {price,description}       update own listing
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && isset($_GET['id'])) {
    $car = dbReady() ? findListing((int) $_GET['id']) : null;
    if (!$car) { apiJson(['ok' => false, 'message' => 'Listing not found.'], 404); }
    // Same visibility as car.php: only approved listings are public; drafts,
    // pending, rejected or sold cars are visible to the owner/admin only.
    $viewer = apiUser();
    $privileged = $viewer !== null && ((int) $car['seller_id'] === (int) $viewer['id'] || $viewer['role'] === 'admin');
    if ($car['status'] !== 'approved' && !$privileged) {
        apiJson(['ok' => false, 'message' => 'Listing not found.'], 404);
    }
    $insp = fetchOne('SELECT * FROM inspections WHERE vehicle_id = ? ORDER BY id DESC', [(int) $car['vehicle_id']]);
    $hist = fetchOne('SELECT * FROM vehicle_history WHERE vehicle_id = ?', [(int) $car['vehicle_id']]);
    apiJson(['ok' => true, 'listing' => [
        'listing_id' => (int) $car['id'], 'title' => vehicleTitle($car), 'make' => $car['make'],
        'model' => $car['model'], 'year' => (int) $car['year'], 'mileage' => (int) $car['km_driven'],
        'price' => (float) $car['price'], 'status' => $car['status'], 'fuel' => $car['fuel_type'],
        'transmission' => $car['transmission'], 'city' => $car['city'],
        'vin' => $privileged ? $car['vin'] : substr((string) $car['vin'], 0, 3) . '••••••••••••••',
        'inspection_score' => (int) $car['inspection_score'], 'rating' => listingRating((int) $car['id']),
        'inspection' => $insp ? ['score' => (int) $insp['score'], 'status' => $insp['status']] : null,
        'history' => $hist ? ['accidents' => (int) $hist['accidents'], 'challans' => (int) $hist['challans']] : null,
    ]]);
}

if ($method === 'POST') {
    $u = apiUser();
    if ($u === null || !in_array($u['role'], ['seller', 'dealer', 'admin'], true)) {
        apiJson(['ok' => false, 'message' => 'Seller sign-in required.'], 401);
    }
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    verifyApiCsrf(is_array($in) ? $in : null);
    foreach (['make', 'model', 'year', 'price'] as $req) {
        if (empty($in[$req])) { apiJson(['ok' => false, 'message' => "Field '$req' is required."], 422); }
    }
    $vin = trim((string) ($in['vin'] ?? ''));
    $decoded = $vin !== '' ? vinDecode($vin) : ['make' => '', 'year' => null];
    $vid = insert('vehicles', [
        'make' => trim((string) $in['make'] ?: $decoded['make']), 'model' => trim((string) $in['model']),
        'variant' => trim((string) ($in['variant'] ?? '')), 'year' => (int) $in['year'] ?: (int) ($decoded['year'] ?? date('Y')),
        'body_type' => (string) ($in['body_type'] ?? 'SUV'),
        'fuel_type' => in_array($in['fuel_type'] ?? '', ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'], true) ? $in['fuel_type'] : 'Petrol',
        'transmission' => ($in['transmission'] ?? '') === 'Automatic' ? 'Automatic' : 'Manual',
        'km_driven' => (int) ($in['km_driven'] ?? $in['mileage'] ?? 0), 'owners' => (int) ($in['owners'] ?? 1),
        'color' => trim((string) ($in['color'] ?? '')), 'reg_number' => trim((string) ($in['reg_number'] ?? '')),
        'vin' => $vin, 'city' => trim((string) ($in['city'] ?? $u['city'] ?? '')),
        'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 2000)]);
    $lid = insert('listings', ['vehicle_id' => $vid, 'seller_id' => $u['id'],
        'price' => (float) $in['price'], 'original_price' => (float) $in['price'],
        'status' => 'pending', 'certified' => 0, 'inspection_score' => 0]);
    apiJson(['ok' => true, 'listing_id' => $lid, 'status' => 'pending'], 201);
}

if ($method === 'PUT') {
    $u = apiUser();
    if ($u === null) { apiJson(['ok' => false, 'message' => 'Sign in required.'], 401); }
    $car = dbReady() ? findListing((int) ($_GET['id'] ?? 0)) : null;
    if (!$car || ((int) $car['seller_id'] !== (int) $u['id'] && $u['role'] !== 'admin')) {
        apiJson(['ok' => false, 'message' => 'Listing not found.'], 404);
    }
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    verifyApiCsrf(is_array($in) ? $in : null);
    $data = [];
    if (isset($in['price']) && (float) $in['price'] > 0) { $data['price'] = (float) $in['price']; }
    if ($data === []) { apiJson(['ok' => false, 'message' => 'Nothing to update.'], 422); }
    updateRow('listings', $data, 'id = ?', [(int) $car['id']]);
    if (isset($in['description'])) {
        updateRow('vehicles', ['description' => mb_substr((string) $in['description'], 0, 2000)], 'id = ?', [(int) $car['vehicle_id']]);
    }
    apiJson(['ok' => true, 'message' => 'Updated.']);
}

// Default: public search feed
$filters = [
    'q' => (string) ($_GET['q'] ?? ''), 'make' => (string) ($_GET['make'] ?? ''),
    'body' => (string) ($_GET['body'] ?? ''), 'fuel' => (string) ($_GET['fuel'] ?? ''),
    'transmission' => (string) ($_GET['transmission'] ?? ''), 'city' => (string) ($_GET['city'] ?? ''),
    'min' => (string) ($_GET['min'] ?? ''), 'max' => (string) ($_GET['max'] ?? ''),
    'sort' => (string) ($_GET['sort'] ?? ''),
];
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 12)));
$result = searchListings($filters, $limit, max(0, (int) ($_GET['offset'] ?? 0)));

echo json_encode([
    'ok' => dbReady(),
    'total' => $result['total'],
    'items' => array_map(static fn(array $r) => [
        'id' => (int) $r['id'],
        'title' => vehicleTitle($r),
        'price' => (float) $r['price'],
        'km_driven' => (int) $r['km_driven'],
        'fuel' => $r['fuel_type'],
        'transmission' => $r['transmission'],
        'city' => $r['city'],
        'inspection_score' => (int) $r['inspection_score'],
        'url' => base('car.php?id=' . (int) $r['id']),
    ], $result['rows']),
], JSON_PRETTY_PRINT);
