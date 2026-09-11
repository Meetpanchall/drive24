<?php
// Online sale agreement: generated from the order, e-signed by buyer + seller via OTP.
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$id = (int) ($_GET['order'] ?? 0);
$order = fetchOne('SELECT o.*, v.make, v.model, v.variant, v.year, v.reg_number, v.vin, v.km_driven,
    l.seller_id, s.name AS seller_name, s.company AS seller_company, s.city AS seller_city, s.mobile AS seller_mobile,
    b.name AS buyer_name, b.mobile AS buyer_mobile, b.city AS buyer_city
    FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id
    JOIN users s ON s.id = l.seller_id JOIN users b ON b.id = o.buyer_id
    WHERE o.id = ?', [$id]);
if (!$order) { flash('error', 'Order not found.'); redirect(base('orders.php')); }
$isBuyer = (int) $u['id'] === (int) $order['buyer_id'];
$isSeller = (int) $u['id'] === (int) $order['seller_id'];
if (!$isBuyer && !$isSeller && ($u['role'] ?? '') !== 'admin') { flash('error', 'Not your order.'); redirect(base('orders.php')); }
$myRole = $isBuyer ? 'buyer' : 'seller';
$sigs = orderSignatures($id);
$mine = (bool) ($sigs[$myRole] ?? false);
$otpKey = 'agree_otp_' . $id . '_' . $myRole;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($u['role'] ?? '') !== 'admin') {
    verifyCsrf();
    if (($_POST['step'] ?? '') === 'send') {
        $code = (string) random_int(100000, 999999);
        $_SESSION[$otpKey] = ['hash' => hash('sha256', $code), 'exp' => time() + 600];
        flash('success', 'OTP sent to your mobile. Demo code: ' . $code);
        redirect(base('agreement.php?order=' . $id . '&sign=1'));
    }
    if (($_POST['step'] ?? '') === 'sign') {
        $sess = $_SESSION[$otpKey] ?? null;
        $ok = is_array($sess) && ($sess['exp'] ?? 0) >= time()
            && hash_equals((string) ($sess['hash'] ?? ''), hash('sha256', trim((string) ($_POST['otp'] ?? ''))));
        if ($mine) { flash('error', 'You have already signed this agreement.'); }
        elseif (!$ok) { flash('error', 'Wrong or expired OTP. Request a fresh code.'); }
        elseif (empty($_POST['agree'])) { flash('error', 'Please tick the consent box to sign.'); }
        elseif (mb_strtolower(trim((string) ($_POST['sig_name'] ?? ''))) !== mb_strtolower(trim((string) ($u['name'] ?? '')))) {
            flash('error', 'Type your full account name exactly as shown to sign.');
        } else {
            unset($_SESSION[$otpKey]);
            insert('documents', ['user_id' => (int) $u['id'], 'order_id' => $id, 'doc_type' => 'sale_agreement',
                'doc_name' => 'Sale agreement ' . $order['order_no'] . ' - signed by ' . $myRole . ' ' . $u['name'],
                'file_url' => null, 'status' => 'verified']);
            $other = $isBuyer ? (int) $order['seller_id'] : (int) $order['buyer_id'];
            $link = $isBuyer ? 'seller/orders.php' : 'order.php?id=' . $id;
            notify($other, 'Agreement signed by ' . $myRole, 'Order ' . $order['order_no'], $link);
            logActivity((int) $u['id'], 'agreement.signed', 'Order #' . $id . ' by ' . $myRole);
            flash('success', 'Signed! The agreement is now e-stamped in the document vault.');
        }
        redirect(base('agreement.php?order=' . $id));
    }
}

$both = $sigs['buyer'] && $sigs['seller'];
renderHeader('Sale agreement ' . $order['order_no'], '');
?>
<div class="wrap section" style="max-width:820px">
  <h1 style="font-size:1.5rem">Vehicle sale agreement</h1>
  <p class="muted">Order <b class="num"><?= e($order['order_no']) ?></b> &middot; <?= statusBadge($both ? 'verified' : 'pending') ?> <?= $both ? 'Both parties signed' : 'Awaiting signatures' ?></p>
  <div class="card card-pad agree-doc">
    <h2 style="text-align:center;font-size:1.2rem">AGREEMENT TO SELL - MOTOR VEHICLE</h2>
    <p>This agreement is made on <b><?= e(date('d F Y')) ?></b> at <?= e((string) ($order['delivery_city'] ?? 'India')) ?> between:</p>
    <div class="kv"><span>Seller</span><span><b><?= e((string) ($order['seller_company'] ?: $order['seller_name'])) ?></b> (<?= e((string) ($order['seller_mobile'] ?? '')) ?>, <?= e((string) ($order['seller_city'] ?? '')) ?>)</span></div>
    <div class="kv"><span>Buyer</span><span><b><?= e($order['buyer_name']) ?></b> (<?= e((string) ($order['buyer_mobile'] ?? '')) ?>, <?= e((string) ($order['buyer_city'] ?? '')) ?>)</span></div>
    <div class="kv"><span>Vehicle</span><span><?= e($order['year'] . ' ' . $order['make'] . ' ' . $order['model'] . ' ' . $order['variant']) ?></span></div>
    <div class="kv"><span>Registration</span><span class="num"><?= e((string) ($order['reg_number'] ?? '-')) ?></span></div>
    <div class="kv"><span>VIN / chassis</span><span class="num"><?= e((string) ($order['vin'] ?? '-')) ?></span></div>
    <div class="kv"><span>Odometer</span><span class="num"><?= number_format((int) $order['km_driven']) ?> km</span></div>
    <div class="kv"><span>Agreed sale price</span><b class="num"><?= rupees($order['amount']) ?></b></div>
    <div class="kv"><span>Booking advance paid</span><span class="num"><?= rupees($order['booking_amount']) ?> (held in DRIVE24 escrow)</span></div>
    <ol class="pol-list">
      <li>The seller warrants clear title, genuine odometer and accident disclosures as per the inspection report.</li>
      <li>Balance is payable on delivery; DRIVE24 releases escrow to the seller after the 7-day return window.</li>
      <li>DRIVE24 files Form 29/30 and completes RC transfer to the buyer within 30 working days.</li>
      <li>Disputes fall under the jurisdiction of the buyer's delivery city courts.</li>
    </ol>
    <div class="sig-grid">
      <div class="sig-box"><small>BUYER - <?= e($order['buyer_name']) ?></small><b><?= $sigs['buyer'] ? 'Signed &#10003;' : 'Awaiting signature' ?></b></div>
      <div class="sig-box"><small>SELLER - <?= e((string) ($order['seller_company'] ?: $order['seller_name'])) ?></small><b><?= $sigs['seller'] ? 'Signed &#10003;' : 'Awaiting signature' ?></b></div>
    </div>
  </div>

  <?php if (($u['role'] ?? '') !== 'admin' && !$mine): ?>
    <div class="card card-pad" style="margin-top:18px">
      <h2 style="font-size:1.15rem">E-sign as <?= e($myRole) ?> (<?= e($u['name']) ?>)</h2>
      <?php if (!isset($_GET['sign'])): ?>
        <p class="muted">We send a 6-digit OTP to your registered mobile. Entering it counts as your digital signature.</p>
        <form method="post"><?= csrfField() ?><input type="hidden" name="step" value="send">
          <button class="btn btn-primary" type="submit">Send signing OTP</button></form>
      <?php else: ?>
        <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
          <?= csrfField() ?><input type="hidden" name="step" value="sign">
          <div><label class="form-label">OTP (valid 10 min)</label><input class="form-control num" name="otp" inputmode="numeric" maxlength="6" required></div>
          <div><label class="form-label">Type your full name: <?= e($u['name']) ?></label><input class="form-control" name="sig_name" required></div>
          <label class="chip" style="grid-column:1/-1"><input type="checkbox" name="agree" value="1" required> I have read this agreement and consent to e-sign it</label>
          <div style="grid-column:1/-1"><button class="btn btn-primary btn-lg" type="submit">Sign agreement</button></div>
        </form>
      <?php endif; ?>
    </div>
  <?php elseif ($mine): ?>
    <div class="alert success" style="margin-top:18px">You signed this agreement. <?= $both ? 'Both signatures are complete - handover can proceed.' : 'Waiting on the other party.' ?></div>
  <?php endif; ?>
  <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
    <?php if ($isBuyer): ?><a class="btn btn-outline btn-sm" href="<?= e(base('order.php?id=' . $id)) ?>">Back to order</a><?php endif; ?>
    <?php if ($isSeller): ?><a class="btn btn-outline btn-sm" href="<?= e(base('seller/orders.php')) ?>">Back to sales</a><?php endif; ?>
    <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">Print / save PDF</button>
  </div>
</div>
<?php renderFooter(); ?>
