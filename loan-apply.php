<?php
// Loan application flow (SRS: financing integration with partner lenders).
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$car = findListing((int) ($_GET['listing'] ?? $_POST['listing_id'] ?? 0));
if ($car === null) { flash('error', 'Choose a car to finance.'); redirect(base('cars.php')); }

$lenders = [
    'HDFC Bank' => 8.95, 'ICICI Bank' => 9.25, 'Kotak Mahindra' => 9.60,
    'Axis Bank' => 9.75, 'Bajaj Finserv' => 10.25, 'Tata Capital' => 10.60,
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $lender = (string) ($_POST['lender'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $tenure = (int) ($_POST['tenure'] ?? 60);
    if (!isset($lenders[$lender]) || $amount <= 0 || $amount > (float) $car['price']) {
        flash('error', 'Please choose a lender and a loan amount up to the car price.');
        redirect(base('loan-apply.php?listing=' . (int) $car['id']));
    }
    $id = insert('loan_applications', [
        'user_id' => $u['id'], 'listing_id' => (int) $car['id'], 'lender' => $lender,
        'amount' => $amount, 'tenure_months' => $tenure, 'rate' => $lenders[$lender],
        'employment' => (string) ($_POST['employment'] ?? ''), 'monthly_income' => (float) ($_POST['income'] ?? 0),
        'status' => 'new',
    ]);
    logActivity((int) $u['id'], 'loan.applied', $lender . ' ' . refCode('LN', $id));
    flash('success', 'Application ' . refCode('LN', $id) . ' sent to ' . $lender . '. Track it in My account.');
    redirect(base('account.php'));
}

$defaultLoan = (float) $car['price'] * 0.8;
renderHeader('Apply for a car loan', 'finance');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">Finance this car</h1>
  <p class="muted"><?= e(vehicleTitle($car)) ?> &middot; <b class="num"><?= rupees($car['price']) ?></b> &middot; pre-qualification is a soft check and never affects your credit score.</p>
  <div class="split-3">
    <form class="card card-pad" method="post">
      <?= csrfField() ?><input type="hidden" name="listing_id" value="<?= (int) $car['id'] ?>">
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <div><label class="form-label">Lender</label><select class="form-select" name="lender">
          <?php foreach ($lenders as $name => $rate): ?><option value="<?= e($name) ?>"><?= e($name) ?> - <?= e((string) $rate) ?>%</option><?php endforeach; ?>
        </select></div>
        <div><label class="form-label">Employment</label><select class="form-select" name="employment"><option>Salaried</option><option>Self-employed</option><option>Business owner</option></select></div>
        <div><label class="form-label">Loan amount</label><input class="form-control num" type="number" name="amount" value="<?= (int) $defaultLoan ?>" max="<?= (int) $car['price'] ?>" required></div>
        <div><label class="form-label">Tenure</label><select class="form-select" name="tenure"><option>36</option><option>48</option><option selected>60</option><option>72</option><option>84</option></select></div>
        <div style="grid-column:1/-1"><label class="form-label">Monthly income</label><input class="form-control num" type="number" name="income" placeholder="e.g. 85000" required></div>
        <div style="grid-column:1/-1"><button class="btn btn-primary btn-lg" type="submit">Submit application</button></div>
      </div>
    </form>
    <aside class="card card-pad sticky" data-emi-price="<?= (int) $car['price'] ?>">
      <h3 style="font-size:1.05rem">What you would pay</h3>
      <label class="form-label">Down payment</label>
      <input class="form-control num" type="number" data-emi-down value="<?= (int) ((float) $car['price'] - $defaultLoan) ?>">
      <label class="form-label" style="margin-top:8px">Tenure</label>
      <select class="form-select" data-emi-tenure><option>36</option><option>48</option><option selected>60</option><option>72</option></select>
      <input type="hidden" data-emi-rate value="8.95">
      <div class="kv" style="margin-top:10px"><span>EMI at 8.95%</span><b class="num" data-emi-out>-</b></div>
      <div class="kv"><span>Total interest</span><span class="num" data-emi-interest>-</span></div>
      <p class="muted" style="font-size:12.5px;margin-top:8px">Approval in ~24 hours. Sanction letter lands in your document vault.</p>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
