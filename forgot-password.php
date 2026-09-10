<?php
// Secure account recovery (SRS: auth + password reset).
require_once __DIR__ . '/includes/layout.php';

$demoLink = null;
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $row = $email !== '' && dbReady() ? fetchOne('SELECT id FROM users WHERE email = ?', [$email]) : null;
    if ($row) {
        $token = bin2hex(random_bytes(32));
        insert('password_resets', ['user_id' => (int) $row['id'],
            'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $demoLink = base('reset-password.php?token=' . $token);
        logActivity((int) $row['id'], 'auth.reset_requested', $email);
    }
    // Same message either way so accounts cannot be enumerated.
    $message = 'If an account exists for that e-mail, a reset link was generated (valid 1 hour).';
}

renderHeader('Forgot password', '');
?>
<div class="wrap section" style="max-width:520px">
  <div class="card card-pad">
    <h1 style="font-size:1.5rem">Reset your password</h1>
    <p class="muted">Enter your account e-mail and we will generate a secure one-hour reset link.</p>
    <?php if ($message !== ''): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($demoLink !== null): ?>
      <div class="alert info">Demo mode (no mail server): <a href="<?= e($demoLink) ?>">open your reset link</a>.</div>
    <?php endif; ?>
    <form method="post">
      <?= csrfField() ?>
      <label class="form-label">Account e-mail</label>
      <input class="form-control" type="email" name="email" required>
      <button class="btn btn-primary btn-block" style="margin-top:12px" type="submit">Send reset link</button>
    </form>
    <p class="muted" style="margin-top:12px"><a href="<?= e(base('login.php')) ?>">&larr; Back to sign in</a></p>
  </div>
</div>
<?php renderFooter(); ?>
