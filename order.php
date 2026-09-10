<?php
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$id = (int) ($_GET['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'cancel') {
        q("UPDATE orders SET status = 'cancelled' WHERE id = ? AND buyer_id = ? AND status IN ('pending','confirmed')", [$id, $u['id']]);
        q("UPDATE listings l JOIN orders o ON o.listing_id = l.id SET l.status = 'approved' WHERE o.id = ?", [$id]);
        $held = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM escrow_ledger WHERE order_id = ? AND kind = 'hold'", [$id], 0);
        if ($held > 0) {
            insert('escrow_ledger', ['order_id' => $id, 'kind' => 'refund', 'amount' => $held, 'note' => 'Escrow refunded on cancellation']);
            q("UPDATE payments SET status = 'refunded' WHERE order_id = ? AND status = 'paid'", [$id]);
        }
        flash('success', 'Order cancelled, escrow refunded and the car returned to inventory.');
    } elseif (($_POST['action'] ?? '') === 'return') {
        $ord = fetchOne('SELECT o.*, l.seller_id FROM orders o JOIN listings l ON l.id = o.listing_id WHERE o.id = ? AND o.buyer_id = ?', [$id, $u['id']]);
        $within = $ord && !empty($ord['delivery_date']) && (time() - strtotime((string) $ord['delivery_date'])) <= 7 * 86400;
        if ($ord && $ord['status'] === 'delivered' && $within) {
            q("UPDATE orders SET status = 'returned' WHERE id = ?", [$id]);
            q("UPDATE listings l JOIN orders o ON o.listing_id = l.id SET l.status = 'approved' WHERE o.id = ?", [$id]);
            $held = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM escrow_ledger WHERE order_id = ? AND kind = 'hold'", [$id], 0);
            if ($held > 0) {
                insert('escrow_ledger', ['order_id' => $id, 'kind' => 'refund', 'amount' => $held, 'note' => 'Escrow refunded on 7-day return']);
                q("UPDATE payments SET status = 'refunded' WHERE order_id = ? AND status = 'paid'", [$id]);
            }
            notify((int) $ord['seller_id'], 'Buyer requested a return', 'Order ' . $ord['order_no'], 'seller/orders.php');
            notifyAdmins('Return requested', 'Order ' . $ord['order_no'] . ' marked returned by buyer.', 'admin/orders.php');
            flash('success', 'Return accepted. We will collect the car and refund you within 5-7 working days.');
        } else {
            flash('error', 'Returns are accepted within 7 days of delivery.');
        }
    } elseif (($_POST['action'] ?? '') === 'review') {
        $ord = fetchOne('SELECT o.*, l.seller_id FROM orders o JOIN listings l ON l.id = o.listing_id WHERE o.id = ? AND o.buyer_id = ?', [$id, $u['id']]);
        $rating = (int) ($_POST['rating'] ?? 0);
        if ($ord && in_array($ord['status'], ['delivered', 'in_transit', 'processing'], true) && $rating >= 1 && $rating <= 5) {
            insert('reviews', ['listing_id' => (int) $ord['listing_id'], 'order_id' => $id, 'author_id' => $u['id'],
                'target_user_id' => (int) $ord['seller_id'], 'reviewer_role' => 'buyer', 'rating' => $rating,
                'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160) ?: null,
                'comment' => mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 2000) ?: null, 'status' => 'pending']);
            flash('success', 'Thanks! Your review was submitted for moderation.');
        } else {
            flash('error', 'Reviews open once your order is being processed.');
        }
    }
    redirect(base('order.php?id=' . $id));
}

$order = fetchOne('SELECT o.*, v.make, v.model, v.year, v.variant, v.image, v.reg_number, u.name AS seller_name
    FROM orders o JOIN listings l ON l.id = o.listing_id JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id
    WHERE o.id = ? AND o.buyer_id = ?', [$id, $u['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect(base('orders.php')); }
$payments = fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$id]);
$docs = fetchAll('SELECT * FROM documents WHERE order_id = ?', [$id]);
$escrow = fetchAll('SELECT * FROM escrow_ledger WHERE order_id = ? ORDER BY id', [$id]);
$myReview = fetchOne('SELECT * FROM reviews WHERE order_id = ? AND author_id = ?', [$id, $u['id']]);
$rc = fetchOne('SELECT * FROM rc_transfers WHERE order_id = ?', [$id]);
$rcStages = ['sale_completed' => 'Sale completed', 'documents_verified' => 'Documents verified',
    'application_filed' => 'RTO application filed', 'rto_processing' => 'RTO processing', 'transfer_completed' => 'Transfer completed'];
$rcIndex = $rc ? (int) array_search((string) $rc['status'], array_keys($rcStages), true) : -1;
$loanEmi = ((int) $order['finance_opted'] && (int) $order['tenure_months'] > 0) ? emiAmount((float) $order['loan_amount'], 9.5, (int) $order['tenure_months']) : 0;
$timeline = ['confirmed' => 'Booking confirmed', 'processing' => 'Documentation &amp; RC transfer', 'in_transit' => 'Vehicle dispatched', 'delivered' => 'Delivered'];
$order_status = (string) $order['status'];
$stages = array_keys($timeline);
$currentIndex = array_search($order_status, $stages, true);
$currentIndex = $currentIndex === false ? 0 : (int) $currentIndex;

renderHeader('Order ' . $order['order_no'], '');
?>
<div class="wrap section">
  <h1 style="font-size:1.5rem">Order <?= e($order['order_no']) ?></h1>
  <div class="steps" style="margin-top:14px">
    <?php $i = 0; foreach ($timeline as $key => $label): ?>
      <div class="step <?= $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'active' : '') ?>"><?= ($i + 1) . '. ' . $label ?></div>
    <?php $i++; endforeach; ?>
  </div>
  <div class="split-3">
    <div class="card card-pad">
      <div style="display:flex;gap:16px;flex-wrap:wrap">
        <img src="<?= e(listingImage($order)) ?>" alt="" style="width:220px;border-radius:12px">
        <div>
          <h2 style="font-size:1.15rem"><?= e($order['year'] . ' ' . $order['make'] . ' ' . $order['model'] . ' ' . $order['variant']) ?></h2>
          <div class="kv"><span>Registration</span><span class="num"><?= e((string) $order['reg_number']) ?></span></div>
          <div class="kv"><span>Seller</span><span><?= e($order['seller_name']) ?></span></div>
          <div class="kv"><span>Status</span><span><?= statusBadge($order_status) ?></span></div>
        </div>
      </div>
      <?php if ($rc): ?>
        <h3 style="margin-top:18px;font-size:1.05rem">RC transfer tracker</h3>
        <div class="steps">
          <?php $ri = 0; foreach ($rcStages as $rk => $rl): ?>
            <div class="step <?= $ri < $rcIndex ? 'done' : ($ri === $rcIndex ? 'active' : '') ?>"><?= ($ri + 1) . '. ' . $rl ?></div>
          <?php $ri++; endforeach; ?>
        </div>
        <div class="kv"><span>RTO office</span><span><?= e((string) ($rc['rto_office'] ?? 'Being assigned')) ?></span></div>
        <?php if (!empty($rc['application_no'])): ?><div class="kv"><span>Application no.</span><span class="num"><?= e((string) $rc['application_no']) ?></span></div><?php endif; ?>
        <?php if (!empty($rc['remark'])): ?><div class="kv"><span>Latest update</span><span><?= e((string) $rc['remark']) ?></span></div><?php endif; ?>
      <?php endif; ?>
      <?php if ($loanEmi > 0): ?>
        <h3 style="margin-top:18px;font-size:1.05rem">Loan repayment schedule</h3>
        <div class="kv"><span>Loan amount</span><span class="num"><?= rupees($order['loan_amount']) ?></span></div>
        <div class="kv"><span>Tenure</span><span class="num"><?= (int) $order['tenure_months'] ?> months @ 9.5% p.a.</span></div>
        <div class="kv"><span>Monthly EMI</span><b class="num"><?= rupees($loanEmi) ?></b></div>
        <div class="kv"><span>Total payable</span><span class="num"><?= rupees($loanEmi * (int) $order['tenure_months']) ?></span></div>
      <?php endif; ?>
      <h3 style="margin-top:18px;font-size:1.05rem">Payments</h3>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Txn ref</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?>
          <tr><td class="num"><?= e($p['txn_ref']) ?></td><td><?= e(strtoupper($p['method'])) ?></td>
            <td class="num"><?= rupees($p['amount']) ?></td><td><?= statusBadge((string) $p['status']) ?></td>
            <td class="num"><?= e(date('d M Y', strtotime((string) $p['created_at']))) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php if ($docs): ?>
        <h3 style="margin-top:18px;font-size:1.05rem">Documents</h3>
        <?php foreach ($docs as $d): ?><div class="kv"><span><?= e($d['doc_name']) ?></span><span><?= statusBadge((string) $d['status']) ?></span></div><?php endforeach; ?>
      <?php endif; ?>
      <?php if ($escrow): ?>
        <h3 style="margin-top:18px;font-size:1.05rem">Escrow ledger</h3>
        <div class="table-wrap" style="border:0"><table class="data">
          <thead><tr><th>Event</th><th>Amount</th><th>Note</th><th>Date</th></tr></thead>
          <tbody><?php foreach ($escrow as $ex): ?>
            <tr><td><?= statusBadge($ex['kind'] === 'hold' ? 'processing' : ($ex['kind'] === 'refund' ? 'returned' : 'delivered')) ?> <?= e(ucfirst($ex['kind'])) ?></td>
              <td class="num"><?= rupees($ex['amount']) ?></td><td class="muted"><?= e((string) $ex['note']) ?></td>
              <td class="num"><?= e(date('d M Y', strtotime((string) $ex['created_at']))) ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
      <?php if (in_array($order_status, ['processing', 'in_transit', 'delivered'], true)): ?>
        <h3 style="margin-top:18px;font-size:1.05rem">Rate your experience</h3>
        <?php if ($myReview): ?>
          <div class="kv"><span>Your rating</span><b class="num">★ <?= (int) $myReview['rating'] ?>/5 &middot; <?= statusBadge((string) $myReview['status']) ?></b></div>
        <?php else: ?>
          <form method="post" class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
            <?= csrfField() ?><input type="hidden" name="action" value="review">
            <div><label class="form-label">Rating</label><select class="form-select" name="rating"><option value="5">★★★★★</option><option value="4">★★★★</option><option value="3">★★★</option><option value="2">★★</option><option value="1">★</option></select></div>
            <div><label class="form-label">Headline</label><input class="form-control" name="title" placeholder="Smooth delivery!"></div>
            <div style="grid-column:1/-1"><label class="form-label">Comment</label><textarea class="form-control" name="comment" rows="2"></textarea></div>
            <div style="grid-column:1/-1"><button class="btn btn-dark btn-sm" type="submit">Submit review</button></div>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <aside class="card card-pad sticky">
      <div class="kv"><span>Car price</span><span class="num"><?= rupees($order['amount']) ?></span></div>
      <div class="kv"><span>Booking paid</span><span class="num"><?= rupees($order['booking_amount']) ?></span></div>
      <div class="kv"><span>Finance</span><span class="num"><?= ((int) $order['finance_opted']) ? rupees($order['loan_amount']) . ' / ' . (int) $order['tenure_months'] . 'm' : 'Not opted' ?></span></div>
      <div class="kv"><span>Delivery</span><span class="num"><?= e(date('d M Y', strtotime((string) $order['delivery_date']))) ?></span></div>
      <div class="kv"><span>Address</span><span><?= e((string) $order['delivery_address']) ?></span></div>
      <?php if (in_array($order_status, ['pending', 'confirmed'], true)): ?>
        <form method="post" style="margin-top:12px"><?= csrfField() ?><input type="hidden" name="action" value="cancel">
          <button class="btn btn-outline btn-block btn-sm" type="submit">Cancel order</button></form>
      <?php endif; ?>
      <?php if ($order_status === 'delivered' && !empty($order['delivery_date']) && (time() - strtotime((string) $order['delivery_date'])) <= 7 * 86400): ?>
        <form method="post" style="margin-top:8px" onsubmit="return confirm('Return this car under the 7-day policy?')"><?= csrfField() ?><input type="hidden" name="action" value="return">
          <button class="btn btn-outline btn-block btn-sm" type="submit">Return within 7 days</button></form>
      <?php endif; ?>
      <a class="btn btn-outline btn-block btn-sm" style="margin-top:8px" href="<?= e(base('invoice.php?id=' . $id)) ?>">Download invoice</a>
      <a class="btn btn-ghost btn-block btn-sm" href="<?= e(base('support.php')) ?>">Need help?</a>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
