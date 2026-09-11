<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    if (($_POST['form'] ?? '') === 'profile') {
        updateRow('users', [
            'name'   => trim((string) ($_POST['name'] ?? $admin['name'])),
            'mobile' => trim((string) ($_POST['mobile'] ?? '')),
            'city'   => trim((string) ($_POST['city'] ?? '')),
        ], 'id = ?', [$admin['id']]);
        logActivity((int) $admin['id'], 'admin.profile', 'profile updated');
        flash('success', 'Profile saved.');
    } elseif (($_POST['form'] ?? '') === 'password') {
        $row = fetchOne('SELECT password_hash FROM users WHERE id = ?', [$admin['id']]);
        if (!$row || !password_verify((string) ($_POST['current'] ?? ''), (string) $row['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen((string) ($_POST['new'] ?? '')) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } else {
            updateRow('users', ['password_hash' => password_hash((string) $_POST['new'], PASSWORD_DEFAULT)], 'id = ?', [$admin['id']]);
            logActivity((int) $admin['id'], 'admin.password', 'password changed');
            flash('success', 'Password changed.');
        }
    }
    redirect(base('admin/profile.php'));
}

$admin = fetchOne('SELECT * FROM users WHERE id = ?', [$admin['id']]);
adminHeader('My profile', 'profile');
?>
<div class="split-3" style="max-width:900px">
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Profile</h2>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="form" value="profile">
      <div style="margin-bottom:10px"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($admin['name']) ?>"></div>
      <div style="margin-bottom:10px"><label class="form-label">Email</label><input class="form-control" value="<?= e($admin['email']) ?>" disabled></div>
      <div style="margin-bottom:10px"><label class="form-label">Mobile</label><input class="form-control" name="mobile" value="<?= e((string) $admin['mobile']) ?>"></div>
      <div style="margin-bottom:14px"><label class="form-label">City</label><input class="form-control" name="city" value="<?= e((string) $admin['city']) ?>"></div>
      <button class="btn btn-primary" type="submit">Save profile</button>
    </form>
  </div>
  <div class="card card-pad">
    <h2 style="font-size:1.1rem">Change password</h2>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="form" value="password">
      <div style="margin-bottom:10px"><label class="form-label">Current password</label><input class="form-control" type="password" name="current" required autocomplete="current-password"></div>
      <div style="margin-bottom:14px"><label class="form-label">New password (8+ chars)</label><input class="form-control" type="password" name="new" required minlength="8" autocomplete="new-password"></div>
      <button class="btn btn-outline" type="submit">Update password</button>
    </form>
  </div>
</div>
<?php adminFooter(); ?>
