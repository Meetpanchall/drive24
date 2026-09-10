<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';

// GET  /api/chat.php?thread=1&after=12  -> new messages (polling)
// POST /api/chat.php  {thread_id, body} -> send a message
$u = apiUser();
if ($u === null || !dbReady()) { apiJson(['ok' => false, 'message' => 'Sign in required.'], 401); }
$me = (int) $u['id'];

$threadOf = static function (int $tid) use ($me, $u): ?array {
    $t = fetchOne('SELECT * FROM chat_threads WHERE id = ?', [$tid]);
    if (!$t) { return null; }
    if ($me !== (int) $t['buyer_id'] && $me !== (int) $t['seller_id'] && $u['role'] !== 'admin') { return null; }
    return $t;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $thread = $threadOf((int) ($_GET['thread'] ?? 0));
    if (!$thread) { apiJson(['ok' => false, 'message' => 'Thread not found.'], 404); }
    $after = (int) ($_GET['after'] ?? 0);
    $connected = (int) fetchValue('SELECT COUNT(*) FROM orders o JOIN listings l ON l.id = o.listing_id
        WHERE o.buyer_id = ? AND l.seller_id = ?', [(int) $thread['buyer_id'], (int) $thread['seller_id']], 0) > 0;
    $rows = fetchAll('SELECT m.id, m.sender_id, m.body, m.image, m.created_at, u.name AS sender
        FROM chat_messages m JOIN users u ON u.id = m.sender_id
        WHERE m.thread_id = ? AND m.id > ? ORDER BY m.id', [(int) $thread['id'], $after]);
    foreach ($rows as &$r) { $r['body'] = maskContact((string) $r['body'], $connected); }
    apiJson(['ok' => true, 'messages' => $rows]);
}

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
$thread = $threadOf((int) ($payload['thread_id'] ?? 0));
$body = trim((string) ($payload['body'] ?? ''));
if (!$thread || $body === '') { apiJson(['ok' => false, 'message' => 'Thread and message body are required.'], 422); }
$flagged = containsFraud($body) ? 1 : 0;
$mid = insert('chat_messages', ['thread_id' => (int) $thread['id'], 'sender_id' => $me,
    'body' => mb_substr($body, 0, 2000), 'flagged' => $flagged]);
if ($flagged) { q("UPDATE chat_threads SET status = 'flagged' WHERE id = ?", [(int) $thread['id']]); }
$other = $me === (int) $thread['buyer_id'] ? (int) $thread['seller_id'] : (int) $thread['buyer_id'];
notify($other, 'New message', mb_substr($body, 0, 90), 'chat.php?thread=' . (int) $thread['id']);
apiJson(['ok' => true, 'message_id' => $mid, 'flagged' => (bool) $flagged], 201);
