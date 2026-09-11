<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';

// GET  /api/offers.php                    -> my offers (buyer) / received (seller)
// POST /api/offers.php  {listing_id, offer_price, message}
$u = apiUser();
if ($u === null || !dbReady()) { apiJson(['ok' => false, 'message' => 'Sign in required.'], 401); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (in_array($u['role'], ['seller', 'dealer', 'admin'], true)) {
        $rows = fetchAll('SELECT o.*, v.make, v.model FROM offers o JOIN listings l ON l.id = o.listing_id
            JOIN vehicles v ON v.id = l.vehicle_id WHERE l.seller_id = ? ORDER BY o.id DESC', [$u['id']]);
    } else {
        $rows = fetchAll('SELECT o.*, v.make, v.model FROM offers o JOIN listings l ON l.id = o.listing_id
            JOIN vehicles v ON v.id = l.vehicle_id WHERE o.buyer_id = ? ORDER BY o.id DESC', [$u['id']]);
    }
    apiJson(['ok' => true, 'offers' => $rows]);
}

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
verifyApiCsrf(is_array($in) ? $in : null);
$car = findListing((int) ($in['listing_id'] ?? 0));
$price = (float) ($in['offer_price'] ?? $in['amount'] ?? 0);
if (!$car || $price <= 0) { apiJson(['ok' => false, 'message' => 'Valid listing_id and offer_price required.'], 422); }
if ($car['status'] !== 'approved') { apiJson(['ok' => false, 'message' => 'Offers are open only on live listings.'], 422); }
if ((int) $car['seller_id'] === (int) $u['id']) { apiJson(['ok' => false, 'message' => 'You cannot offer on your own car.'], 422); }
$oid = insert('offers', ['listing_id' => (int) $car['id'], 'buyer_id' => $u['id'], 'amount' => $price,
    'message' => mb_substr(trim((string) ($in['message'] ?? '')), 0, 400), 'status' => 'new']);
notify((int) $car['seller_id'], 'New offer', rupees($price) . ' on ' . vehicleTitle($car), 'seller/offers.php');
apiJson(['ok' => true, 'offer_id' => $oid, 'status' => 'new'], 201);
