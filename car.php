<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $u = requireLogin();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'offer') {
        insert('offers', [
            'listing_id' => $id, 'buyer_id' => $u['id'],
            'amount' => (float) ($_POST['amount'] ?? 0),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'status' => 'new',
        ]);
        $car0 = findListing($id);
        if ($car0) { notify((int) $car0['seller_id'], 'New offer', rupees((float) ($_POST['amount'] ?? 0)) . ' on ' . vehicleTitle($car0), 'seller/offers.php'); }
        flash('success', 'Your offer was sent to the seller.');
    } elseif ($action === 'testdrive') {
        insert('test_drives', [
            'listing_id' => $id, 'user_id' => $u['id'],
            'mode' => ($_POST['mode'] ?? 'home') === 'hub' ? 'hub' : 'home',
            'slot_date' => (string) ($_POST['slot_date'] ?? date('Y-m-d')),
            'slot_time' => (string) ($_POST['slot_time'] ?? '11:00 AM'),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'status' => 'requested',
        ]);
        $car0 = findListing($id);
        if ($car0) { notify((int) $car0['seller_id'], 'Test drive requested', vehicleTitle($car0), 'seller/testdrives.php'); }
        flash('success', 'Test drive requested. Our advisor will confirm the slot shortly.');
    } elseif ($action === 'review') {
        $rating = (int) ($_POST['rating'] ?? 0);
        $car0 = findListing($id);
        $bought = $car0 ? (int) fetchValue("SELECT COUNT(*) FROM orders WHERE listing_id = ? AND buyer_id = ? AND status NOT IN ('cancelled','returned')", [$id, $u['id']], 0) : 0;
        if (!$car0 || $rating < 1 || $rating > 5) {
            flash('error', 'Please give a star rating between 1 and 5.');
        } elseif ($bought === 0) {
            flash('error', 'Only verified buyers can review this car.');
        } else {
            insert('reviews', ['listing_id' => $id, 'author_id' => $u['id'],
                'target_user_id' => (int) $car0['seller_id'], 'reviewer_role' => 'buyer', 'rating' => $rating,
                'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160) ?: null,
                'comment' => mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 2000) ?: null, 'status' => 'pending']);
            flash('success', 'Review submitted. It goes live after moderation.');
        }
    } elseif ($action === 'bid') {
        $car0 = findListing($id);
        $amount = (float) ($_POST['amount'] ?? 0);
        $top = $car0 ? (float) fetchValue('SELECT COALESCE(MAX(amount),0) FROM bids WHERE listing_id = ?', [$id], 0) : 0;
        $min = max($top + 1000, (float) ($car0['starting_bid'] ?? 0));
        if (!$car0 || (int) $car0['auction_enabled'] !== 1) {
            flash('error', 'This car is not on auction.');
        } elseif (!empty($car0['auction_ends_at']) && $car0['auction_ends_at'] < date('Y-m-d H:i:s')) {
            flash('error', 'This auction has ended.');
        } elseif ($amount < $min) {
            flash('error', 'Your bid must be at least ' . rupees($min) . '.');
        } else {
            $prev = fetchOne('SELECT buyer_id FROM bids WHERE listing_id = ? ORDER BY amount DESC, id DESC LIMIT 1', [$id]);
            insert('bids', ['listing_id' => $id, 'buyer_id' => $u['id'], 'amount' => $amount]);
            notify((int) $car0['seller_id'], 'New auction bid', rupees($amount) . ' on ' . vehicleTitle($car0), 'car.php?id=' . $id);
            if ($prev && (int) $prev['buyer_id'] !== (int) $u['id']) {
                notify((int) $prev['buyer_id'], 'You were outbid', vehicleTitle($car0) . ' - top bid is now ' . rupees($amount), 'car.php?id=' . $id);
            }
            flash('success', 'Your bid of ' . rupees($amount) . ' is now the highest.');
        }
    } elseif ($action === 'question') {
        $qt = mb_substr(trim((string) ($_POST['question'] ?? '')), 0, 500);
        if ($qt === '') {
            flash('error', 'Please type your question.');
        } else {
            insert('questions', ['listing_id' => $id, 'user_id' => $u['id'], 'question' => $qt]);
            $car0 = findListing($id);
            if ($car0) { notify((int) $car0['seller_id'], 'New question on your car', vehicleTitle($car0), 'seller/questions.php'); }
            flash('success', 'Question posted. The seller usually answers within a day.');
        }
    } elseif ($action === 'report') {
        $reason = mb_substr((string) ($_POST['reason'] ?? 'other'), 0, 60);
        $detail = mb_substr(trim((string) ($_POST['details'] ?? '')), 0, 2000);
        insert('support_tickets', ['user_id' => $u['id'],
            'subject' => 'Listing report #' . $id . ': ' . $reason,
            'category' => 'report', 'priority' => 'high',
            'message' => "Listing: " . base('car.php?id=' . $id) . "\nReason: " . $reason . "\n\n" . $detail,
            'status' => 'open']);
        notifyAdmins('Listing reported', 'Listing #' . $id . ' reported: ' . $reason, 'admin/complaints.php');
        flash('success', 'Thanks - our trust team will review this listing.');
    }
    redirect(base('car.php?id=' . $id));
}

$car = findListing($id);
if ($car === null) {
    renderHeader('Car not found', 'cars');
    echo '<div class="wrap section"><div class="card card-pad empty">This car is no longer available. <a href="' . e(base('cars.php')) . '">Browse all cars</a>.</div></div>';
    renderFooter();
    exit;
}
q('UPDATE listings SET views = views + 1 WHERE id = ?', [$id]);
$inspection = fetchOne('SELECT * FROM inspections WHERE vehicle_id = ? ORDER BY id DESC', [(int) $car['vehicle_id']]);
$history = fetchOne('SELECT * FROM vehicle_history WHERE vehicle_id = ?', [(int) $car['vehicle_id']]);
$similar = fetchAll(LISTING_SELECT . " WHERE l.status = 'approved' AND l.id <> ? AND (v.body_type = ? OR v.make = ?) ORDER BY ABS(l.price - ?) LIMIT 3", [$id, $car['body_type'], $car['make'], (float) $car['price']]);
$gallery = listingGallery($car);
$rating = listingRating($id);
$sellerRate = sellerRating((int) $car['seller_id']);
$reviews = fetchAll("SELECT r.*, u.name AS author FROM reviews r JOIN users u ON u.id = r.author_id
    WHERE r.listing_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 10", [$id]);
$me = user();
$verifiedBuyer = $me ? (int) fetchValue("SELECT COUNT(*) FROM orders WHERE listing_id = ? AND buyer_id = ? AND status NOT IN ('cancelled','returned')", [$id, $me['id']], 0) > 0 : false;
$wish = wishlistIds();
$cmp = compareIds();
$saved = in_array($id, $wish, true);
$emi = emiAmount((float) $car['price'] * 0.8, 9.5, 60);
$shareUrl = (($_SERVER['HTTP_HOST'] ?? '') ? ('https://' . $_SERVER['HTTP_HOST']) : '') . base('car.php?id=' . $id);
$features = fetchAll('SELECT category, feature FROM vehicle_features WHERE vehicle_id = ? ORDER BY category, feature', [(int) $car['vehicle_id']]);
$featByCat = [];
foreach ($features as $f) { $featByCat[$f['category']][] = $f['feature']; }
$listingDocs = fetchAll('SELECT doc_type, doc_name, status FROM documents WHERE listing_id = ? ORDER BY id', [$id]);
$sellerId = (int) $car['seller_id'];
$sellerSold = (int) fetchValue("SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'sold'", [$sellerId], 0);
$offersTotal = (int) fetchValue('SELECT COUNT(*) FROM offers o JOIN listings l ON l.id = o.listing_id WHERE l.seller_id = ?', [$sellerId], 0);
$offersAnswered = (int) fetchValue("SELECT COUNT(*) FROM offers o JOIN listings l ON l.id = o.listing_id WHERE l.seller_id = ? AND o.status <> 'new'", [$sellerId], 0);
$sellerResp = $offersTotal > 0 ? (int) round($offersAnswered * 100 / $offersTotal) : 100;
$sellerCars = fetchAll(LISTING_SELECT . ' WHERE l.status = ? AND l.seller_id = ? AND l.id <> ? ORDER BY l.id DESC LIMIT 3', ['approved', $sellerId, $id]);
$ratingDist = fetchAll("SELECT rating, COUNT(*) AS n FROM reviews WHERE listing_id = ? AND status = 'approved' GROUP BY rating", [$id]);
$distMap = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($ratingDist as $d) { $distMap[(int) $d['rating']] = (int) $d['n']; }
$questions = fetchAll('SELECT q.*, u.name AS asker FROM questions q JOIN users u ON u.id = q.user_id WHERE q.listing_id = ? ORDER BY q.id DESC LIMIT 20', [$id]);
$auction = ((int) $car['auction_enabled'] === 1);
$topBid = $auction ? (float) fetchValue('SELECT COALESCE(MAX(amount),0) FROM bids WHERE listing_id = ?', [$id], 0) : 0;
$bidCount = $auction ? (int) fetchValue('SELECT COUNT(*) FROM bids WHERE listing_id = ?', [$id], 0) : 0;
$auctionLive = $auction && (empty($car['auction_ends_at']) || $car['auction_ends_at'] >= date('Y-m-d H:i:s'));
$minBid = max($topBid + 1000, (float) ($car['starting_bid'] ?? 0));
$myTopBid = ($auction && $me) ? (float) fetchValue('SELECT COALESCE(MAX(amount),0) FROM bids WHERE listing_id = ? AND buyer_id = ?', [$id, $me['id']], 0) : 0;
$insDays = !empty($car['insurance_valid_till']) ? (int) floor((strtotime((string) $car['insurance_valid_till']) - time()) / 86400) : null;
$docFee = 4999;
$tcs = ((float) $car['price'] >= 1000000) ? (float) $car['price'] * 0.01 : 0;
$onRoad = (float) $car['price'] + $docFee + $tcs;

renderHeader(vehicleTitle($car), 'cars');
?>
<div class="wrap section">
  <p class="muted" style="font-size:13px"><a href="<?= e(base('index.php')) ?>">Home</a> / <a href="<?= e(base('cars.php')) ?>">Used cars</a> / <?= e(vehicleTitle($car)) ?></p>
  <div class="split-3">
    <div>
      <?php if (!empty($car['model_3d'])): ?>
      <div class="card model3d-card" style="overflow:hidden;margin-bottom:18px">
        <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
        <model-viewer src="<?= e(base('assets/uploads/' . $car['model_3d'])) ?>" alt="3D model of <?= e(vehicleTitle($car)) ?>" auto-rotate camera-controls shadow-intensity="1" style="width:100%;height:380px;background:#0a1630"></model-viewer>
        <div class="card-pad" style="padding-top:10px"><span class="badge info">Interactive 3D</span> <small class="muted">Drag to spin &middot; scroll to zoom &middot; right-drag to pan</small></div>
      </div>
      <?php endif; ?>
      <div class="card" style="overflow:hidden">
        <div class="gallery">
          <img id="galMain" src="<?= e($gallery[0]['src']) ?>" alt="<?= e(vehicleTitle($car)) ?>" style="width:100%;aspect-ratio:16/10;object-fit:cover">
          <?php if (count($gallery) > 1): ?>
          <div class="thumbs">
            <?php foreach ($gallery as $i => $g): ?>
              <button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-gal="<?= e($g['src']) ?>" title="<?= e($g['label']) ?>"><img src="<?= e($g['src']) ?>" alt="<?= e($g['label']) ?>"></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="card-pad">
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <h1 style="font-size:1.55rem;margin:0;flex:1;min-width:220px"><?= e(vehicleTitle($car)) ?></h1>
            <?php if ($rating['count'] > 0): ?><span class="badge ok">★ <?= $rating['avg'] ?> (<?= $rating['count'] ?>)</span><?php endif; ?>
          </div>
          <div class="meta">
            <span><?= number_format((int) $car['km_driven']) ?> km</span><span><?= e((string) $car['fuel_type']) ?></span>
            <span><?= e((string) $car['transmission']) ?></span><span><?= (int) $car['owners'] ?> owner</span>
            <span><?= e(trim(((string) ($car['area'] ?? '') !== '' ? (string) $car['area'] . ', ' : '') . (string) $car['city'])) ?></span><span><?= (int) $car['views'] ?> views</span>
          </div>
          <p class="muted" style="margin-top:12px"><?= e((string) $car['description']) ?></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <a class="btn btn-outline btn-sm" href="<?= e(base('history-report.php?listing=' . $id)) ?>">History report<?= $history ? ' (' . (int) $history['accidents'] . ' accidents)' : '' ?></a>
            <?php if ($inspection): ?><a class="btn btn-outline btn-sm" href="<?= e(base('inspection-report.php?listing=' . $id)) ?>" target="_blank">Inspection PDF</a><?php endif; ?>
            <a class="btn btn-outline btn-sm" href="<?= e(base('inspection.php?listing=' . $id)) ?>">Book own inspection</a>
            <span style="margin-left:auto;display:flex;gap:6px">
              <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= urlencode(vehicleTitle($car) . ' ' . $shareUrl) ?>">WhatsApp</a>
              <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= urlencode(vehicleTitle($car) . ' ' . $shareUrl) ?>">X</a>
              <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($shareUrl) ?>">Copy link</button>
            </span>
          </div>
        </div>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="overview">
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <a class="btn btn-ghost btn-sm" href="#overview">Overview</a>
          <a class="btn btn-ghost btn-sm" href="#specs">Specifications</a>
          <a class="btn btn-ghost btn-sm" href="#features">Features</a>
          <a class="btn btn-ghost btn-sm" href="#condition">Condition</a>
          <a class="btn btn-ghost btn-sm" href="#history">History</a>
          <a class="btn btn-ghost btn-sm" href="#documents">Documents</a>
          <a class="btn btn-ghost btn-sm" href="#location">Location</a>
          <a class="btn btn-ghost btn-sm" href="#reviews">Reviews</a>
          <a class="btn btn-ghost btn-sm" href="#qa">Q&amp;A</a>
        </div>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="price">
        <h2 style="font-size:1.2rem">Price breakup</h2>
        <div class="kv"><span>Car price</span><b class="num"><?= rupees($car['price']) ?></b></div>
        <div class="kv"><span>RC transfer + documentation</span><span class="num"><?= rupees($docFee) ?></span></div>
        <div class="kv"><span>TCS (1% on cars above &#8377;10 lakh)</span><span class="num"><?= $tcs > 0 ? rupees($tcs) : 'Not applicable' ?></span></div>
        <div class="kv"><span><b>Estimated on-road price</b></span><b class="num"><?= rupees($onRoad) ?></b></div>
        <p class="muted" style="font-size:13px">Includes DRIVE24 buyer protection and doorstep delivery in the same city. Insurance renewal, if due, is extra at actuals.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-outline btn-sm" href="<?= e(base('exchange.php?listing=' . $id)) ?>">Exchange old car, get bonus up to &#8377;20,000</a>
          <a class="btn btn-outline btn-sm" href="<?= e(base('finance.php?listing=' . $id)) ?>">Compare loan offers</a>
        </div>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="specs">
        <h2 style="font-size:1.2rem">Specifications</h2>
        <div class="spec-grid">
          <div><small>Registration</small><b class="num"><?= e((string) $car['reg_number']) ?></b></div>
          <div><small>Reg. state</small><b><?= e((string) $car['reg_state']) ?></b></div>
          <div><small>Engine</small><b class="num"><?= (int) $car['engine_cc'] ?> cc</b></div>
          <div><small>Power</small><b><?= e((string) $car['power_bhp']) ?></b></div>
          <div><small>Mileage</small><b class="num"><?= e((string) $car['mileage_kmpl']) ?> kmpl</b></div>
          <div><small>Seats</small><b class="num"><?= (int) $car['seats'] ?></b></div>
          <div><small>Colour</small><b><?= e((string) $car['color']) ?></b></div>
          <div><small>Insurance till</small><b class="num"><?= e($car['insurance_valid_till'] ? date('M Y', strtotime((string) $car['insurance_valid_till'])) : '-') ?></b></div>
          <div><small>VIN</small><b class="num"><?= e((string) $car['vin']) ?></b></div>
        </div>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="features">
        <h2 style="font-size:1.2rem">Features (<?= count($features) ?>)</h2>
        <?php if (!$features): ?><p class="muted">The feature list is being verified for this car.</p><?php endif; ?>
        <?php foreach (['comfort' => 'Comfort &amp; convenience', 'safety' => 'Safety', 'entertainment' => 'Entertainment &amp; tech', 'exterior' => 'Exterior'] as $cat => $catLabel): ?>
          <?php if (!empty($featByCat[$cat])): ?>
            <h3 style="font-size:.95rem;margin:12px 0 6px"><?= $catLabel ?></h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php foreach ($featByCat[$cat] as $ft): ?><span class="badge ok">&#10003; <?= e($ft) ?></span><?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="ownership">
        <h2 style="font-size:1.2rem">Service, insurance &amp; warranty</h2>
        <div class="kv"><span>Service history</span><span><?= $history ? (int) $history['service_records'] . ' authorised-service records' : 'See full history report' ?><?= ($history && (int) $history['odometer_verified'] === 1) ? ' &middot; ' . statusBadge('verified') . ' odometer' : '' ?></span></div>
        <div class="kv"><span>Insurance</span><span class="num"><?= $insDays === null ? 'Not specified' : ($insDays >= 0 ? 'Valid till ' . e(date('M Y', strtotime((string) $car['insurance_valid_till']))) . ' (' . $insDays . ' days left)' : 'Expired - renew at checkout') ?></span></div>
        <div class="kv"><span>Warranty</span><span><?= ((int) $car['certified'] === 1) ? '12-month DRIVE24 warranty included' : 'Extended warranty available at checkout' ?></span></div>
      </div>

      <?php if ($inspection): ?>
      <div class="card card-pad" style="margin-top:18px" id="condition">
        <h2 style="font-size:1.2rem">280-point inspection report <a class="btn btn-ghost btn-sm" style="float:right" target="_blank" href="<?= e(base('inspection-report.php?listing=' . $id)) ?>">Download</a></h2>
        <p class="muted">Inspected by <?= e((string) $inspection['inspector']) ?> on <?= e(date('d M Y', strtotime((string) $inspection['inspected_on']))) ?> &middot; <?= statusBadge((string) $inspection['status']) ?></p>
        <div class="spec-grid">
          <?php foreach (['engine_score' => 'Engine &amp; transmission', 'exterior_score' => 'Exterior &amp; body', 'interior_score' => 'Interior', 'electrical_score' => 'Electricals', 'tyres_score' => 'Tyres &amp; brakes'] as $key => $label): ?>
            <div><small><?= $label ?></small><b class="num"><?= (int) $inspection[$key] ?>/100</b><br><small style="color:#b45309"><?= stars((int) $inspection[$key]) ?></small> <small class="muted"><?= conditionLabel((int) $inspection[$key]) ?></small></div>
          <?php endforeach; ?>
          <div><small>Overall score</small><b class="num"><?= (int) $inspection['score'] ?>/100</b><br><small style="color:#b45309"><?= stars((int) $inspection['score']) ?></small> <small class="muted"><?= conditionLabel((int) $inspection['score']) ?></small></div>
        </div>
        <div class="kv" style="margin-top:10px"><span>Accident history</span><span><?= e((string) $inspection['accident_history']) ?></span></div>
        <div class="kv"><span>Engineer remarks</span><span><?= e((string) $inspection['remarks']) ?></span></div>
      </div>
      <?php else: ?>
      <div class="card card-pad" style="margin-top:18px" id="condition">
        <h2 style="font-size:1.2rem">Condition report</h2>
        <p class="muted">The 280-point inspection is scheduled for this car - the full scorecard appears here once the engineer submits it.</p>
        <a class="btn btn-outline btn-sm" href="<?= e(base('inspection.php?listing=' . $id)) ?>">Book your own inspection</a>
      </div>
      <?php endif; ?>

      <div class="card card-pad" style="margin-top:18px" id="history">
        <h2 style="font-size:1.2rem">History summary</h2>
        <?php if ($history): ?>
          <div class="kv"><span>Accidents</span><span><?= (int) $history['accidents'] ?> &middot; <?= e((string) ($history['accident_details'] ?? '')) ?></span></div>
          <div class="kv"><span>Insurance claims</span><span class="num"><?= (int) $history['insurance_claims'] ?></span></div>
          <div class="kv"><span>Traffic challans</span><span class="num"><?= (int) $history['challans'] ?> (<?= rupees($history['challan_amount']) ?> settled)</span></div>
          <div class="kv"><span>Flood / theft record</span><span><?= ((int) $history['flood_damage'] === 1 || (int) $history['theft_record'] === 1) ? statusBadge('flagged') : statusBadge('verified') . ' clean' ?></span></div>
          <div class="kv"><span>Hypothecation / loan</span><span><?= e((string) ($history['loan_status'] ?? 'No active loan')) ?></span></div>
          <div class="kv"><span>RC verified</span><span><?= (int) ($history['rc_verified'] ?? 0) === 1 ? statusBadge('verified') : statusBadge('pending') ?></span></div>
          <div class="kv"><span>Odometer verified</span><span><?= (int) ($history['odometer_verified'] ?? 0) === 1 ? statusBadge('verified') : statusBadge('pending') ?></span></div>
          <p class="muted"><?= e((string) ($history['report_summary'] ?? '')) ?></p>
        <?php else: ?>
          <p class="muted">The history check is in progress for this car.</p>
        <?php endif; ?>
        <a class="btn btn-outline btn-sm" href="<?= e(base('history-report.php?listing=' . $id)) ?>">Open full history report</a>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="documents">
        <h2 style="font-size:1.2rem">Documents available</h2>
        <?php if (!$listingDocs): ?><p class="muted">The seller is uploading the RC, insurance and service documents. Verified copies are shared before you pay anything.</p><?php endif; ?>
        <?php foreach ($listingDocs as $dc): ?>
          <div class="kv"><span><?= e($dc['doc_name']) ?> <small class="muted">(<?= e($dc['doc_type']) ?>)</small></span><span><?= statusBadge((string) $dc['status']) ?></span></div>
        <?php endforeach; ?>
        <p class="muted" style="font-size:13px">RC transfer papers (Form 29/30), the tax invoice and the delivery note are issued by DRIVE24 on every order.</p>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="location">
        <h2 style="font-size:1.2rem">Car location</h2>
        <div class="kv"><span>Area</span><span><?= e((string) ($car['area'] ?? '-') ?: '-') ?></span></div>
        <div class="kv"><span>City</span><span><?= e((string) $car['city']) ?></span></div>
        <div class="kv"><span>Registered in</span><span><?= e((string) $car['reg_state']) ?> (<?= e((string) $car['reg_number']) ?>)</span></div>
        <p class="muted" style="font-size:13px">The exact parking address and the seller contact are shared after booking. Home test drives are available across the city.</p>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="reviews">
        <h2 style="font-size:1.2rem">Buyer reviews <?= $rating['count'] ? '(★ ' . $rating['avg'] . ' average)' : '' ?></h2>
        <?php if ($rating['count'] > 0): ?>
          <div style="max-width:340px;margin:8px 0 4px">
            <?php foreach ([5, 4, 3, 2, 1] as $st): $pct = $rating['count'] ? (int) round($distMap[$st] * 100 / $rating['count']) : 0; ?>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;margin-top:4px"><span class="num" style="width:26px"><?= $st ?>★</span><div style="flex:1;background:var(--line);border-radius:6px;height:8px"><div style="width:<?= $pct ?>%;background:#f59e0b;height:8px;border-radius:6px"></div></div><span class="num muted"><?= $distMap[$st] ?></span></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!$reviews): ?><p class="muted">No reviews yet - be the first verified buyer to rate this car.</p><?php endif; ?>
        <?php foreach ($reviews as $rv): ?>
          <div style="border-bottom:1px solid var(--line);padding:10px 0">
            <div><b><?= e($rv['author']) ?></b> <span style="color:#b45309"><?= str_repeat('★', (int) $rv['rating']) . str_repeat('☆', 5 - (int) $rv['rating']) ?></span>
              <small class="muted num"><?= e(date('d M Y', strtotime((string) $rv['created_at']))) ?></small></div>
            <?php if ($rv['title']): ?><b><?= e($rv['title']) ?></b><?php endif; ?>
            <div class="muted"><?= e((string) ($rv['comment'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if ($verifiedBuyer): ?>
          <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:10px;margin-top:12px">
            <?= csrfField() ?><input type="hidden" name="action" value="review">
            <div><label class="form-label">Your rating</label><select class="form-select" name="rating"><option value="5">★★★★★</option><option value="4">★★★★</option><option value="3">★★★</option><option value="2">★★</option><option value="1">★</option></select></div>
            <div><label class="form-label">Headline</label><input class="form-control" name="title" placeholder="Great car!"></div>
            <div style="grid-column:1/-1"><label class="form-label">Review</label><textarea class="form-control" name="comment" rows="3"></textarea></div>
            <div style="grid-column:1/-1"><button class="btn btn-dark btn-sm" type="submit">Submit review</button></div>
          </form>
        <?php endif; ?>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="qa">
        <h2 style="font-size:1.2rem">Questions &amp; answers (<?= count($questions) ?>)</h2>
        <?php if (!$questions): ?><p class="muted">No questions yet - ask the seller anything about this car.</p><?php endif; ?>
        <?php foreach ($questions as $qa): ?>
          <div style="border-bottom:1px solid var(--line);padding:10px 0">
            <div><b>Q:</b> <?= e($qa['question']) ?> <small class="muted">- <?= e($qa['asker']) ?></small></div>
            <?php if (!empty($qa['answer'])): ?>
              <div style="margin-top:4px"><b>A:</b> <?= e($qa['answer']) ?> <small class="muted">(seller)</small></div>
            <?php else: ?>
              <div style="margin-top:4px"><small class="muted">Waiting for the seller's answer.</small></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <form method="post" class="grid" style="gap:10px;margin-top:12px">
          <?= csrfField() ?><input type="hidden" name="action" value="question">
          <div><label class="form-label">Ask the seller</label><input class="form-control" name="question" maxlength="500" placeholder="e.g. Is the second key available?" required></div>
          <div><button class="btn btn-dark btn-sm" type="submit">Post question</button></div>
        </form>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="testdrive">
        <h2 style="font-size:1.2rem">Book a test drive</h2>
        <form method="post" class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
          <?= csrfField() ?><input type="hidden" name="action" value="testdrive">
          <div><label class="form-label">Mode</label><select class="form-select" name="mode"><option value="home">At my home</option><option value="hub">At DRIVE24 hub</option></select></div>
          <div><label class="form-label">Date</label><input class="form-control" type="date" name="slot_date" value="<?= e(date('Y-m-d', strtotime('+2 days'))) ?>" required></div>
          <div><label class="form-label">Time slot</label><select class="form-select" name="slot_time"><option>10:00 AM</option><option>11:00 AM</option><option>02:00 PM</option><option>04:30 PM</option><option>06:00 PM</option></select></div>
          <div><label class="form-label">Address / landmark</label><input class="form-control" name="address" placeholder="Where should we bring the car?"></div>
          <div style="grid-column:1/-1"><button class="btn btn-dark" type="submit">Request test drive</button></div>
        </form>
      </div>

      <?php if ($similar): ?>
        <h2 style="margin-top:26px;font-size:1.2rem">Similar cars</h2>
        <div class="grid cars"><?php foreach ($similar as $s) { carCard($s, $wish, $cmp); } ?></div>
      <?php endif; ?>
    </div>

    <aside>
      <?php if ($auction): ?>
      <div class="card card-pad" style="margin-bottom:18px;border:2px solid #f59e0b">
        <h3 style="font-size:1.05rem">&#128296; Live auction</h3>
        <?php if ($auctionLive): ?>
          <div class="kv"><span>Top bid</span><b class="num"><?= $topBid > 0 ? rupees($topBid) : 'No bids yet' ?></b></div>
          <div class="kv"><span>Bids</span><span class="num"><?= $bidCount ?></span></div>
          <?php if (!empty($car['auction_ends_at'])): ?><div class="kv"><span>Ends at</span><span class="num"><?= e(date('d M, h:i A', strtotime((string) $car['auction_ends_at']))) ?></span></div><?php endif; ?>
          <?php if ($myTopBid > 0): ?><div class="kv"><span>Your best</span><span class="num"><?= rupees($myTopBid) ?></span></div><?php endif; ?>
          <form method="post" style="margin-top:10px">
            <?= csrfField() ?><input type="hidden" name="action" value="bid">
            <label class="form-label">Your bid (min <?= rupees($minBid) ?>)</label>
            <input class="form-control num" type="number" name="amount" min="<?= (int) $minBid ?>" step="1000" value="<?= (int) $minBid ?>" required>
            <button class="btn btn-primary btn-block" style="margin-top:10px" type="submit">Place bid</button>
          </form>
        <?php else: ?>
          <p class="muted">This auction ended<?= !empty($car['auction_ends_at']) ? ' on ' . e(date('d M Y', strtotime((string) $car['auction_ends_at']))) : '' ?>.<?= $topBid > 0 ? ' Winning bid: <b class="num">' . rupees($topBid) . '</b>' : '' ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="card card-pad sticky">
        <div class="price num" style="font-size:1.7rem;font-weight:800"><?= rupees($car['price']) ?></div>
        <?php if ($car['original_price'] && (float) $car['original_price'] > (float) $car['price']): ?>
          <div class="muted num" style="font-size:13px"><s><?= rupees($car['original_price']) ?></s> &middot; you save <?= rupees((float) $car['original_price'] - (float) $car['price']) ?></div>
        <?php endif; ?>
        <div class="muted num" style="font-size:13.5px;margin-top:4px">EMI from <b><?= rupees($emi) ?></b>/month</div>
        <div style="display:flex;gap:8px;margin-top:14px">
          <?php $heldOut = !empty($car['hold_until']) && $car['hold_until'] > date('Y-m-d H:i:s') && (!$me || (int) ($car['hold_buyer_id'] ?? 0) !== (int) $me['id']); ?>
          <?php if ($heldOut): ?>
            <span class="btn btn-outline" style="flex:1;opacity:.6;pointer-events:none">Reserved for another buyer</span>
          <?php else: ?>
            <a class="btn btn-primary" style="flex:1" href="<?= e(base('checkout.php?listing=' . $id)) ?>">Buy now</a>
          <?php endif; ?>
          <button class="btn btn-outline fav <?= $saved ? 'on' : '' ?>" style="position:static;width:auto;border-radius:10px" type="button" data-wishlist="<?= $id ?>">&#10084; Save</button>
        </div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <a class="btn btn-outline btn-sm" style="flex:1" href="<?= e(base('chat.php?listing=' . $id)) ?>">Chat with seller</a>
          <button class="btn btn-outline btn-sm" style="flex:1" type="button" data-compare="<?= $id ?>">Compare</button>
        </div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <a class="btn btn-ghost btn-sm" style="flex:1" href="<?= e(base('loan-apply.php?listing=' . $id)) ?>">Apply loan</a>
          <a class="btn btn-ghost btn-sm" style="flex:1" href="<?= e(base('insurance.php?listing=' . $id)) ?>">Insurance</a>
        </div>
        <div class="kv" style="margin-top:12px"><span>Seller</span><span><?= e((string) ($car['seller_company'] ?: $car['seller_name'])) ?></span></div>
        <div class="kv"><span>Seller rating</span><span class="num"><?= $sellerRate['count'] ? '★ ' . $sellerRate['avg'] . ' (' . $sellerRate['count'] . ')' : 'New seller' ?></span></div>
        <div class="kv"><span>Inspection score</span><span class="num"><?= (int) $car['inspection_score'] ?>/100</span></div>
        <div class="kv"><span>Certified</span><span><?= ((int) $car['certified'] === 1) ? statusBadge('verified') : statusBadge('pending') ?></span></div>
        <div class="kv"><span>Member since</span><span class="num"><?= e(date('M Y', strtotime((string) ($car['seller_since'] ?? 'now')))) ?></span></div>
        <div class="kv"><span>KYC</span><span><?= statusBadge((string) ($car['seller_kyc'] ?? 'pending')) ?></span></div>
        <div class="kv"><span>Cars sold</span><span class="num"><?= $sellerSold ?></span></div>
        <div class="kv"><span>Responds to</span><span class="num"><?= $sellerResp ?>% of offers</span></div>
        <div style="margin-top:10px"><a class="btn btn-ghost btn-sm" href="<?= e(base('cars.php?seller=' . $sellerId)) ?>">All cars by this seller</a></div>
        <details style="margin-top:10px">
          <summary class="muted" style="cursor:pointer;font-size:13px">Report this listing</summary>
          <form method="post" style="margin-top:8px">
            <?= csrfField() ?><input type="hidden" name="action" value="report">
            <label class="form-label">Reason</label>
            <select class="form-select" name="reason"><option value="wrong-details">Wrong details or photos</option><option value="price">Suspicious price / advance demand</option><option value="sold">Already sold elsewhere</option><option value="fraud">Fraud / scam</option><option value="other">Other</option></select>
            <label class="form-label" style="margin-top:8px">Details</label>
            <textarea class="form-control" name="details" rows="2"></textarea>
            <button class="btn btn-outline btn-sm" style="margin-top:8px" type="submit">Submit report</button>
          </form>
        </details>
      </div>

      <div class="card card-pad" style="margin-top:18px">
        <h3 style="font-size:1.05rem">Exchange your old car</h3>
        <p class="muted" style="font-size:13px">Extra exchange bonus up to &#8377;20,000 on this car.</p>
        <a class="btn btn-outline btn-block btn-sm" href="<?= e(base('exchange.php?listing=' . $id)) ?>">Get exchange quote</a>
      </div>

      <div class="card card-pad" style="margin-top:18px" data-emi-price="<?= (int) $car['price'] ?>">
        <h3 style="font-size:1.05rem">EMI calculator</h3>
        <label class="form-label">Down payment</label>
        <input class="form-control num" type="number" data-emi-down value="<?= (int) ((float) $car['price'] * 0.2) ?>">
        <label class="form-label" style="margin-top:8px">Interest rate (%)</label>
        <input class="form-control num" type="number" step="0.1" data-emi-rate value="9.5">
        <label class="form-label" style="margin-top:8px">Tenure (months)</label>
        <select class="form-select" data-emi-tenure><option>36</option><option>48</option><option selected>60</option><option>72</option><option>84</option></select>
        <div class="kv" style="margin-top:10px"><span>Monthly EMI</span><b class="num" data-emi-out>-</b></div>
        <div class="kv"><span>Loan amount</span><span class="num" data-emi-principal>-</span></div>
        <div class="kv"><span>Total interest</span><span class="num" data-emi-interest>-</span></div>
        <a class="btn btn-outline btn-block btn-sm" style="margin-top:10px" href="<?= e(base('finance.php?listing=' . $id)) ?>">Compare lender offers</a>
        <a class="btn btn-primary btn-block btn-sm" style="margin-top:8px" href="<?= e(base('loan-apply.php?listing=' . $id)) ?>">Apply for this loan</a>
      </div>

      <div class="card card-pad" style="margin-top:18px">
        <h3 style="font-size:1.05rem">Make an offer</h3>
        <form method="post">
          <?= csrfField() ?><input type="hidden" name="action" value="offer">
          <label class="form-label">Your offer</label>
          <input class="form-control num" type="number" name="amount" value="<?= (int) ((float) $car['price'] * 0.95) ?>" required>
          <label class="form-label" style="margin-top:8px">Message</label>
          <textarea class="form-control" name="message" rows="3" placeholder="Tell the seller why"></textarea>
          <button class="btn btn-dark btn-block" style="margin-top:10px" type="submit">Send offer</button>
        </form>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
