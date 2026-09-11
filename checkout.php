<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';

$u = requireLogin();
$listingId = (int) ($_GET['listing'] ?? $_POST['listing_id'] ?? 0);
$car = findListing($listingId);
if ($car === null) { flash('error', 'Select a car to book.'); redirect(base('cars.php')); }
if (!empty($car['hold_until']) && $car['hold_until'] > date('Y-m-d H:i:s') && (int) ($car['hold_buyer_id'] ?? 0) !== (int) $u['id']) {
    flash('error', 'This car is reserved for another buyer until ' . date('d M, h:i A', strtotime((string) $car['hold_until'])) . '.');
    redirect(base('cars.php'));
}

$booking = (float) setting('booking_amount', 25000);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $finance = isset($_POST['finance']);
    $method  = in_array($_POST['method'] ?? 'upi', ['upi', 'card', 'netbanking', 'finance'], true) ? $_POST['method'] : 'upi';
    $loan    = $finance ? (float) ($_POST['loan_amount'] ?? 0) : 0;
    $tenure  = $finance ? (int) ($_POST['tenure'] ?? 60) : 0;
    $hub     = ($_POST['fulfilment'] ?? 'home') === 'hub';
    $city    = trim((string) ($_POST['city'] ?? ''));
    $address = $hub ? 'DRIVE24 hub pickup, ' . $city : trim((string) ($_POST['address'] ?? ''));

    // Step 1: freeze the booking as payment-pending. Money moves only after
    // the gateway confirms, so abandoned checkouts never reserve the car.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $orderId = insert('orders', [
            'order_no'       => 'D24-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'listing_id'     => $listingId,
            'buyer_id'       => $u['id'],
            'amount'         => (float) $car['price'],
            'booking_amount' => $booking,
            'finance_opted'  => $finance ? 1 : 0,
            'loan_amount'    => $loan,
            'tenure_months'  => $tenure,
            'status'         => 'pending',
            'delivery_city'  => $city,
            'delivery_address' => $address,
            'delivery_date'  => date('Y-m-d', strtotime('+7 days')),
        ]);
        insert('payments', [
            'order_id' => $orderId,
            'txn_ref'  => 'PEND-' . date('ymdHis') . random_int(10, 99),
            'method'   => $method,
            'amount'   => $booking,
            'status'   => 'pending',
        ]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('error', 'Booking could not be created: ' . $ex->getMessage());
        redirect(base('checkout.php?listing=' . $listingId));
    }

    // Step 2: hand off to the Razorpay gateway (or simulate it in test mode).
    if (razorpayEnabled()) {
        redirect(base('pay.php?order=' . $orderId));
    }
    try {
        confirmBookingPayment($orderId, 'TXN' . date('ymdHis') . random_int(10, 99), $method);
    } catch (Throwable $ex) {
        flash('error', 'Payment could not be recorded: ' . $ex->getMessage());
        redirect(base('checkout.php?listing=' . $listingId));
    }
    logActivity((int) $u['id'], 'order.created', 'Order #' . $orderId);
    redirect(base('payment-success.php?order=' . $orderId));
}

$emi = emiAmount((float) $car['price'] * 0.8, 9.5, 60);
renderHeader('Checkout', '');
?>
<div class="wrap section">
  <div class="steps">
    <div class="step done">1. Car selected</div>
    <div class="step active">2. Booking &amp; payment</div>
    <div class="step">3. Documentation</div>
    <div class="step">4. Delivery</div>
  </div>
  <div class="split-3">
    <form class="card card-pad" method="post">
      <?= csrfField() ?><input type="hidden" name="listing_id" value="<?= (int) $car['id'] ?>">
      <h1 style="font-size:1.4rem">Secure checkout</h1>
      <p class="muted">Pay a refundable booking amount of <b class="num"><?= rupees($booking) ?></b> to reserve this car. The order, payment and seller payout rows are written inside one MySQL transaction.</p>
      <h3 style="font-size:1rem;margin-top:14px">Delivery or pickup</h3>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <label class="chip"><input type="radio" name="fulfilment" value="home" checked> Home delivery</label>
        <label class="chip"><input type="radio" name="fulfilment" value="hub"> Collect from DRIVE24 hub</label>
      </div>
      <h3 style="font-size:1rem;margin-top:14px">Delivery details</h3>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <div><label class="form-label">City</label><input class="form-control" name="city" value="<?= e((string) $u['city']) ?>" required></div>
        <div><label class="form-label">Mobile</label><input class="form-control" value="<?= e((string) $u['mobile']) ?>" disabled></div>
        <div style="grid-column:1/-1"><label class="form-label">Delivery address</label><input class="form-control" name="address" required></div>
      </div>
      <h3 style="font-size:1rem;margin-top:16px">Payment method</h3>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach (['upi' => 'UPI', 'card' => 'Credit / debit card', 'netbanking' => 'Net banking', 'finance' => 'Pay via finance'] as $val => $label): ?>
          <label class="chip"><input type="radio" name="method" value="<?= e($val) ?>" <?= $val === 'upi' ? 'checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
      <h3 style="font-size:1rem;margin-top:16px">Finance (optional)</h3>
      <label class="chip"><input type="checkbox" name="finance" value="1"> Apply for a car loan</label>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
        <div><label class="form-label">Loan amount</label><input class="form-control num" type="number" name="loan_amount" value="<?= (int) ((float) $car['price'] * 0.8) ?>"></div>
        <div><label class="form-label">Tenure</label><select class="form-select" name="tenure"><option>48</option><option selected>60</option><option>72</option><option>84</option></select></div>
      </div>
      <button class="btn btn-primary btn-lg btn-block" style="margin-top:18px" type="submit">Pay <?= rupees($booking) ?> &amp; reserve</button>
      <p class="muted" style="font-size:12.5px;margin-top:10px"><?= razorpayEnabled() ? 'You will be redirected to <b>Razorpay</b> to complete the payment securely.' : 'Test mode: no keys configured, so this demo confirms the booking instantly.' ?></p>
      <p class="muted" style="font-size:12.5px;margin-top:4px">Escrow protected &middot; PCI-DSS aligned &middot; 7-day easy return &middot; Free RC transfer.</p>
    </form>

    <aside class="card card-pad sticky">
      <img src="<?= e(listingImage($car)) ?>" alt="" style="width:100%;border-radius:12px;aspect-ratio:16/10;object-fit:cover">
      <h3 style="margin-top:12px;font-size:1.05rem"><?= e(vehicleTitle($car)) ?></h3>
      <div class="kv"><span>Car price</span><span class="num"><?= rupees($car['price']) ?></span></div>
      <div class="kv"><span>RC transfer</span><span class="num"><?= rupees(3499) ?></span></div>
      <div class="kv"><span>Booking now</span><span class="num"><?= rupees($booking) ?></span></div>
      <div class="kv"><span>EMI option</span><span class="num"><?= rupees($emi) ?>/mo</span></div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
