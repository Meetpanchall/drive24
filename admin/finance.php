<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, ['new', 'under_review', 'approved', 'rejected', 'disbursed'], true)) {
        updateRow('loan_applications', ['status' => $status], 'id = ?', [$id]);
        $loan = fetchOne('SELECT * FROM loan_applications WHERE id = ?', [$id]);
        if ($loan) {
            notify((int) $loan['user_id'], 'Loan application ' . str_replace('_', ' ', $status),
                $loan['lender'] . ' - ' . rupees($loan['amount']), 'account.php');
            if ($status === 'approved') {
                insert('documents', ['user_id' => (int) $loan['user_id'], 'doc_type' => 'loan',
                    'doc_name' => 'Loan sanction letter - ' . $loan['lender'], 'status' => 'verified']);
            }
        }
        logActivity((int) $admin['id'], 'loan.updated', '#' . $id . ' -> ' . $status);
        flash('success', 'Loan #' . $id . ' marked ' . $status . '.');
    }
    redirect(base('admin/finance.php'));
}

$rows = [];
try {
    $rows = fetchAll('SELECT a.*, v.make, v.model, v.year, u.name AS customer, u.mobile
        FROM loan_applications a JOIN listings l ON l.id = a.listing_id JOIN vehicles v ON v.id = l.vehicle_id
        JOIN users u ON u.id = a.user_id ORDER BY a.id DESC');
} catch (Throwable $e) { /* table created on next request */ }
$counts = [];
foreach ($rows as $r) { $k = (string) $r['status']; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Loan management', 'finance');
?>
<p class="muted">Buyer loan applications routed to partner lenders. Approving one drops a sanction letter into the buyer's document vault.</p>
<div class="kpis">
  <div class="kpi"><small>Applications</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>#</th><th>Customer</th><th>Car</th><th>Lender</th><th>Amount</th><th>Tenure</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No loan applications yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e(refCode('LN', (int) $r['id'])) ?></td>
      <td><?= e((string) $r['customer']) ?><div class="muted" style="font-size:12px"><?= e((string) ($r['employment'] ?? '')) ?> &middot; <span class="num"><?= rupees($r['monthly_income']) ?>/mo</span></div></td>
      <td><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></td>
      <td><?= e((string) $r['lender']) ?><div class="muted num" style="font-size:12px"><?= e((string) $r['rate']) ?>%</div></td>
      <td class="num"><?= rupees($r['amount']) ?></td>
      <td class="num"><?= (int) $r['tenure_months'] ?> mo</td>
      <td><?= statusBadge((string) $r['status']) ?></td>
      <td><form method="post" style="display:flex;gap:6px"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <select class="form-select" style="padding:6px 8px" name="status">
          <?php foreach (['new', 'under_review', 'approved', 'rejected', 'disbursed'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
          <?php endforeach; ?>
        </select><button class="btn btn-dark btn-sm" type="submit">Save</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
