<?php
// Insurance quote tool (SRS: insurer integration with pre-filled vehicle data).
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = user();
$car = findListing((int) ($_GET['listing'] ?? $_POST['listing_id'] ?? 0));
if ($car === null) {
    $cars = dbReady() ? fetchAll(LISTING_SELECT . " WHERE l.status = 'approved' ORDER BY l.created_at DESC LIMIT 12") : [];
    renderHeader('Car insurance', '');
    ?>
    <div class="wrap section"><h1 style="font-size:1.6rem">Car insurance</h1>
    <p class="muted">Pick a car to get an instant comprehensive premium with vehicle details pre-filled.</p>
    <div class="grid cars"><?php foreach ($cars as $c): ?>
      <article class="car-card"><div class="body"><h3><?= e(vehicleTitle($c)) ?></h3>
      <div class="price num"><?= rupees($c['price']) ?></div>
      <a class="btn btn-primary btn-sm btn-block" style="margin-top:8px" href="<?= e(base('insurance.php?listing=' . (int) $c['id'])) ?>">Get quote</a></div></article>
    <?php endforeach; ?></div></div><?php
    renderFooter();
    exit;
}

$age = max(0, (int) date('Y') - (int) $car['year']);
$idv = (float) $car['price'] * max(0.5, 0.95 - $age * 0.07);
$insurers = [
    ['ICICI Lombard', 1.00, 'Zero-dep + RSA included'],
    ['TATA AIG', 0.96, 'Consumables cover free'],
    ['HDFC ERGO', 1.04, 'Engine protect included'],
    ['Go Digit', 0.92, 'DIY claims in 3 steps'],
];
$quotes = [];
foreach ($insurers as [$name, $mult, $note]) {
    $own = $idv * 0.032 * $mult;
    $tp = 8416.0;
    $premium = round(($own + $tp) * 1.18);
    $quotes[] = ['name' => $name, 'premium' => $premium, 'note' => $note];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $me = requireLogin();
    $pick = (string) ($_POST['insurer'] ?? '');
    foreach ($quotes as $qt) {
        if ($qt['name'] === $pick) {
            $qid = insert('insurance_quotes', ['user_id' => $me['id'], 'listing_id' => (int) $car['id'],
                'insurer' => $pick, 'idv' => round($idv), 'premium' => $qt['premium'],
                'addons' => 'Zero-dep, RSA', 'status' => 'purchased']);
            insert('documents', ['user_id' => $me['id'], 'doc_type' => 'insurance',
                'doc_name' => 'Motor policy - ' . $pick . ' (' . refCode('IN', $qid) . ')', 'status' => 'verified']);
            logActivity((int) $me['id'], 'insurance.purchased', $pick . ' ' . refCode('IN', $qid));
            flash('success', 'Policy purchased with ' . $pick . '. It is saved in your document vault.');
            redirect(base('services.php'));
        }
    }
}

renderHeader('Insurance for ' . vehicleTitle($car), '');
?>
<div class="wrap section">
  <p class="muted" style="font-size:13px"><a href="<?= e(base('car.php?id=' . (int) $car['id'])) ?>">&larr; Back to car</a></p>
  <h1 style="font-size:1.6rem">Insurance quotes</h1>
  <p class="muted"><?= e(vehicleTitle($car)) ?> &middot; <?= (int) $car['year'] ?> &middot; IDV <b class="num"><?= rupees($idv) ?></b> (age-adjusted from listing price).</p>
  <div class="grid four" style="margin-top:16px">
    <?php foreach ($quotes as $qt): ?>
      <form class="card card-pad" method="post">
        <?= csrfField() ?><input type="hidden" name="listing_id" value="<?= (int) $car['id'] ?>"><input type="hidden" name="insurer" value="<?= e($qt['name']) ?>">
        <h3 style="font-size:1.05rem"><?= e($qt['name']) ?></h3>
        <div class="num" style="font-size:1.6rem;font-weight:800"><?= rupees($qt['premium']) ?></div>
        <div class="muted" style="font-size:13px">comprehensive / year, incl. GST</div>
        <div class="muted" style="font-size:13px;margin:6px 0"><?= e($qt['note']) ?></div>
        <button class="btn btn-primary btn-block btn-sm" type="submit">Buy this policy</button>
      </form>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="font-size:12.5px;margin-top:10px">Indicative partner pricing for demo. IDV = insured declared value.</p>
</div>
<?php renderFooter(); ?>
