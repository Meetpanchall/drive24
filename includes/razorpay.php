<?php
declare(strict_types=1);

require_once __DIR__ . '/listings.php';

/**
 * Razorpay integration (Orders API + Checkout.js + signature verification).
 *
 * Setup: paste your Key ID / Key Secret from the Razorpay Dashboard
 * (Settings > API Keys - use TEST keys first) into config/config.php
 * or the RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET environment variables.
 * Until keys are set, checkout runs in simulated test mode so the demo
 * keeps working out of the box.
 */

function razorpayConfig(): array
{
    $c = config('razorpay');
    return is_array($c) ? $c : [];
}

function razorpayEnabled(): bool
{
    $c = razorpayConfig();
    $id = (string) ($c['key_id'] ?? '');
    $secret = (string) ($c['key_secret'] ?? '');
    return $id !== '' && $secret !== '';
}

function razorpayKeyId(): string
{
    $c = razorpayConfig();
    return (string) ($c['key_id'] ?? '');
}

/** Create an order on Razorpay. Amount is in paise. Returns the decoded order array. */
function razorpayCreateOrder(int $amountPaise, string $receipt): array
{
    $id = razorpayKeyId();
    $rc = razorpayConfig();
    $secret = (string) ($rc['key_secret'] ?? '');
    if ($id === '' || $secret === '') {
        throw new RuntimeException('Razorpay keys are not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for Razorpay. Enable extension=curl and retry.');
    }
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $id . ':' . $secret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'amount'          => $amountPaise,
            'currency'        => 'INR',
            'receipt'         => substr($receipt, 0, 40),
            'payment_capture' => 1,
        ]),
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $raw === '') {
        throw new RuntimeException('Could not reach Razorpay: ' . $err);
    }
    $data = json_decode((string) $raw, true);
    if ($http < 200 || $http >= 300 || !is_array($data) || empty($data['id'])) {
        $msg = is_array($data) ? (string) (($data['error']['description'] ?? '') ?: $raw) : (string) $raw;
        throw new RuntimeException('Razorpay order failed (HTTP ' . $http . '): ' . substr($msg, 0, 180));
        error_log('Razorpay order failed (HTTP ' . $http . '): ' . substr($msg, 0, 300));
    }
    return $data;
}

/** Verify the payment signature sent back by Checkout.js. */
function razorpayVerifySignature(string $orderId, string $paymentId, string $signature): bool
{
    $rc = razorpayConfig();
    $secret = (string) ($rc['key_secret'] ?? '');
    if ($secret === '' || $orderId === '' || $paymentId === '' || $signature === '') { return false; }
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    return hash_equals($expected, $signature);
}

/**
 * Mark a pending booking as paid and write the escrow hold, RC transfer
 * row and seller payout inside one transaction. Called from checkout
 * (simulated test mode) and from verify-payment.php (real Razorpay flow).
 */
function confirmBookingPayment(int $orderId, string $txnRef, string $method): void
{
    $order = fetchOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) { throw new RuntimeException('Order not found.'); }
    if (($order['status'] ?? '') === 'confirmed') { return; } // idempotent (double callback safe)
    $car = findListing((int) $order['listing_id']);
    if ($car === null) { throw new RuntimeException('Listing not found.'); }
    $booking = (float) $order['booking_amount'];
    $method = in_array($method, ['upi', 'card', 'netbanking', 'finance'], true) ? $method : 'upi';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q("UPDATE orders SET status = 'confirmed' WHERE id = ?", [$orderId]);
        q('UPDATE payments SET txn_ref = ?, method = ?, status = ? WHERE order_id = ? AND status = ?', [$txnRef, $method, 'paid', $orderId, 'pending']);
        insert('escrow_ledger', [
            'order_id' => $orderId, 'kind' => 'hold', 'amount' => $booking,
            'note' => 'Booking held in DRIVE24 escrow - released to seller after delivery',
        ]);
        insert('rc_transfers', [
            'order_id' => $orderId, 'listing_id' => (int) $order['listing_id'],
            'buyer_id' => (int) $order['buyer_id'], 'seller_id' => (int) $car['seller_id'],
            'status' => 'sale_completed',
        ]);
        q("UPDATE listings SET status = 'reserved' WHERE id = ?", [(int) $order['listing_id']]);
        insert('payouts', ['seller_id' => (int) $car['seller_id'], 'order_id' => $orderId, 'amount' => (float) $car['price'] * 0.96, 'status' => 'pending']);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}
