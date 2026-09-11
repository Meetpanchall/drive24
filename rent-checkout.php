<?php
require_once __DIR__ . '/includes/rentals.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';

$u = requireLogin();
$listingId = (int) ($_GET['listing'] ?? $_POST['listing_id'] ?? 0);
$car = findRentalCar($listingId);
if (!$car) { flash('error', 'This car is not available for rent.'); redirect(base('rent.php')); }

$pickupLoc = trim((string) ($_POST['pickup_loc'] ?? $_GET['pickup_loc'] ?? ''));
$returnLoc = trim((string) ($_POST['return_loc'] ?? $_GET['pickup_loc'] ?? ''));
$pickupAt = (string) ($_POST['pickup_at'] ?? $_GET['pickup_at'] ?? '');
$returnAt = (string) ($_POST['return_at'] ?? $_GET['return_at'] ?? '');
foreach (['pickupAt', 'returnAt'] as $k) {
    $$k = ($$k !== '' && ($t = strtotime($$k)) !== false) ? date('Y-m-d H:i:s', $t) : '';
}
$kyc = rentalKyc((int) $u['id']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    // Inline driving-licence upload for the rental KYC gate.
    if (($_POST['action'] ?? '') === 'kyc') {
        $file = !empty($_FILES['doc_file']['name'] ?? '') ? saveDocument($_FILES['doc_file'], 'kyc') : null;
        if ($file === null) {
            flash('error', 'Please attach your driving licence (PDF or image, max 8 MB).');
        } else {
            insert('documents', [
                'user_id' => $u['id'], 'doc_type' => 'kyc',
                'doc_name' => 'Driving Licence - ' . trim((string) ($_POST['id_number'] ?? '')),
                'file_url' => $file, 'status' => 'pending',
            ]);
            notifyAdmins('Rental KYC uploaded', ($u['name'] ?? '') . ' - driving licence pending verification.', 'admin/kyc.php');
            flash('success', 'Driving licence uploaded. Compliance verifies within 24 hours - you can complete this booking meanwhile.');
        }
        redirect(base('rent-checkout.php?listing=' . $listingId . '&pickup_loc=' . urlencode($pickupLoc) . '&pickup_at=' . urlencode($pickupAt) . '&return_at=' . urlencode($returnAt)));
    }

    // Final booking validation.
    $err = '';
    if ($pickupLoc === '' || $returnLoc === '') { $err = 'Pickup and return locations are required.'; }
    elseif ($pickupAt === '' || $returnAt === '' || $returnAt <= $pickupAt) { $err = 'Please choose a valid pickup/return window.'; }
    elseif (strtotime($pickupAt) < time() - 1800) { $err = 'Pickup time has already passed.'; }
    elseif ((strtotime($returnAt) - strtotime($pickupAt)) > 30 * 86400) { $err = 'Trips are limited to 30 days online - call us for longer leases.'; }
    elseif (!$kyc['has']) { $err = 'Driving licence KYC is required before payment. Upload it above.'; }
    elseif (!rentalAvailable($listingId, $pickupAt, $returnAt)) { $err = 'Someone just booked these dates. Please pick another window.'; }
    if ($err !== '') { flash('error', $err); redirect(base('rent-checkout.php?listing=' . $listingId)); }

    $quote = rentalQuote($car, $pickupAt, $returnAt);
    $rentalId = createRental($listingId, (int) $u['id'], $pickupLoc, $returnLoc, $pickupAt, $returnAt, $quote);
    if (razorpayEnabled()) { redirect(base('rent-pay.php?id=' . $rentalId)); }
    confirmRentalPayment($rentalId, 'TXN' . date('ymdHis') . random_int(10, 99));
    logActivity((int) $u['id'], 'rental.created', 'Rental #' . $rentalId);
    redirect(base('rental.php?id=' . $rentalId));
}

$quote = ($pickupAt !== '' && $returnAt !== '' && $returnAt > $pickupAt) ? rentalQuote($car, $pickupAt, $returnAt) : null;
$okDates = $quote !== null && rentalAvailable($listingId, $pickupAt, $returnAt);

renderHeader('Rental checkout', 'rent');
?>
<div class="wrap section">
  <div class="steps">
    <div class="step done">1. Car selected</div>
    <div class="step active">2. KYC &amp; summary</div>
    <div class="step">3. Payment</div>
    <div class="step">4. Pickup &amp; drive</div>
  </div>
  <div class="split-3" style="margin-top:16px">
    <div>
      <form class="card card-pad" method="get">
        <h1 style="font-size:1.35rem">Trip details</h1>
        <input type="hidden" name="listing" value="<?= (int) $car['id'] ?>">
        <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
          <div><label class="form-label">Pickup location</label><input class="form-control" name="pickup_loc" value="<?= e($pickupLoc) ?>" required></div>
          <div><label class="form-label">Pickup date &amp; time</label><input class="form-control" type="datetime-local" name="pickup_at" value="<?= $pickupAt !== '' ? e(date('Y-m-d\TH:i', strtotime($pickupAt))) : '' ?>" required></div>
          <div><label class="form-label">Return date &amp; time</label><input class="form-control" type="datetime-local" name="return_at" value="<?= $returnAt !== '' ? e(date('Y-m-d\TH:i', strtotime($returnAt))) : '' ?>" required></div>
          <div style="display:flex;align-items:flex-end"><button class="btn btn-outline btn-block" type="submit">Update quote</button></div>
        </div>
        <?php if ($pickupAt !== '' && $returnAt !== '' && !$okDates && $quote !== null): ?>
          <div class="alert error" style="margin:12px 0 0">These dates are already booked for this car. Try another window.</div>
        <?php endif; ?>
      </form>

      <div class="card card-pad" style="margin-top:18px">
        <h2 style="font-size:1.15rem">Driving licence KYC <?= $kyc['verified'] ? statusBadge('verified') : ($kyc['has'] ? statusBadge('pending') : statusBadge('new')) ?></h2>
        <?php if ($kyc['verified']): ?>
          <p class="muted" style="margin-bottom:0">Your driving licence is verified - carry the original at pickup.</p>
        <?php elseif ($kyc['has']): ?>
          <div class="alert info">Licence uploaded and pending verification. You may complete this booking - handover needs the original licence.</div>
        <?php else: ?>
          <p class="muted">Rentals need a driving licence on file. Upload it once - admin verifies within 24 hours.</p>
          <form method="post" enctype="multipart/form-data" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
            <?= csrfField() ?><input type="hidden" name="action" value="kyc">
            <input type="hidden" name="listing_id" value="<?= (int) $car['id'] ?>">
            <input type="hidden" name="pickup_loc" value="<?= e($pickupLoc) ?>">
            <input type="hidden" name="pickup_at" value="<?= e($pickupAt) ?>">
            <input type="hidden" name="return_at" value="<?= e($returnAt) ?>">
            <div><label class="form-label">Licence number</label><input class="form-control num" name="id_number" placeholder="GJ01-2020-1234567" required></div>
            <div><label class="form-label">Licence photo/PDF</label><input class="form-control" type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
            <div style="grid-column:1/-1"><button class="btn btn-dark" type="submit">Upload licence</button></div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <aside class="card card-pad sticky">
      <img src="<?= e(listingImage($car)) ?>" alt="" style="width:100%;border-radius:12px;aspect-ratio:16/10;object-fit:cover">
      <h3 style="margin-top:12px;font-size:1.05rem"><?= e(vehicleTitle($car)) ?></h3>
      <?php if ($quote): ?>
        <div class="kv"><span>Pickup</span><span class="num"><?= e(date('d M, h:i A', strtotime($pickupAt))) ?></span></div>
        <div class="kv"><span>Return</span><span class="num"><?= e(date('d M, h:i A', strtotime($returnAt))) ?></span></div>
        <div class="kv"><span>Duration</span><span class="num"><?= $quote['days'] ?> day(s)</span></div>
        <div class="kv"><span>Rental charges</span><span class="num"><?= rupees($quote['base']) ?></span></div>
        <?php if ($quote['discount'] > 0): ?><div class="kv"><span>Weekly discount</span><span class="num">- <?= rupees($quote['discount']) ?></span></div><?php endif; ?>
        <div class="kv"><span>Taxes &amp; fees (<?= rentalTaxPct() ?>%)</span><span class="num"><?= rupees($quote['tax']) ?></span></div>
        <div class="kv"><span>Security deposit</span><span class="num"><?= rupees($quote['deposit']) ?></span></div>
        <div class="kv"><span><b>Payable now</b></span><b class="num"><?= rupees($quote['total']) ?></b></div>
        <form method="post" style="margin-top:12px">
          <?= csrfField() ?><input type="hidden" name="listing_id" value="<?= (int) $car['id'] ?>">
          <input type="hidden" name="pickup_loc" value="<?= e($pickupLoc) ?>">
          <input type="hidden" name="pickup_at" value="<?= e($pickupAt) ?>">
          <input type="hidden" name="return_at" value="<?= e($returnAt) ?>">
          <div><label class="form-label">Return location</label><input class="form-control" name="return_loc" value="<?= e($returnLoc !== '' ? $returnLoc : $pickupLoc) ?>" required></div>
          <?php if ($okDates && $kyc['has']): ?>
            <button class="btn btn-primary btn-lg btn-block" style="margin-top:12px" type="submit">Pay <?= rupees($quote['total']) ?> &amp; book</button>
          <?php elseif (!$kyc['has']): ?>
            <div class="alert warn" style="margin-top:12px">Upload your driving licence to unlock payment.</div>
          <?php else: ?>
            <div class="alert error" style="margin-top:12px">These dates are unavailable.</div>
          <?php endif; ?>
        </form>
        <p class="muted" style="font-size:12.5px;margin-top:10px"><?= razorpayEnabled() ? 'Redirects to <b>Razorpay</b> for secure payment (rental + refundable deposit).' : 'Test mode: no gateway keys, booking confirms instantly.' ?></p>
      <?php else: ?>
        <p class="muted">Choose trip dates to see the price breakup.</p>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
