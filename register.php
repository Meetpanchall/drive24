<?php
require_once __DIR__ . '/includes/layout.php';

if (user() !== null) { redirect(base('account.php')); }
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $role = in_array($_POST['role'] ?? 'buyer', ['buyer', 'seller', 'dealer'], true) ? (string) $_POST['role'] : 'buyer';
    $password = (string) ($_POST['password'] ?? '');

    if (!dbReady()) {
        $error = 'MySQL is not connected yet. Import database/schema.sql first.';
    } elseif ($name === '') {
        $error = 'Please enter your full name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid e-mail address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (fetchOne('SELECT id FROM users WHERE email = ?', [$email])) {
        $error = 'An account with this email already exists.';
    } else {
        $id = insert('users', [
            'name' => $name, 'email' => $email, 'mobile' => $mobile, 'city' => $city, 'role' => $role,
            'company' => trim((string) ($_POST['company'] ?? '')) ?: null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $idProof = trim((string) ($_POST['id_proof'] ?? ''));
        if ($idProof !== '') {
            insert('documents', ['user_id' => $id, 'doc_type' => 'kyc',
                'doc_name' => ($role === 'dealer' ? 'Dealer licence - ' : 'PAN - ') . $idProof, 'status' => 'pending']);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        logActivity($id, 'auth.register', $email);
        flash('success', 'Welcome to DRIVE24, your account is ready.');
        redirect($role === 'buyer' ? base('account.php') : base('seller/dashboard.php'));
    }
}

$cities = ['Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Ahmedabad', 'Chennai', 'Pune', 'Jaipur', 'Surat', 'Noida', 'Gurugram', 'Kolkata'];
$postedCity = trim((string) ($_POST['city'] ?? ''));
if ($postedCity !== '' && !in_array($postedCity, $cities, true)) { $cities[] = $postedCity; }

renderHeader('Create account', '');
?>
<div class="wrap">
  <div class="auth-wrap reg-wrap">
    <div class="auth-side reg-side">
      <a href="<?= e(base('index.php')) ?>" aria-label="DRIVE24 home"><img src="<?= e(base('assets/img/logo-brand-white.svg')) ?>" alt="Drive24 - Rent, Drive, Explore" height="52"></a>
      <h2>Join India's Trusted<br><span class="hl">Used-Car Marketplace</span></h2>
      <p class="reg-sub">Buy, sell and trade cars with confidence.<br>Verified cars <span class="dot">&bull;</span> Transparent deals <span class="dot">&bull;</span> Hassle-free process</p>
      <ul class="reg-feats">
        <li><span class="reg-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 3v6c0 5-3.2 8.6-7 11-3.8-2.4-7-6-7-11V5z"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><b>Verified Listings</b><small>Every car is inspected and verified</small></div></li>
        <li><span class="reg-ic"><span style="font-size:19px;font-weight:800">&#8377;</span></span><div><b>Best Prices</b><small>Get the best value for your car</small></div></li>
        <li><span class="reg-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12l3 3 5-6"/><path d="M12 21c-4 0-7-1.5-9-4l2.5-2.5c1.5 1.5 4 2.5 6.5 2.5s5-1 6.5-2.5L21 17c-2 2.5-5 4-9 4z"/><path d="M3 13l2-2M21 13l-2-2"/></svg></span><div><b>Secure Transactions</b><small>Safe and hassle-free payment process</small></div></li>
        <li><span class="reg-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13a8 8 0 0 1 16 0"/><rect x="3" y="13" width="4" height="6" rx="2"/><rect x="17" y="13" width="4" height="6" rx="2"/><path d="M20 19a4 4 0 0 1-4 3h-2"/></svg></span><div><b>Dedicated Support</b><small>We're here to help, always</small></div></li>
      </ul>
      <div class="reg-cars">
        <img src="<?= e(base('assets/img/car9.jpg')) ?>" alt="Certified SUVs">
        <span class="script">Drive Your Dreams</span>
      </div>
    </div>
    <div class="card-pad reg-form">
      <div class="reg-top">
        <h1>Create your <span class="blue">DRIVE24</span> account</h1>
        <a class="signin-link" href="<?= e(base('login.php')) ?>">Already registered? <b>Sign in &rarr;</b></a>
      </div>
      <p class="muted">Buyers, individual sellers and dealers all start here.</p>
      <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="grid reg-grid">
        <?= csrfField() ?>
        <div><label class="form-label">Full Name <span class="req">*</span></label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-5.5 8-5.5s6.5 1.5 8 5.5"/></svg><input class="form-control" name="name" placeholder="Enter your full name" value="<?= e($_POST['name'] ?? '') ?>" required></div></div>
        <div><label class="form-label">Email Address <span class="req">*</span></label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg><input class="form-control" type="email" name="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>" required></div></div>
        <div><label class="form-label">Mobile Number <span class="req">*</span></label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><input class="form-control num" name="mobile" placeholder="+91 98765 43210" value="<?= e($_POST['mobile'] ?? '') ?>" required></div></div>
        <div><label class="form-label">City <span class="req">*</span></label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg><select class="form-select" name="city" required><option value="" disabled <?= $postedCity === '' ? 'selected' : '' ?>>Select your city</option><?php foreach ($cities as $c): ?><option <?= $postedCity === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div></div>
        <div><label class="form-label">I Want To <span class="req">*</span></label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16l1.5-4.5A2 2 0 0 1 8.4 10h7.2a2 2 0 0 1 1.9 1.5L19 16"/><path d="M4 16h16v3.5H4z"/><circle cx="8" cy="19.5" r="1.4"/><circle cx="16" cy="19.5" r="1.4"/></svg><select class="form-select" name="role" required><option value="" disabled selected>Select option</option><option value="buyer">Buy a car</option><option value="seller">Sell my car</option><option value="dealer">Register as dealer</option></select></div></div>
        <div><label class="form-label">Dealership Name (Dealers Only)</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 21v-4h6v4M8 7h2M8 11h2M14 7h2M14 11h2"/></svg><input class="form-control" name="company" placeholder="Enter dealership name" value="<?= e($_POST['company'] ?? '') ?>"></div></div>
        <div class="full"><label class="form-label">PAN / Dealer Licence No. (KYC, Sellers &amp; Dealers)</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.8-1.4 1.8-2 3-2s2.2.6 3 2M14 9h5M14 13h5"/></svg><input class="form-control" name="id_proof" placeholder="Enter PAN or dealer licence number" value="<?= e($_POST['id_proof'] ?? '') ?>"></div></div>
        <div class="full"><label class="form-label">Password (Minimum 8 characters) <span class="req">*</span></label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15" r="1.4" fill="currentColor" stroke="none"/></svg><input class="form-control" type="password" id="regPassword" name="password" placeholder="Create a strong password" required><button type="button" class="pw-eye" data-pw-toggle="regPassword" aria-label="Show password"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.8"/></svg></button></div></div>
        <div class="full"><button class="btn-create" type="submit"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10" cy="8" r="3.5"/><path d="M3 20c1.2-3.4 4-4.8 7-4.8s5.8 1.4 7 4.8M18 8v6M15 11h6"/></svg> Create Account <span aria-hidden="true">&rarr;</span></button></div>
      </form>
      <div class="or"><span>OR</span></div>
      <div class="oauth">
        <a class="oauth-btn" href="#" data-soon="Google sign-in is coming soon"><svg viewBox="0 0 24 24" width="17" height="17"><path fill="#4285F4" d="M23.5 12.3c0-.9-.1-1.5-.3-2.3H12v4.3h6.5c-.1 1.1-.8 2.7-2.4 3.8l3.6 2.8c2.2-2 3.8-5 3.8-8.6z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 7.9-2.9l-3.8-2.9c-1 .7-2.4 1.2-4.1 1.2-3.2 0-5.9-2.1-6.8-5.1l-3.7 2.9C3.5 21.3 7.5 24 12 24z"/><path fill="#FBBC05" d="M5.2 14.3c-.2-.7-.4-1.5-.4-2.3s.1-1.6.4-2.3L1.5 6.9C.5 8.9 0 10.4 0 12s.5 3.1 1.5 4.9l3.7-2.6z"/><path fill="#EA4335" d="M12 4.7c1.8 0 3 .8 3.7 1.4l3.3-3.2C17.9 1.1 15.2 0 12 0 7.5 0 3.5 2.7 1.5 7l3.6 2.8c1-3 3.7-5.1 6.9-5.1z"/></svg> Continue with Google</a>
        <a class="oauth-btn" href="#" data-soon="Facebook sign-in is coming soon"><svg viewBox="0 0 24 24" width="17" height="17"><circle cx="12" cy="12" r="12" fill="#1877F2"/><path fill="#fff" d="M16.5 12.2h-2.2v6.6h-2.7v-6.6H10V9.7h1.6V8.1c0-1.3.6-2.4 2.4-2.4h1.8v2.2h-1.1c-.6 0-.8.4-.8.9v1h2.1l-.5 2.4z"/></svg> Continue with Facebook</a>
        <a class="oauth-btn" href="#" data-soon="Apple sign-in is coming soon"><svg viewBox="0 0 24 24" width="17" height="17" fill="#111"><path d="M17.05 12.54c0-2.4 1.96-3.55 2.05-3.6-1.12-1.64-2.86-1.86-3.48-1.89-1.48-.15-2.89.87-3.64.87-.75 0-1.9-.85-3.13-.83-1.61.02-3.1.94-3.93 2.38-1.68 2.91-.43 7.22 1.2 9.59.8 1.15 1.75 2.45 3 2.4 1.2-.05 1.66-.78 3.12-.78s1.87.78 3.14.75c1.3-.02 2.12-1.17 2.91-2.33.92-1.34 1.3-2.64 1.32-2.71-.03-.01-2.54-.97-2.58-3.86zM14.14 4.06c.66-.8 1.1-1.91 1.1-3.02-1.06.04-2.35.71-3.11 1.51-.68.79-1.28 2.05-1.12 3.02 1.18.09 2.39-.6 3.13-1.51z"/></svg> Continue with Apple</a>
      </div>
      <p class="terms">By creating an account, you agree to our <a href="<?= e(base('terms.php')) ?>">Terms &amp; Conditions</a> and <a href="<?= e(base('privacy.php')) ?>">Privacy Policy</a>.</p>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
