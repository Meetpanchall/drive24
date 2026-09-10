<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';

$u = requireLogin();
$orderId = (int) ($_GET['order'] ?? 0);
$order = fetchOne(
    'SELECT o.*, v.make, v.model, v.year, v.variant, v.image FROM orders o ' .
    'JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id ' .
    'WHERE o.id = ? AND o.buyer_id = ?',
    [$orderId, $u['id']]
);
if (!$order) { flash('error', 'Order not found.'); redirect(base('orders.php')); }
if (($order['status'] ?? '') === 'confirmed') { redirect(base('payment-success.php?order=' . $orderId)); }
if (!razorpayEnabled()) { flash('error', 'Online payment is not configured.'); redirect(base('orders.php')); }

// Reuse the gateway order if one was already created for this booking.
$payment = fetchOne('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$orderId]);
$rzpOrderId = '';
$rzpError = '';
if (!$payment) {
    $rzpError = 'Payment record not found for this order.';
} elseif (str_starts_with((string) $payment['txn_ref'], 'order_')) {
    $rzpOrderId = (string) $payment['txn_ref'];
} else {
    try {
        $rzp = razorpayCreateOrder((int) round((float) $order['booking_amount'] * 100), (string) $order['order_no']);
        $rzpOrderId = (string) $rzp['id'];
        q('UPDATE payments SET txn_ref = ? WHERE id = ?', [$rzpOrderId, (int) $payment['id']]);
    } catch (Throwable $ex) {
        $rzpError = $ex->getMessage();
        error_log('DRIVE24 pay.php: ' . $ex->getMessage());
    }
}

renderHeader('Complete payment', '');
?>
<div class="wrap section" style="max-width:680px">
  <div class="steps">
    <div class="step done">1. Car selected</div>
    <div class="step active">2. Booking &amp; payment</div>
    <div class="step">3. Documentation</div>
    <div class="step">4. Delivery</div>
  </div>
  <div class="card card-pad" style="text-align:center;margin-top:16px">
    <span class="badge info">Razorpay secure payment</span>
    <h1 style="font-size:1.4rem;margin-top:10px">Pay <?= rupees($order['booking_amount']) ?> to reserve</h1>
    <p class="muted"><?= e($order['year'] . ' ' . $order['make'] . ' ' . $order['model'] . ' ' . $order['variant']) ?> &middot; Order <span class="num"><?= e($order['order_no']) ?></span></p>
    <?php if ($rzpError): ?>
      <div class="alert error" style="text-align:left"><?= e($rzpError) ?></div>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?= e(base('pay.php?order=' . $orderId)) ?>">Retry payment</a>
        <a class="btn btn-outline" href="<?= e(base('orders.php')) ?>">My orders</a>
      </div>
    <?php else: ?>
      <div id="payMsg"></div>
      <button class="btn btn-primary btn-lg btn-block" id="rzpBtn" type="button">Pay <?= rupees($order['booking_amount']) ?> with Razorpay</button>
      <p class="muted" style="font-size:12.5px;margin-top:10px">UPI, cards, netbanking, wallets &amp; EMI - processed securely by Razorpay. Do not refresh while paying.</p>
      <p><a class="btn btn-ghost btn-sm" href="<?= e(base('orders.php')) ?>">Cancel and go to My orders</a></p>
    <?php endif; ?>
  </div>
</div>
<?php if (!$rzpError): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
  var btn = document.getElementById('rzpBtn');
  var msg = document.getElementById('payMsg');
  function fail(text) {
    msg.innerHTML = '<div class="alert error" style="text-align:left">' + text + '</div>';
    btn.disabled = false;
    btn.textContent = 'Retry payment';
  }
  var rzp = new Razorpay({
    key: <?= json_encode(razorpayKeyId()) ?>,
    order_id: <?= json_encode($rzpOrderId) ?>,
    name: 'DRIVE24',
    description: <?= json_encode('Booking for order ' . $order['order_no']) ?>,
    theme: { color: '#0b5cff' },
    prefill: {
      name: <?= json_encode((string) ($u['name'] ?? '')) ?>,
      email: <?= json_encode((string) ($u['email'] ?? '')) ?>,
      contact: <?= json_encode((string) ($u['mobile'] ?? '')) ?>
    },
    modal: { ondismiss: function () { btn.disabled = false; } },
    handler: function (resp) {
      btn.disabled = true;
      btn.textContent = 'Verifying payment...';
      var body = new URLSearchParams({
        _token: <?= json_encode(csrfToken()) ?>,
        order_id: String(<?= (int) $orderId ?>),
        razorpay_order_id: resp.razorpay_order_id || '',
        razorpay_payment_id: resp.razorpay_payment_id || '',
        razorpay_signature: resp.razorpay_signature || ''
      });
      fetch(<?= json_encode(base('verify-payment.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.ok && d.redirect) { window.location.href = d.redirect; }
        else { fail((d && d.error) ? d.error : 'Payment verification failed.'); }
      }).catch(function () { fail('Network error while verifying. Please retry.'); });
    }
  });
  rzp.on('payment.failed', function (resp) {
    fail('Payment failed: ' + ((resp.error && resp.error.description) || 'try again.'));
  });
  btn.addEventListener('click', function () { btn.disabled = true; rzp.open(); });
  // Open the gateway immediately - this page is the redirect target of Pay.
  rzp.open();
})();
</script>
<?php endif; ?>
<?php renderFooter(); ?>
