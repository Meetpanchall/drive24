<?php
require_once __DIR__ . '/includes/rentals.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';

$u = requireLogin();
$rentalId = (int) ($_GET['id'] ?? 0);
$r = findRental($rentalId, (int) $u['id']);
if (!$r) { flash('error', 'Rental not found.'); redirect(base('my-rentals.php')); }
if (($r['status'] ?? '') !== 'pending') { redirect(base('rental.php?id=' . $rentalId)); }
if (!razorpayEnabled()) { flash('error', 'Online payment is not configured.'); redirect(base('my-rentals.php')); }

$rzpOrderId = (string) ($r['rzp_order_id'] ?? '');
$rzpError = '';
if ($rzpOrderId === '') {
    try {
        $rzp = razorpayCreateOrder((int) round((float) $r['total_charged'] * 100), (string) $r['booking_no']);
        $rzpOrderId = (string) $rzp['id'];
        q('UPDATE rentals SET rzp_order_id = ? WHERE id = ?', [$rzpOrderId, $rentalId]);
    } catch (Throwable $ex) {
        $rzpError = $ex->getMessage();
        error_log('DRIVE24 rent-pay: ' . $ex->getMessage());
    }
}

renderHeader('Pay for rental', 'rent');
?>
<div class="wrap section" style="max-width:680px">
  <div class="steps">
    <div class="step done">1. Car selected</div>
    <div class="step done">2. KYC &amp; summary</div>
    <div class="step active">3. Payment</div>
    <div class="step">4. Pickup &amp; drive</div>
  </div>
  <div class="card card-pad" style="text-align:center;margin-top:16px">
    <span class="badge info">Razorpay secure payment</span>
    <h1 style="font-size:1.4rem;margin-top:10px">Pay <?= rupees($r['total_charged']) ?> to confirm</h1>
    <p class="muted"><?= e(vehicleTitle($r)) ?> &middot; Booking <span class="num"><?= e($r['booking_no']) ?></span><br>
      <span class="num"><?= e(date('d M, h:i A', strtotime((string) $r['pickup_at']))) ?> &rarr; <?= e(date('d M, h:i A', strtotime((string) $r['return_at']))) ?></span>
      (rental <?= rupees((float) $r['rental_amount'] - (float) $r['discount'] + (float) $r['tax']) ?> + <?= rupees($r['deposit']) ?> refundable deposit)</p>
    <?php if ($rzpError): ?>
      <div class="alert error" style="text-align:left"><?= e($rzpError) ?></div>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?= e(base('rent-pay.php?id=' . $rentalId)) ?>">Retry payment</a>
        <a class="btn btn-outline" href="<?= e(base('my-rentals.php')) ?>">My rentals</a>
      </div>
    <?php else: ?>
      <div id="payMsg"></div>
      <button class="btn btn-primary btn-lg btn-block" id="rzpBtn" type="button">Pay <?= rupees($r['total_charged']) ?> with Razorpay</button>
      <p class="muted" style="font-size:12.5px;margin-top:10px">UPI, cards, netbanking, wallets &amp; EMI - processed securely by Razorpay. Do not refresh while paying.</p>
      <p><a class="btn btn-ghost btn-sm" href="<?= e(base('my-rentals.php')) ?>">Cancel and go to My rentals</a></p>
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
    name: 'DRIVE24 Rentals',
    description: <?= json_encode('Rental booking ' . $r['booking_no']) ?>,
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
        kind: 'rental',
        rental_id: String(<?= (int) $rentalId ?>),
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
  rzp.open();
})();
</script>
<?php endif; ?>
<?php renderFooter(); ?>
