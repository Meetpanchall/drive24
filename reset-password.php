<?php
require_once __DIR__ . '/includes/layout.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$valid = null;
if ($token !== '' && dbReady()) {
    $valid = fetchOne('SELECT r.*, u.email FROM password_resets r JOIN users u ON u.id = r.user_id
        WHERE r.token_hash = ? AND r.used = 0 AND r.expires_at > NOW()', [hash('sha256', $token)]);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $pw = (string) ($_POST['password'] ?? '');
    if (!$valid) {
        $error = 'This reset link is invalid or has expired.';
    } elseif (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        updateRow('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)], 'id = ?', [(int) $valid['user_id']]);
        q('UPDATE password_resets SET used = 1 WHERE id = ?', [(int) $valid['id']]);
        logActivity((int) $valid['user_id'], 'auth.reset_completed', '');
        flash('success', 'Password updated. Please sign in.');
        redirect(base('login.php'));
    }
}

renderHeader('Set a new password', '');
?>
<div class="wrap section" style="max-width:520px">
  <div class="card card-pad">
    <h1 style="font-size:1.5rem">Set a new password</h1>
    <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($token === '' || !$valid): ?>
      <div class="alert error">This reset link is invalid or has expired. <a href="<?= e(base('forgot-password.php')) ?>">Request a new one</a>.</div>
    <?php else: ?>
      <p class="muted">Resetting password for <b><?= e((string) $valid['email']) ?></b>.</p>
      <form method="post">
        <?= csrfField() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="form-label">New password (min 8 characters)</label>
        <input class="form-control" type="password" name="password" required>
        <button class="btn btn-primary btn-block" style="margin-top:12px" type="submit">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php renderFooter(); ?>
