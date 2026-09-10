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
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        logActivity($id, 'auth.register', $email);
        flash('success', 'Welcome to DRIVE24, your account is ready.');
        redirect($role === 'buyer' ? base('account.php') : base('seller/dashboard.php'));
    }
}

renderHeader('Create account', '');
?>
<div class="wrap">
  <div class="auth-wrap">
    <div class="auth-side">
      <h2 style="color:#fff">Create your DRIVE24 account</h2>
      <p style="color:#dbeafe">Buyers, individual sellers and dealers all start here.</p>
      <ul>
        <li>Free listing with AI price suggestion</li>
        <li>Verified buyer leads and offers</li>
        <li>Doorstep inspection and paperwork</li>
        <li>Payout within 2 working days</li>
      </ul>
    </div>
    <div class="card-pad">
      <h1 style="font-size:1.5rem">Sign up</h1>
      <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <?= csrfField() ?>
        <div style="grid-column:1/-1"><label class="form-label">Full name</label><input class="form-control" name="name" required></div>
        <div><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
        <div><label class="form-label">Mobile</label><input class="form-control num" name="mobile" required></div>
        <div><label class="form-label">City</label><input class="form-control" name="city"></div>
        <div><label class="form-label">I want to</label><select class="form-select" name="role">
          <option value="buyer">Buy a car</option><option value="seller">Sell my car</option><option value="dealer">Register as dealer</option></select></div>
        <div style="grid-column:1/-1"><label class="form-label">Dealership name (dealers only)</label><input class="form-control" name="company"></div>
        <div style="grid-column:1/-1"><label class="form-label">Password (min 8 characters)</label><input class="form-control" type="password" name="password" required></div>
        <div style="grid-column:1/-1"><button class="btn btn-primary btn-block btn-lg" type="submit">Create account</button></div>
      </form>
      <p class="muted" style="margin-top:12px">Already registered? <a href="<?= e(base('login.php')) ?>">Sign in</a></p>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
