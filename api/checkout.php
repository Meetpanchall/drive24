<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/razorpay.php';

// POST /api/checkout.php {listing_id, method, city, address, finance?, loan_amount?, tenure?}
$u = apiUser();
if ($u === null || !dbReady()) { apiJson(['ok' => false, 'message' => 'Sign in required.'], 401); }
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
verifyApiCsrf(is_array($in) ? $in : null);
$car = findListing((int) ($in['listing_id'] ?? 0));
if (!$car || $car['status'] !== 'approved') { apiJson(['ok' => false, 'message' => 'Car is not available for booking.'], 422); }
if (!empty($car['hold_until']) && $car['hold_until'] > date('Y-m-d H:i:s') && (int) ($car['hold_buyer_id'] ?? 0) !== (int) $u['id']) {
    apiJson(['ok' => false, 'message' => 'This car is reserved for another buyer.'], 409);
}

$method = in_array($in['method'] ?? '', ['upi', 'card', 'netbanking', 'finance', 'wallet'], true) ? (string) $in['method'] : 'upi';
$booking = (float) setting('booking_amount', 25000);

// Step 1: freeze the booking as payment-pending. Money moves only after the
// gateway confirms - an API call alone must never mark an order paid.
$pdo = db();
$pdo->beginTransaction();
try {
    $orderId = insert('orders', [
        'order_no' => 'D24-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3))),
        'listing_id' => (int) $car['id'], 'buyer_id' => $u['id'], 'amount' => (float) $car['price'],
        'booking_amount' => $booking, 'finance_opted' => !empty($in['finance']) ? 1 : 0,
        'loan_amount' => (float) ($in['loan_amount'] ?? 0), 'tenure_months' => (int) ($in['tenure'] ?? 0),
        'status' => 'pending', 'delivery_city' => mb_substr(trim((string) ($in['city'] ?? '')), 0, 80),
        'delivery_address' => mb_substr(trim((string) ($in['address'] ?? '')), 0, 255),
        'delivery_date' => date('Y-m-d', strtotime('+7 days'))]);
    insert('payments', ['order_id' => $orderId, 'txn_ref' => 'PEND-' . date('ymdHis') . random_int(10, 99),
        'method' => $method, 'amount' => $booking, 'status' => 'pending']);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    apiJson(['ok' => false, 'message' => 'Checkout failed: ' . $ex->getMessage()], 500);
}

// Step 2: gateway handoff. When no gateway is configured (local/test mode),
// settle through the same idempotent confirm path the web checkout uses.
if (!razorpayEnabled()) {
    try {
        confirmBookingPayment($orderId, 'TXN' . date('ymdHis') . random_int(10, 99), $method);
    } catch (Throwable $ex) {
        apiJson(['ok' => false, 'message' => 'Payment could not be recorded: ' . $ex->getMessage(), 'order_id' => $orderId], 500);
    }
    logActivity((int) $u['id'], 'order.created', 'Order #' . $orderId . ' via API');
    apiJson(['ok' => true, 'order_id' => $orderId, 'status' => 'confirmed'], 201);
}
logActivity((int) $u['id'], 'order.created', 'Order #' . $orderId . ' via API (pending payment)');
apiJson(['ok' => true, 'order_id' => $orderId, 'status' => 'pending', 'pay_url' => base('pay.php?order=' . $orderId)], 201);
