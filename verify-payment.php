<?php
// Razorpay callback: verifies the payment signature, then confirms the booking.
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';

header('Content-Type: application/json');

function verifyFail(string $error): void
{
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { verifyFail('Invalid request.'); }
$u = user();
if (!$u) { verifyFail('Session expired, please log in again.'); }
$sent = $_POST['_token'] ?? '';
if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) { verifyFail('Session expired, please try again.'); }

$orderId = (int) ($_POST['order_id'] ?? 0);
$rzpOrder = (string) ($_POST['razorpay_order_id'] ?? '');
$rzpPay = (string) ($_POST['razorpay_payment_id'] ?? '');
$rzpSig = (string) ($_POST['razorpay_signature'] ?? '');

$order = fetchOne('SELECT * FROM orders WHERE id = ? AND buyer_id = ?', [$orderId, $u['id']]);
if (!$order) { verifyFail('Order not found.'); }
if (($order['status'] ?? '') === 'confirmed') {
    echo json_encode(['ok' => true, 'redirect' => base('payment-success.php?order=' . $orderId)]);
    exit;
}
$payment = fetchOne('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$orderId]);
if (!$payment || (string) $payment['txn_ref'] !== $rzpOrder) { verifyFail('Order mismatch, please retry.'); }
if (!razorpayVerifySignature($rzpOrder, $rzpPay, $rzpSig)) {
    q("UPDATE payments SET status = 'failed' WHERE id = ?", [(int) $payment['id']]);
    verifyFail('Signature check failed - payment NOT captured as confirmed.');
}

try {
    confirmBookingPayment($orderId, $rzpPay, (string) ($payment['method'] ?? 'upi'));
} catch (Throwable $ex) {
    verifyFail('Payment verified but booking failed: ' . $ex->getMessage());
}
logActivity((int) $u['id'], 'order.created', 'Order #' . $orderId . ' via Razorpay ' . $rzpPay);
echo json_encode(['ok' => true, 'redirect' => base('payment-success.php?order=' . $orderId)]);
