<?php
// Mobile OTP verification (SRS: email/mobile with OTP). Demo mode shows the
// code on screen - wire an SMS gateway (Twilio/MSG91) in production.
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$mobile = trim((string) ($_GET['mobile'] ?? $u['mobile'] ?? ''));
$demoCode = null;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');
    $mobile = trim((string) ($_POST['mobile'] ?? $mobile));
    if ($action === 'send' && $mobile !== '') {
        $code = (string) random_int(100000, 999999);
        insert('otp_codes', ['user_id' => $u['id'], 'mobile' => $mobile,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'purpose' => 'verify',
            'expires_at' => date('Y-m-d H:i:s', time() + 600)]);
        updateRow('users', ['mobile' => $mobile], 'id = ?', [$u['id']]);
        $demoCode = $code;
        flash('success', 'OTP sent to ' . $mobile . ' (valid 10 minutes).');
    } elseif ($action === 'verify') {
        $row = fetchOne('SELECT * FROM otp_codes WHERE user_id = ? AND mobile = ? AND verified = 0 AND expires_at > NOW()
            ORDER BY id DESC', [$u['id'], $mobile]);
        if (!$row) {
            $error = 'No active OTP for this number. Please resend.';
        } elseif ((int) $row['attempts'] >= 5) {
            $error = 'Too many attempts. Please request a fresh OTP.';
        } elseif (!password_verify(trim((string) ($_POST['code'] ?? '')), (string) $row['code_hash'])) {
            q('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [(int) $row['id']]);
            $error = 'Incorrect OTP. Please try again.';
        } else {
            q('UPDATE otp_codes SET verified = 1 WHERE id = ?', [(int) $row['id']]);
            updateRow('users', ['mobile_verified' => 1], 'id = ?', [$u['id']]);
            logActivity((int) $u['id'], 'auth.otp_verified', $mobile);
            flash('success', 'Mobile number verified.');
            redirect(base('account.php'));
        }
    }
}

renderHeader('Verify mobile number', '');
?>
<div class="wrap section" style="max-width:560px">
  <div class="card card-pad">
    <h1 style="font-size:1.5rem">Verify your mobile</h1>
    <p class="muted">SRS login security: one-time passcodes protect high-value transactions.</p>
    <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($demoCode !== null): ?>
      <div class="alert success">Demo mode - your OTP is <b class="num" style="font-size:1.2rem"><?= e($demoCode) ?></b></div>
    <?php endif; ?>
    <form method="post" style="display:flex;gap:8px">
      <?= csrfField() ?><input type="hidden" name="action" value="send">
      <input class="form-control num" name="mobile" value="<?= e($mobile) ?>" placeholder="98XXXXXXXX" required style="flex:1">
      <button class="btn btn-outline" type="submit">Send OTP</button>
    </form>
    <form method="post" style="display:flex;gap:8px;margin-top:12px">
      <?= csrfField() ?><input type="hidden" name="action" value="verify">
      <input type="hidden" name="mobile" value="<?= e($mobile) ?>">
      <input class="form-control num" name="code" inputmode="numeric" maxlength="6" placeholder="6-digit OTP" required style="flex:1;font-size:1.3rem;letter-spacing:.35em;text-align:center">
      <button class="btn btn-primary" type="submit">Verify</button>
    </form>
  </div>
</div>
<?php renderFooter(); ?>
