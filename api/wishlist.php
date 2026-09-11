<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
$listingId = (int) ($payload['listing_id'] ?? 0);
$u = user();

if ($u === null) {
    echo json_encode(['ok' => false, 'message' => 'Please sign in to save cars.']);
    exit;
}
verifyApiCsrf($payload);
if ($listingId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid car.']);
    exit;
}

$exists = fetchOne('SELECT id FROM wishlists WHERE user_id = ? AND listing_id = ?', [$u['id'], $listingId]);
if ($exists) {
    q('DELETE FROM wishlists WHERE id = ?', [(int) $exists['id']]);
    $saved = false;
} else {
    insert('wishlists', ['user_id' => $u['id'], 'listing_id' => $listingId]);
    $saved = true;
}
$count = (int) fetchValue('SELECT COUNT(*) FROM wishlists WHERE user_id = ?', [$u['id']]);

echo json_encode([
    'ok' => true,
    'saved' => $saved,
    'count' => $count,
    'message' => $saved ? 'Saved to your wishlist.' : 'Removed from wishlist.',
]);
