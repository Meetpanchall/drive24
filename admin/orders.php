<?php
require_once __DIR__ . '/../includes/listings.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$statusCol = 'status';
$allowed = ['pending', 'confirmed', 'processing', 'in_transit', 'delivered', 'cancelled', 'returned'];

$rcAllowed = ['sale_completed', 'documents_verified', 'application_filed', 'rto_processing', 'transfer_completed'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['form'] ?? '') === 'rc') {
        $rcStatus = (string) ($_POST['rc_status'] ?? '');
        $ord = fetchOne('SELECT o.*, l.seller_id FROM orders o JOIN listings l ON l.id = o.listing_id WHERE o.id = ?', [$id]);
        if ($ord && in_array($rcStatus, $rcAllowed, true)) {
            $rc = fetchOne('SELECT id FROM rc_transfers WHERE order_id = ?', [$id]);
            $rcData = ['status' => $rcStatus,
                'rto_office' => mb_substr(trim((string) ($_POST['rto_office'] ?? '')), 0, 120) ?: null,
                'application_no' => mb_substr(trim((string) ($_POST['application_no'] ?? '')), 0, 60) ?: null,
                'remark' => mb_substr(trim((string) ($_POST['remark'] ?? '')), 0, 255) ?: null];
            if ($rc) { updateRow('rc_transfers', $rcData, 'order_id = ?', [$id]); }
            else {
                insert('rc_transfers', $rcData + ['order_id' => $id, 'listing_id' => (int) $ord['listing_id'],
                    'buyer_id' => (int) $ord['buyer_id'], 'seller_id' => (int) $ord['seller_id']]);
            }
            notify((int) $ord['buyer_id'], 'RC transfer update', 'Order ' . $ord['order_no'] . ': ' . str_replace('_', ' ', $rcStatus), 'order.php?id=' . $id);
            logActivity((int) $admin['id'], 'rc.updated', '#' . $id . ' -> ' . $rcStatus);
            flash('success', 'RC transfer for order #' . $id . ' updated.');
        }
    } else {
        $status = (string) ($_POST['status'] ?? '');
        if ($id > 0 && in_array($status, $allowed, true)) {
            $data = ['status' => $status];
            updateRow('orders', $data, 'id = ?', [$id]);
            if ($status === 'delivered') {
                q("UPDATE listings l JOIN orders o ON o.listing_id = l.id SET l.status = 'sold' WHERE o.id = ?", [$id]);
            }
            logActivity((int) $admin['id'], 'orders.updated', '#' . $id . ' -> ' . $status);
            flash('success', 'Record #' . $id . ' updated to ' . $status . '.');
        }
    }
    redirect(base('admin/orders.php'));
}

$rows = fetchAll("SELECT o.*, v.make, v.model, v.year, u.name AS buyer, rc.status AS rc_status, rc.rto_office, rc.application_no FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = o.buyer_id LEFT JOIN rc_transfers rc ON rc.order_id = o.id ORDER BY o.id DESC");
$handDocs = [];
foreach (fetchAll("SELECT order_id, file_url, doc_name FROM documents WHERE doc_type = 'handover' ORDER BY id") as $d) { $handDocs[(int) $d['order_id']][] = $d; }
$counts = [];
foreach ($rows as $r) { $k = (string) $r[$statusCol]; $counts[$k] = ($counts[$k] ?? 0) + 1; }

adminHeader('Order lifecycle', 'orders');
?>
<p class="muted">Move orders through documentation, dispatch and delivery. Buyers track the same timeline on their order page.</p>
<div class="kpis">
  <div class="kpi"><small>Total</small><b class="num"><?= count($rows) ?></b></div>
  <?php foreach ($counts as $k => $n): ?><div class="kpi"><small><?= e(ucfirst(str_replace('_', ' ', $k))) ?></small><b class="num"><?= (int) $n ?></b></div><?php endforeach; ?>
</div>
<div class="table-wrap" style="margin-top:14px"><table class="data">
  <thead><tr><th>Order</th><th>Vehicle</th><th>Buyer</th><th>Amount</th><th>RC transfer</th><th>Handover</th><th>Status</th><th>Update</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">Nothing here yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= e((string) ((string) $r['order_no'])) ?></td>
      <td><?= e((string) ($r['year'] . ' ' . $r['make'] . ' ' . $r['model'])) ?></td>
      <td><?= e((string) ((string) $r['buyer'])) ?></td>
      <td class="num"><?= e((string) (rupees($r['amount']))) ?></td>
      <td><small class="muted num"><?= e((string) ($r['delivery_city'] ?? '')) ?> &middot; <?= e((string) ($r['delivery_date'] ? date('d M Y', strtotime((string) $r['delivery_date'])) : '')) ?></small><br>
        <?= $r['rc_status'] ? statusBadge($r['rc_status'] === 'transfer_completed' ? 'verified' : 'processing') . ' <small>' . e(str_replace('_', ' ', (string) $r['rc_status'])) . '</small>' : '<small class="muted">not started</small>' ?>
        <details><summary class="muted" style="cursor:pointer;font-size:12px">Update RC</summary>
          <form method="post" style="display:grid;gap:4px;margin-top:6px;min-width:210px">
            <?= csrfField() ?><input type="hidden" name="form" value="rc"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <select class="form-select" style="padding:6px 8px" name="rc_status">
              <?php foreach ($rcAllowed as $rs): ?><option value="<?= $rs ?>" <?= ($r['rc_status'] ?? '') === $rs ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $rs))) ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" style="padding:6px 8px" name="rto_office" value="<?= e((string) ($r['rto_office'] ?? '')) ?>" placeholder="RTO office">
            <input class="form-control" style="padding:6px 8px" name="application_no" value="<?= e((string) ($r['application_no'] ?? '')) ?>" placeholder="Application no.">
            <input class="form-control" style="padding:6px 8px" name="remark" placeholder="Remark for buyer">
            <button class="btn btn-dark btn-sm" type="submit">Save RC</button>
          </form>
        </details></td>
      <td><?php if (!empty($r['handover_at'])): ?>
          <?= statusBadge('verified') ?>
          <div class="muted num" style="font-size:12px"><?= e(date('d M H:i', strtotime((string) $r['handover_at']))) ?><br>
          odo <?= number_format((int) $r['handover_odo']) ?> km &middot; fuel <?= (int) $r['handover_fuel'] ?>%</div>
          <?php if (!empty($r['handover_notes'])): ?><div class="muted" style="font-size:12px"><?= e((string) $r['handover_notes']) ?></div><?php endif; ?>
          <?php foreach ($handDocs[(int) $r['id']] ?? [] as $hd): ?>
            <div><a style="font-size:12px" href="<?= e(base('assets/uploads/' . $hd['file_url'])) ?>" target="_blank" rel="noopener"><?= e((string) $hd['doc_name']) ?> &nearr;</a></div>
          <?php endforeach; ?>
        <?php elseif (in_array($r[$statusCol], ['processing', 'in_transit'], true)): ?>
          <small class="muted">OTP <b class="num"><?= e((string) ($r['handover_otp'] ?? '-')) ?></b><br>awaiting ceremony</small>
        <?php else: ?><small class="muted">-</small><?php endif; ?></td>
      <td><?= statusBadge((string) $r[$statusCol]) ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select class="form-select" style="padding:6px 8px" name="status">
            <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>" <?= $r[$statusCol] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-dark btn-sm" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
