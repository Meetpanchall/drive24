<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';

// POST /api/checkout.php {listing_id, method, city, address, finance?, loan_amount?, tenure?}
$u = apiUser();
if ($u === null || !dbReady()) { apiJson(['ok' => false, 'message' => 'Sign in required.'], 401); }
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$car = findListing((int) ($in['listing_id'] ?? 0));
if (!$car || $car['status'] !== 'approved') { apiJson(['ok' => false, 'message' => 'Car is not available for booking.'], 422); }

$method = in_array($in['method'] ?? '', ['upi', 'card', 'netbanking', 'finance', 'wallet'], true) ? (string) $in['method'] : 'upi';
$booking = 25000.0;
$pdo = db();
$pdo->beginTransaction();
try {
    $orderId = insert('orders', [
        'order_no' => 'D24-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3))),
        'listing_id' => (int) $car['id'], 'buyer_id' => $u['id'], 'amount' => (float) $car['price'],
        'booking_amount' => $booking, 'finance_opted' => !empty($in['finance']) ? 1 : 0,
        'loan_amount' => (float) ($in['loan_amount'] ?? 0), 'tenure_months' => (int) ($in['tenure'] ?? 0),
        'status' => 'confirmed', 'delivery_city' => mb_substr(trim((string) ($in['city'] ?? '')), 0, 80),
        'delivery_address' => mb_substr(trim((string) ($in['address'] ?? '')), 0, 255),
        'delivery_date' => date('Y-m-d', strtotime('+7 days'))]);
    $payId = insert('payments', ['order_id' => $orderId, 'txn_ref' => 'TXN' . date('ymdHis') . random_int(10, 99),
        'method' => $method, 'amount' => $booking, 'status' => 'paid']);
    insert('escrow_ledger', ['order_id' => $orderId, 'kind' => 'hold', 'amount' => $booking,
        'note' => 'Booking held in escrow via API']);
    q("UPDATE listings SET status = 'reserved' WHERE id = ?", [(int) $car['id']]);
    insert('payouts', ['seller_id' => (int) $car['seller_id'], 'order_id' => $orderId,
        'amount' => (float) $car['price'] * 0.96, 'status' => 'pending']);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    apiJson(['ok' => false, 'message' => 'Checkout failed: ' . $ex->getMessage()], 500);
}
notify((int) $car['seller_id'], 'Car reserved', vehicleTitle($car), 'seller/listings.php');
apiJson(['ok' => true, 'order_id' => $orderId, 'payment_id' => $payId, 'status' => 'confirmed'], 201);
