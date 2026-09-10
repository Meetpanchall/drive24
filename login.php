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
                logActivity((int) $row['id'], 'auth.login', $email);
                $next = (string) ($_POST['next'] ?? '');
                if ($next !== '') { redirect($next); }
                if ($row['role'] === 'admin') { redirect(base('admin/index.php')); }
                if (in_array($row['role'], ['seller', 'dealer'], true)) { redirect(base('seller/dashboard.php')); }
                redirect(base('account.php'));
            }
        } else {
            $error = 'Incorrect email or password.';
        }
    }
}

renderHeader('Sign in', '');
?>
<div class="wrap">
  <div class="auth-wrap">
    <div class="auth-side">
      <h2 style="color:#fff">Welcome back to DRIVE24</h2>
      <p style="color:#dbeafe">One account for buying, selling, finance and RC transfer.</p>
      <ul>
        <li>Track orders and deliveries</li>
        <li>Manage offers and test drives</li>
        <li>Save cars and get price-drop alerts</li>
        <li>Sellers get a full listing dashboard</li>
      </ul>
      <div class="card card-pad" style="background:rgba(255,255,255,.12);border:0;color:#fff;margin-top:18px">
        <b>Demo logins</b>
        <div class="num" style="font-size:13.5px;margin-top:6px">
          admin@drive24.in &middot; admin<br>
          seller@drive24.in &middot; dealer<br>
          meet@example.com &middot; buyer<br>
          Password: Drive24@2026
        </div>
      </div>
    </div>
    <div class="card-pad">
      <h1 style="font-size:1.5rem">Sign in</h1>
      <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="next" value="<?= e((string) ($_GET['next'] ?? '')) ?>">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>" required autofocus>
        <label class="form-label" style="margin-top:10px">Password</label>
        <input class="form-control" type="password" name="password" required>
        <button class="btn btn-primary btn-block btn-lg" style="margin-top:16px" type="submit">Sign in</button>
      </form>
      <p class="muted" style="margin-top:14px">New to DRIVE24? <a href="<?= e(base('register.php')) ?>">Create an account</a></p>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
