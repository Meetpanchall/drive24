<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$u = requireLogin();
$me = (int) $u['id'];

/** Are the two parties connected (order or accepted offer)? Contacts stay hidden until then. */
function chatConnected(int $buyerId, int $sellerId, ?int $listingId): bool
{
    if (!dbReady()) { return false; }
    $order = (int) fetchValue('SELECT COUNT(*) FROM orders o JOIN listings l ON l.id = o.listing_id
        WHERE o.buyer_id = ? AND l.seller_id = ?', [$buyerId, $sellerId], 0);
    if ($order > 0) { return true; }
    $offer = (int) fetchValue("SELECT COUNT(*) FROM offers o JOIN listings l ON l.id = o.listing_id
        WHERE o.buyer_id = ? AND l.seller_id = ? AND o.status = 'accepted'", [$buyerId, $sellerId], 0);
    return $offer > 0;
}

// Start / find a thread for a listing
if (isset($_GET['listing'])) {
    $car = findListing((int) $_GET['listing']);
    if ($car === null) { flash('error', 'Car not found.'); redirect(base('cars.php')); }
    $sellerId = (int) $car['seller_id'];
    if ($sellerId === $me) { flash('error', 'This is your own listing.'); redirect(base('car.php?id=' . (int) $car['id'])); }
    $thread = fetchOne('SELECT * FROM chat_threads WHERE listing_id = ? AND buyer_id = ? AND seller_id = ?',
        [(int) $car['id'], $me, $sellerId]);
    if (!$thread) {
        $tid = insert('chat_threads', ['listing_id' => (int) $car['id'], 'buyer_id' => $me,
            'seller_id' => $sellerId, 'subject' => vehicleTitle($car)]);
        insert('chat_messages', ['thread_id' => $tid, 'sender_id' => $me,
            'body' => 'Hi! I am interested in your ' . vehicleTitle($car) . '. Is it still available?']);
        notify($sellerId, 'New enquiry', vehicleTitle($car), 'chat.php?thread=' . $tid);
        $thread = fetchOne('SELECT * FROM chat_threads WHERE id = ?', [$tid]);
    }
    redirect(base('chat.php?thread=' . (int) $thread['id']));
}

// Support thread (buyer <-> first support/admin user)
if (isset($_GET['support'])) {
    $agent = fetchOne("SELECT id FROM users WHERE role IN ('support','admin') AND status = 'active' ORDER BY id LIMIT 1");
    if (!$agent) { flash('error', 'Support is unavailable right now.'); redirect(base('support.php')); }
    $thread = fetchOne('SELECT * FROM chat_threads WHERE listing_id IS NULL AND buyer_id = ? AND seller_id = ?',
        [$me, (int) $agent['id']]);
    if (!$thread) {
        $tid = insert('chat_threads', ['listing_id' => null, 'buyer_id' => $me,
            'seller_id' => (int) $agent['id'], 'subject' => 'Support chat']);
        $thread = fetchOne('SELECT * FROM chat_threads WHERE id = ?', [$tid]);
    }
    redirect(base('chat.php?thread=' . (int) $thread['id']));
}

// Send a message
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $tid = (int) ($_POST['thread_id'] ?? 0);
    $body = trim((string) ($_POST['body'] ?? ''));
    $thread = fetchOne('SELECT * FROM chat_threads WHERE id = ?', [$tid]);
    if (!$thread || ($me !== (int) $thread['buyer_id'] && $me !== (int) $thread['seller_id'] && $u['role'] !== 'admin')) {
        flash('error', 'You cannot message in this chat.');
        redirect(base('chat.php'));
    }
    if ($body !== '') {
        $flagged = containsFraud($body) ? 1 : 0;
        $imgName = null;
        if (!empty($_FILES['photo']['name'] ?? '')) { $imgName = saveUpload($_FILES['photo'], 'chat'); }
        insert('chat_messages', ['thread_id' => $tid, 'sender_id' => $me,
            'body' => mb_substr($body, 0, 2000), 'image' => $imgName, 'flagged' => $flagged]);
        if ($flagged) {
            q("UPDATE chat_threads SET status = 'flagged' WHERE id = ?", [$tid]);
            logActivity($me, 'chat.flagged', 'Thread #' . $tid);
        }
        $other = $me === (int) $thread['buyer_id'] ? (int) $thread['seller_id'] : (int) $thread['buyer_id'];
        notify($other, 'New message', mb_substr($body, 0, 90), 'chat.php?thread=' . $tid);
    }
    redirect(base('chat.php?thread=' . $tid));
}

$threads = fetchAll('SELECT t.*, v.make, v.model, v.year, v.image,
        b.name AS buyer_name, s.name AS seller_name,
        (SELECT COUNT(*) FROM chat_messages m WHERE m.thread_id = t.id) AS messages
    FROM chat_threads t LEFT JOIN listings l ON l.id = t.listing_id LEFT JOIN vehicles v ON v.id = l.vehicle_id
    JOIN users b ON b.id = t.buyer_id JOIN users s ON s.id = t.seller_id
    WHERE t.buyer_id = ? OR t.seller_id = ? ORDER BY t.updated_at DESC', [$me, $me]);

$active = null;
$messages = [];
if (isset($_GET['thread'])) {
    $tid = (int) $_GET['thread'];
    foreach ($threads as $t) { if ((int) $t['id'] === $tid) { $active = $t; } }
    if ($active === null && $u['role'] === 'admin') {
        $active = fetchOne('SELECT t.*, v.make, v.model, v.year, v.image, b.name AS buyer_name, s.name AS seller_name
            FROM chat_threads t LEFT JOIN listings l ON l.id = t.listing_id LEFT JOIN vehicles v ON v.id = l.vehicle_id
            JOIN users b ON b.id = t.buyer_id JOIN users s ON s.id = t.seller_id WHERE t.id = ?', [$tid]);
    }
    if ($active) {
        $messages = fetchAll('SELECT m.*, u.name AS sender FROM chat_messages m JOIN users u ON u.id = m.sender_id
            WHERE m.thread_id = ? ORDER BY m.id', [$tid]);
    }
}
$connected = $active ? chatConnected((int) $active['buyer_id'], (int) $active['seller_id'],
    $active['listing_id'] !== null ? (int) $active['listing_id'] : null) : false;

renderHeader('Messages', '');
?>
<div class="wrap section">
  <h1 style="font-size:1.6rem">Messages</h1>
  <p class="muted">Chat with buyers and sellers without revealing phone numbers - contact details unlock after a booking or accepted offer.</p>
  <div class="chat-shell">
    <aside class="card card-pad chat-list">
      <a class="btn btn-outline btn-block btn-sm" href="<?= e(base('chat.php?support=1')) ?>">Chat with support</a>
      <?php if (!$threads): ?><p class="muted" style="margin-top:12px">No conversations yet. Open any car and tap <b>Chat with seller</b>.</p><?php endif; ?>
      <?php foreach ($threads as $t):
        $peer = $me === (int) $t['buyer_id'] ? $t['seller_name'] : $t['buyer_name']; ?>
        <a class="chat-row <?= $active && (int) $active['id'] === (int) $t['id'] ? 'active' : '' ?>" href="<?= e(base('chat.php?thread=' . (int) $t['id'])) ?>">
          <b><?= e($peer) ?></b>
          <small class="muted"><?= e((string) ($t['subject'] ?? 'Support chat')) ?> &middot; <?= (int) $t['messages'] ?> msgs</small>
          <?= $t['status'] === 'flagged' ? statusBadge('flagged') : '' ?>
        </a>
      <?php endforeach; ?>
    </aside>
    <div class="card card-pad chat-main">
      <?php if (!$active): ?>
        <div class="empty">Select a conversation to start messaging.</div>
      <?php else:
        $peer = $me === (int) $active['buyer_id'] ? $active['seller_name'] : $active['buyer_name']; ?>
        <div style="display:flex;gap:10px;align-items:center;border-bottom:1px solid var(--line);padding-bottom:10px;margin-bottom:10px">
          <div><b><?= e($peer) ?></b><div class="muted" style="font-size:12.5px"><?= e((string) ($active['subject'] ?? '')) ?></div></div>
          <div style="margin-left:auto"><?= $connected ? statusBadge('verified') : '<span class="badge warn">Contacts hidden</span>' ?></div>
        </div>
        <?php if ($active['status'] === 'flagged'): ?>
          <div class="alert error">Our safety filter flagged this chat. Never share OTPs, UPI PINs or advance payments outside DRIVE24.</div>
        <?php endif; ?>
        <div class="chat-log" id="chatLog" data-thread="<?= (int) $active['id'] ?>" data-last="<?= $messages ? (int) end($messages)['id'] : 0 ?>">
          <?php foreach ($messages as $m): $mine = (int) $m['sender_id'] === $me; ?>
            <div class="bubble <?= $mine ? 'mine' : '' ?>">
              <div><?= e(maskContact((string) $m['body'], $connected)) ?></div>
              <?php if (!empty($m['image'])): ?><img src="<?= e(base('assets/uploads/' . $m['image'])) ?>" alt="" style="border-radius:8px;margin-top:6px;max-width:240px"><?php endif; ?>
              <small class="muted num"><?= e(date('d M, H:i', strtotime((string) $m['created_at']))) ?><?= (int) $m['flagged'] ? ' · flagged' : '' ?></small>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;margin-top:12px">
          <?= csrfField() ?><input type="hidden" name="thread_id" value="<?= (int) $active['id'] ?>">
          <input class="form-control" name="body" placeholder="Type your message..." autocomplete="off" required style="flex:1">
          <label class="btn btn-outline btn-sm" title="Attach a photo">📷<input type="file" name="photo" accept="image/*" hidden></label>
          <button class="btn btn-primary" type="submit">Send</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
