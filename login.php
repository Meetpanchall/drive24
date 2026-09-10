<?php
require_once __DIR__ . '/includes/layout.php';

if (user() !== null) { redirect(base('account.php')); }
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!dbReady()) {
        $error = 'MySQL is not connected yet. Import database/schema.sql first.';
    } else {
        $row = fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        if ($row && password_verify($password, (string) $row['password_hash'])) {
            if ($row['status'] !== 'active') {
                $error = 'This account is suspended. Contact support.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $row['id'];
                if (isset($_POST['remember'])) {
                    @ini_set('session.gc_maxlifetime', (string) (30 * 86400));
                    setcookie((string) session_name(), (string) session_id(),
                        ['expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                }
                logActivity((int) $row['id'], 'auth.login', $email);
                $next = (string) ($_POST['next'] ?? '');
                if ($next !== '') { redirect($next); }
                if ($row['role'] === 'admin') { redirect(base('admin/index.php')); }
                if (in_array($row['role'], ['seller', 'dealer'], true)) { redirect(base('seller/dashboard.php')); }
                redirect(base('account.php'));
            }
        } else {
            // Help users who imported the schema but skipped the install.php password step.
            $placeholder = str_starts_with((string) ($row['password_hash'] ?? ''), '$2y$10$e0NRzC0m0Qm2m0iQ1kQ0t');
            $error = $placeholder
                ? 'Demo passwords are not activated yet. Open install.php and click "Set all demo passwords to Drive24@2026", then sign in again.'
                : 'Incorrect email or password.';
        }
    }
}

renderHeader('Sign in', '');
?>
<div class="wrap">
  <div class="auth-wrap reg-wrap login-wrap">
    <div class="auth-side reg-side">
      <a href="<?= e(base('index.php')) ?>" aria-label="DRIVE24 home"><img src="<?= e(base('assets/img/logo-word-white.svg')) ?>" alt="DRIVE24" height="38"></a>
      <div class="login-tag">BUY <span>&bull;</span> SELL <span>&bull;</span> TRADE</div>
      <h2>Welcome Back to<br><span class="hl">DRIVE24</span></h2>
      <p class="reg-sub">Your trusted platform for buying, selling, financing and RC transfer. Drive your dream today!</p>
      <ul class="reg-feats">
        <li><span class="reg-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 3v6c0 5-3.2 8.6-7 11-3.8-2.4-7-6-7-11V5z"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><b>Verified Listings</b><small>Every car is inspected and verified</small></div></li>
        <li><span class="reg-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v7l7 7 8-8-7-8z"/><circle cx="8" cy="8" r="1.4" fill="currentColor" stroke="none"/></svg></span><div><b>Best Prices</b><small>Get the best value for your car</small></div></li>
        <li><span class="reg-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 3v6c0 5-3.2 8.6-7 11-3.8-2.4-7-6-7-11V5z"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><b>Secure Transactions</b><small>Safe and hassle-free payment process</small></div></li>
        <li><span class="reg-ic"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13a8 8 0 0 1 16 0"/><rect x="3" y="13" width="4" height="6" rx="2"/><rect x="17" y="13" width="4" height="6" rx="2"/><path d="M20 19a4 4 0 0 1-4 3h-2"/></svg></span><div><b>Dedicated Support</b><small>We're here to help, always</small></div></li>
      </ul>
      <div class="reg-cars">
        <img src="<?= e(base('assets/img/car9.jpg')) ?>" alt="Certified SUVs">
        <span class="script">Drive Your Dreams</span>
      </div>
    </div>
    <div class="card-pad reg-form">
      <div class="reg-top">
        <h1>Sign in to<br><span class="blue">DRIVE24</span></h1>
        <a class="signin-link" href="<?= e(base('register.php')) ?>">New to DRIVE24? <b>Create an account &rarr;</b></a>
      </div>
      <p class="muted">Welcome back! Please enter your details to continue.</p>
      <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="grid reg-grid login-grid">
        <?= csrfField() ?>
        <input type="hidden" name="next" value="<?= e((string) ($_GET['next'] ?? '')) ?>">
        <div class="full"><label class="form-label">Email Address</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg><input class="form-control" type="email" name="email" placeholder="Enter your email address" value="<?= e((string) ($_POST['email'] ?? '')) ?>" required autofocus></div></div>
        <div class="full"><label class="form-label">Password</label>
          <div class="in-wrap"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15" r="1.4" fill="currentColor" stroke="none"/></svg><input class="form-control" type="password" id="loginPassword" name="password" placeholder="Enter your password" required><button type="button" class="pw-eye" data-pw-toggle="loginPassword" aria-label="Show password"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.8"/></svg></button></div></div>
        <div class="full remember-row">
          <label class="remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
          <a href="<?= e(base('forgot-password.php')) ?>">Forgot password?</a>
        </div>
        <div class="full"><button class="btn-create" type="submit">Sign In <span aria-hidden="true">&rarr;</span></button></div>
      </form>
      <div class="or"><span>OR</span></div>
      <div class="oauth oauth-2">
        <a class="oauth-btn" href="#" data-soon="Google sign-in is coming soon"><svg viewBox="0 0 24 24" width="17" height="17"><path fill="#4285F4" d="M23.5 12.3c0-.9-.1-1.5-.3-2.3H12v4.3h6.5c-.1 1.1-.8 2.7-2.4 3.8l3.6 2.8c2.2-2 3.8-5 3.8-8.6z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 7.9-2.9l-3.8-2.9c-1 .7-2.4 1.2-4.1 1.2-3.2 0-5.9-2.1-6.8-5.1l-3.7 2.9C3.5 21.3 7.5 24 12 24z"/><path fill="#FBBC05" d="M5.2 14.3c-.2-.7-.4-1.5-.4-2.3s.1-1.6.4-2.3L1.5 6.9C.5 8.9 0 10.4 0 12s.5 3.1 1.5 4.9l3.7-2.6z"/><path fill="#EA4335" d="M12 4.7c1.8 0 3 .8 3.7 1.4l3.3-3.2C17.9 1.1 15.2 0 12 0 7.5 0 3.5 2.7 1.5 7l3.6 2.8c1-3 3.7-5.1 6.9-5.1z"/></svg> Continue with Google</a>
        <a class="oauth-btn" href="#" data-soon="Facebook sign-in is coming soon"><svg viewBox="0 0 24 24" width="17" height="17"><circle cx="12" cy="12" r="12" fill="#1877F2"/><path fill="#fff" d="M16.5 12.2h-2.2v6.6h-2.7v-6.6H10V9.7h1.6V8.1c0-1.3.6-2.4 2.4-2.4h1.8v2.2h-1.1c-.6 0-.8.4-.8.9v1h2.1l-.5 2.4z"/></svg> Continue with Facebook</a>
      </div>
      <details class="demo-logins">
        <summary>Use a demo account</summary>
        <div class="num">admin@drive24.in &middot; seller@drive24.in &middot; meet@example.com<br>Password for all: <b>Drive24@2026</b></div>
      </details>
      <p class="terms">By continuing, you agree to our <a href="<?= e(base('terms.php')) ?>">Terms &amp; Conditions</a> and <a href="<?= e(base('privacy.php')) ?>">Privacy Policy</a>.</p>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
