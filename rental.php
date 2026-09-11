<?php
require_once __DIR__ . '/includes/rentals.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';

$u = requireLogin();
$id = (int) ($_GET['id'] ?? 0);
$r = findRental($id, (int) $u['id']);
if (!$r) { flash('error', 'Rental not found.'); redirect(base('my-rentals.php')); }
$status = (string) ($r['status'] ?? 'pending');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');
    $r = findRental($id, (int) $u['id']);
    $status = (string) ($r['status'] ?? '');

    if ($action === 'cancel' && $status === 'confirmed') {
        $refund = (float) $r['total_charged'];
        $rzp = (string) ($r['rzp_payment_id'] ?? '');
        $done = false;
        if ($refund > 0 && $rzp !== '' && !str_starts_with($rzp, 'TXN') && razorpayEnabled()) {
            try { razorpayRefund($rzp, (int) round($refund * 100)); $done = true; }
            catch (Throwable $ex) { error_log('DRIVE24 rental cancel refund: ' . $ex->getMessage()); }
        }
        q('UPDATE rentals SET status = ?, refund_amount = ?, refund_status = ? WHERE id = ?', ['cancelled', $refund, $done ? 'done' : 'processing', $id]);
        notify((int) $r['seller_id'], 'Rental cancelled', $r['booking_no'] . ' freed up.', 'seller/orders.php');
        flash('success', 'Booking cancelled. Refund of ' . rupees($refund) . ($done ? ' initiated.' : ' is queued and reaches your source in 5-7 days.'));
        redirect(base('rental.php?id=' . $id));
    } elseif ($action === 'pickup' && $status === 'confirmed') {
        $otp = trim((string) ($_POST['otp'] ?? ''));
        $odo = (int) ($_POST['odo'] ?? 0);
        $fuel = (int) ($_POST['fuel'] ?? -1);
        if ($otp === '' || !hash_equals((string) ($r['pickup_otp'] ?? ''), $otp)) { flash('error', 'Wrong pickup OTP. Check the code from your confirmation.'); }
        elseif ($odo <= 0) { flash('error', 'Record the odometer reading from the dashboard.'); }
        elseif ($fuel < 0 || $fuel > 100) { flash('error', 'Record fuel level as 0-100%.'); }
        elseif (empty($_POST['licence_ok'])) { flash('error', 'Please confirm you carry the original driving licence.'); }
        else {
            q('UPDATE rentals SET status = ?, pickup_odo = ?, pickup_fuel = ?, pickup_notes = ? WHERE id = ?',
                ['active', $odo, $fuel, mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255), $id]);
            notify((int) $r['user_id'], 'Trip started', $r['booking_no'] . ' - drive safe!', 'rental.php?id=' . $id);
            flash('success', 'Handover complete. Your trip is now active - drive safe!');
        }
        redirect(base('rental.php?id=' . $id));
    } elseif ($action === 'sos' && $status === 'active') {
        insert('support_tickets', [
            'user_id' => $u['id'],
            'subject' => 'ROADSIDE SOS - ' . $r['booking_no'],
            'category' => 'general',
            'priority' => 'high',
            'message' => 'SOS from trip screen. Location: ' . trim((string) ($_POST['sos_loc'] ?? '')) . '. Issue: ' . trim((string) ($_POST['sos_msg'] ?? '')),
            'status' => 'open',
        ]);
        notifyAdmins('Roadside SOS', $r['booking_no'] . ' needs help.', 'admin/support.php');
        flash('success', 'SOS raised. Our roadside team will call you in minutes - helpline 1800 200 2424.');
        redirect(base('rental.php?id=' . $id));
    } elseif ($action === 'return' && $status === 'active') {
        $odo = (int) ($_POST['odo'] ?? 0);
        $fuel = (int) ($_POST['fuel'] ?? -1);
        $damage = max(0, (float) ($_POST['damage'] ?? 0));
        if ($odo < (int) ($r['pickup_odo'] ?? 0)) { flash('error', 'Return odometer cannot be below the pickup reading.'); redirect(base('rental.php?id=' . $id)); }
        if ($fuel < 0 || $fuel > 100) { flash('error', 'Record fuel level as 0-100%.'); redirect(base('rental.php?id=' . $id)); }
        $r['return_odo'] = $odo;
        [$driven, $included, $extraKm, $kmAmt] = rentalKmMath($r);
        $fuelShort = max(0, (int) ($r['pickup_fuel'] ?? 0) - $fuel);
        $fuelAmt = round($fuelShort * RENTAL_FUEL_PER_PCT, 2);
        $lateHrs = max(0, (int) ceil((time() - strtotime((string) $r['return_at'])) / 3600));
        $lateAmt = $lateHrs > 0 ? round($lateHrs * ((float) $r['price_per_day'] / 24) * 1.5, 2) : 0.0;
        q('UPDATE rentals SET status = ?, return_odo = ?, return_fuel = ?, return_notes = ? WHERE id = ?',
            ['returned', $odo, $fuel, mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255), $id]);
        if ($kmAmt > 0) { insert('rental_charges', ['rental_id' => $id, 'kind' => 'extra_km', 'label' => $extraKm . ' extra km @ ' . rupees($r['extra_km_rate']) . '/km (' . $driven . ' driven, ' . $included . ' included)', 'amount' => $kmAmt]); }
        if ($fuelAmt > 0) { insert('rental_charges', ['rental_id' => $id, 'kind' => 'fuel', 'label' => $fuelShort . '% fuel shortfall', 'amount' => $fuelAmt]); }
        if ($lateAmt > 0) { insert('rental_charges', ['rental_id' => $id, 'kind' => 'late', 'label' => $lateHrs . ' hr late return (1.5x hourly)', 'amount' => $lateAmt]); }
        if ($damage > 0) { insert('rental_charges', ['rental_id' => $id, 'kind' => 'damage', 'label' => 'Damage/cleaning: ' . mb_substr(trim((string) ($_POST['damage_note'] ?? 'as inspected')), 0, 120), 'amount' => $damage]); }
        // Deposit settlement: refund deposit minus charges.
        $charges = rentalChargesTotal($id);
        $refund = max(0, round((float) $r['deposit'] - $charges, 2));
        $rzp = (string) ($r['rzp_payment_id'] ?? '');
        $done = false;
        if ($refund > 0 && $rzp !== '' && !str_starts_with($rzp, 'TXN') && razorpayEnabled()) {
            try { razorpayRefund($rzp, (int) round($refund * 100)); $done = true; }
            catch (Throwable $ex) { error_log('DRIVE24 rental settlement: ' . $ex->getMessage()); }
        }
        q('UPDATE rentals SET refund_amount = ?, refund_status = ? WHERE id = ?', [$refund, $done ? 'done' : 'processing', $id]);
        notify((int) $r['user_id'], 'Car returned', $r['booking_no'] . ' - refund ' . rupees($refund) . ' ' . ($done ? 'initiated.' : 'queued.'), 'rental.php?id=' . $id);
        flash('success', 'Return recorded. Deposit refund of ' . rupees($refund) . ($done ? ' initiated.' : ' queued - reaches you in 5-7 days.'));
        redirect(base('rental.php?id=' . $id));
    } elseif ($action === 'settle' && $status === 'returned') {
        q("UPDATE rentals SET status = 'settled' WHERE id = ?", [$id]);
        flash('success', 'Trip closed. Thanks for driving with DRIVE24!');
        redirect(base('rental.php?id=' . $id));
    } elseif ($action === 'review' && in_array($status, ['returned', 'settled'], true)) {
        $rating = (int) ($_POST['rating'] ?? 0);
        $exists = fetchOne('SELECT id FROM reviews WHERE author_id = ? AND listing_id = ? AND order_id IS NULL AND comment LIKE ?', [(int) $u['id'], (int) $r['listing_id'], '%' . $r['booking_no'] . '%']);
        if ($rating < 1 || $rating > 5) { flash('error', 'Please pick 1-5 stars.'); }
        elseif ($exists) { flash('error', 'You already reviewed this trip.'); }
        else {
            insert('reviews', ['listing_id' => (int) $r['listing_id'], 'order_id' => null, 'author_id' => (int) $u['id'],
                'target_user_id' => (int) $r['seller_id'], 'reviewer_role' => 'buyer', 'rating' => $rating,
                'title' => mb_substr(trim((string) ($_POST['title'] ?? 'Rental trip')) . ' [' . $r['booking_no'] . ']', 0, 160),
                'comment' => trim((string) ($_POST['comment'] ?? '')), 'status' => 'pending']);
            flash('success', 'Thanks! Your review was submitted for moderation.');
        }
        redirect(base('rental.php?id=' . $id));
    }
    redirect(base('rental.php?id=' . $id));
}

$charges = dbReady() ? fetchAll('SELECT * FROM rental_charges WHERE rental_id = ? ORDER BY id', [$id]) : [];
$chargesTotal = 0.0;
foreach ($charges as $c) { $chargesTotal += (float) $c['amount']; }
$myReview = fetchOne('SELECT * FROM reviews WHERE author_id = ? AND listing_id = ? AND order_id IS NULL AND title LIKE ?', [(int) $u['id'], (int) $r['listing_id'], '%' . $r['booking_no'] . '%']);
$steps = ['pending' => 'Payment', 'confirmed' => 'Confirmed', 'active' => 'On trip', 'returned' => 'Returned', 'settled' => 'Settled'];
$stepIdx = array_search($status, array_keys($steps), true);

renderHeader('Rental ' . $r['booking_no'], 'rent');
?>
<div class="wrap section">
  <p class="muted" style="font-size:13px"><a href="<?= e(base('my-rentals.php')) ?>">My rentals</a> / <?= e($r['booking_no']) ?></p>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <h1 style="font-size:1.5rem;margin:0">Booking <?= e($r['booking_no']) ?></h1>
    <?= statusBadge($status) ?>
  </div>
  <?php if ($status !== 'cancelled'): ?>
  <div class="steps" style="margin-top:12px">
    <?php $i = 0; foreach ($steps as $key => $label): $i++; ?>
      <div class="step <?= $stepIdx !== false && $i - 1 < $stepIdx ? 'done' : ($key === $status ? 'active' : '') ?>"><?= $i ?>. <?= e($label) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="split-3" style="margin-top:16px">
    <div>
      <?php if ($status === 'pending'): ?>
        <div class="card card-pad">
          <h2 style="font-size:1.15rem">Complete payment</h2>
          <p class="muted">Your car is held for a short time. Pay <?= rupees($r['total_charged']) ?> to lock this booking.</p>
          <a class="btn btn-primary" href="<?= e(base('rent-pay.php?id=' . $id)) ?>">Pay now</a>
        </div>
      <?php elseif ($status === 'confirmed'): ?>
        <div class="card card-pad">
          <h2 style="font-size:1.15rem">Pickup &amp; digital handover</h2>
          <p class="muted">At <b><?= e($r['pickup_location']) ?></b> from <b class="num"><?= e(date('d M, h:i A', strtotime((string) $r['pickup_at']))) ?></b>. Inspect the car with the hub executive, then enter the OTP to start your trip. <span class="badge info">Demo OTP: <b class="num"><?= e((string) ($r['pickup_otp'] ?? '')) ?></b></span></p>
          <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
            <?= csrfField() ?><input type="hidden" name="action" value="pickup">
            <div><label class="form-label">Pickup OTP</label><input class="form-control num" name="otp" inputmode="numeric" maxlength="6" placeholder="6-digit code" required></div>
            <div><label class="form-label">Odometer (km)</label><input class="form-control num" type="number" name="odo" min="0" placeholder="e.g. 45210" required></div>
            <div><label class="form-label">Fuel level (%)</label><input class="form-control num" type="number" name="fuel" min="0" max="100" placeholder="e.g. 80" required></div>
            <div><label class="form-label">Scratches/dents noted</label><input class="form-control" name="notes" placeholder="e.g. small scratch, left door"></div>
            <label class="chip" style="grid-column:1/-1"><input type="checkbox" name="licence_ok" value="1"> I carry the original driving licence</label>
            <div style="grid-column:1/-1"><button class="btn btn-primary btn-lg" type="submit">Verify &amp; start trip</button></div>
          </form>
          <form method="post" style="margin-top:12px" onsubmit="return confirm('Cancel this booking? A full refund will be queued.');">
            <?= csrfField() ?><input type="hidden" name="action" value="cancel">
            <button class="btn btn-ghost btn-sm" type="submit">Cancel booking (free before pickup)</button>
          </form>
        </div>
      <?php elseif ($status === 'active'): ?>
        <div class="card card-pad trip-live">
          <h2 style="font-size:1.15rem"><span class="live-dot"></span> Trip in progress</h2>
          <div class="kv"><span>Return by</span><span class="num"><?= e(date('d M, h:i A', strtotime((string) $r['return_at']))) ?> at <?= e($r['return_location']) ?></span></div>
          <div class="kv"><span>Pickup reading</span><span class="num"><?= number_format((int) $r['pickup_odo']) ?> km &middot; <?= (int) $r['pickup_fuel'] ?>% fuel</span></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <a class="btn btn-outline btn-sm" href="<?= e(base('support.php')) ?>">Support</a>
            <a class="btn btn-outline btn-sm" href="<?= e(base('rent-car.php?id=' . (int) $r['listing_id'] . '#policies')) ?>">Trip policies</a>
          </div>
        </div>
        <div class="card card-pad" style="margin-top:18px;border-color:#fecaca">
          <h2 style="font-size:1.15rem">Emergency / roadside assistance</h2>
          <p class="muted" style="font-size:13px">Breakdown, accident or flat tyre? Raise an SOS - helpline <b class="num">1800 200 2424</b> (24x7).</p>
          <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
            <?= csrfField() ?><input type="hidden" name="action" value="sos">
            <div><label class="form-label">Your location</label><input class="form-control" name="sos_loc" placeholder="Road / landmark / GPS" required></div>
            <div><label class="form-label">What happened?</label><input class="form-control" name="sos_msg" placeholder="e.g. flat tyre" required></div>
            <div style="grid-column:1/-1"><button class="btn btn-dark" type="submit">Raise SOS</button></div>
          </form>
        </div>
        <div class="card card-pad" style="margin-top:18px">
          <h2 style="font-size:1.15rem">Return the car</h2>
          <p class="muted" style="font-size:13px">Inspect with the executive and record final readings. Extra KM, fuel shortfall, late hours and damage settle from your <?= rupees($r['deposit']) ?> deposit.</p>
          <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
            <?= csrfField() ?><input type="hidden" name="action" value="return">
            <div><label class="form-label">Return odometer (km)</label><input class="form-control num" type="number" name="odo" min="<?= (int) $r['pickup_odo'] ?>" required></div>
            <div><label class="form-label">Return fuel (%)</label><input class="form-control num" type="number" name="fuel" min="0" max="100" required></div>
            <div><label class="form-label">Damage/cleaning (&#8377;, 0 if none)</label><input class="form-control num" type="number" name="damage" min="0" value="0"></div>
            <div><label class="form-label">Return notes</label><input class="form-control" name="notes" placeholder="Condition at return"></div>
            <div style="grid-column:1/-1"><label class="form-label">Damage detail (if any)</label><input class="form-control" name="damage_note" placeholder="e.g. bumper scuff"></div>
            <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Submit return &amp; settle deposit</button></div>
          </form>
        </div>
      <?php elseif ($status === 'returned' || $status === 'settled'): ?>
        <div class="card card-pad">
          <h2 style="font-size:1.15rem">Deposit settlement</h2>
          <?php if (!$charges): ?><p class="muted">Clean trip - no extra charges.</p><?php endif; ?>
          <?php foreach ($charges as $c): ?>
            <div class="kv"><span><?= e($c['label']) ?> <small class="muted">(<?= e($c['kind']) ?>)</small></span><span class="num"><?= rupees($c['amount']) ?></span></div>
          <?php endforeach; ?>
          <div class="kv"><span>Deposit held</span><span class="num"><?= rupees($r['deposit']) ?></span></div>
          <div class="kv"><span>Deductions</span><span class="num"><?= rupees($chargesTotal) ?></span></div>
          <div class="kv"><span><b>Refund</b></span><b class="num"><?= rupees($r['refund_amount'] ?? 0) ?></b> <?= statusBadge((string) ($r['refund_status'] ?? 'processing')) ?></div>
          <?php if ($status === 'returned'): ?>
            <form method="post" style="margin-top:10px">
              <?= csrfField() ?><input type="hidden" name="action" value="settle">
              <button class="btn btn-primary" type="submit">Confirm &amp; close trip</button>
            </form>
          <?php endif; ?>
        </div>
        <div class="card card-pad" style="margin-top:18px">
          <h2 style="font-size:1.15rem">Review &amp; rating</h2>
          <?php if ($myReview): ?>
            <p style="margin:0"><span class="t-stars"><?= str_repeat('&#9733;', (int) $myReview['rating']) ?></span> <b><?= e((string) ($myReview['title'] ?? '')) ?></b> <?= statusBadge((string) ($myReview['status'] ?? 'pending')) ?></p>
            <?php if (!empty($myReview['comment'])): ?><p class="muted" style="margin:6px 0 0"><?= e((string) $myReview['comment']) ?></p><?php endif; ?>
          <?php else: ?>
            <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
              <?= csrfField() ?><input type="hidden" name="action" value="review">
              <div><label class="form-label">Rating</label><select class="form-select" name="rating"><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Average</option><option value="2">2 - Poor</option><option value="1">1 - Bad</option></select></div>
              <div><label class="form-label">Title</label><input class="form-control" name="title" value="Rental trip" required></div>
              <div style="grid-column:1/-1"><label class="form-label">Comment</label><textarea class="form-control" name="comment" rows="3" placeholder="How was the car and handover?"></textarea></div>
              <div style="grid-column:1/-1"><button class="btn btn-dark" type="submit">Submit review</button></div>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="card card-pad empty">This booking was cancelled. <?= $r['refund_amount'] !== null ? 'Refund of ' . rupees($r['refund_amount']) . ' ' . statusBadge((string) ($r['refund_status'] ?? 'processing')) : '' ?></div>
      <?php endif; ?>
    </div>

    <aside>
      <div class="card card-pad sticky">
        <img src="<?= e(listingImage($r)) ?>" alt="" style="width:100%;border-radius:12px;aspect-ratio:16/10;object-fit:cover">
        <h3 style="margin-top:12px;font-size:1.05rem"><?= e(vehicleTitle($r)) ?></h3>
        <div class="kv"><span>Pickup</span><span class="num"><?= e(date('d M, h:i A', strtotime((string) $r['pickup_at']))) ?></span></div>
        <div class="kv"><span>From</span><span><?= e($r['pickup_location']) ?></span></div>
        <div class="kv"><span>Return</span><span class="num"><?= e(date('d M, h:i A', strtotime((string) $r['return_at']))) ?></span></div>
        <div class="kv"><span>To</span><span><?= e($r['return_location']) ?></span></div>
        <div class="kv"><span>Duration</span><span class="num"><?= (int) $r['days'] ?> day(s)</span></div>
        <div class="kv"><span>Rental</span><span class="num"><?= rupees($r['rental_amount']) ?></span></div>
        <?php if ((float) $r['discount'] > 0): ?><div class="kv"><span>Discount</span><span class="num">- <?= rupees($r['discount']) ?></span></div><?php endif; ?>
        <div class="kv"><span>Tax</span><span class="num"><?= rupees($r['tax']) ?></span></div>
        <div class="kv"><span>Deposit</span><span class="num"><?= rupees($r['deposit']) ?></span></div>
        <div class="kv"><span><b>Charged</b></span><b class="num"><?= rupees($r['total_charged']) ?></b></div>
        <?php if (!empty($r['rzp_payment_id'])): ?><div class="kv"><span>Payment</span><span class="num"><?= e($r['rzp_payment_id']) ?></span></div><?php endif; ?>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
