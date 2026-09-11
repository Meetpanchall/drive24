<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';

// GET  /api/reviews.php?listing=1        -> approved reviews + average
// POST /api/reviews.php {listing_id, rating, comment, title}
if (!dbReady()) { apiJson(['ok' => false, 'message' => 'Database unavailable.'], 503); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $lid = (int) ($_GET['listing'] ?? 0);
    if ($lid <= 0) { apiJson(['ok' => false, 'message' => 'listing id required.'], 422); }
    $rows = fetchAll("SELECT r.rating, r.title, r.comment, r.created_at, u.name AS author FROM reviews r
        JOIN users u ON u.id = r.author_id WHERE r.listing_id = ? AND r.status = 'approved' ORDER BY r.id DESC", [$lid]);
    apiJson(['ok' => true, 'average' => listingRating($lid), 'reviews' => $rows]);
}

$u = apiUser();
if ($u === null) { apiJson(['ok' => false, 'message' => 'Sign in required.'], 401); }
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
verifyApiCsrf(is_array($in) ? $in : null);
$car = findListing((int) ($in['listing_id'] ?? 0));
$rating = (int) ($in['rating'] ?? 0);
if (!$car || $rating < 1 || $rating > 5) { apiJson(['ok' => false, 'message' => 'Valid listing_id and rating 1-5 required.'], 422); }
$bought = (int) fetchValue("SELECT COUNT(*) FROM orders o WHERE o.listing_id = ? AND o.buyer_id = ? AND o.status NOT IN ('cancelled','returned')",
    [(int) $car['id'], $u['id']], 0);
if ($bought === 0 && !in_array($u['role'], ['admin'], true)) {
    apiJson(['ok' => false, 'message' => 'Only verified buyers can review this car.'], 403);
}
$rid = insert('reviews', ['listing_id' => (int) $car['id'], 'author_id' => $u['id'],
    'target_user_id' => (int) $car['seller_id'], 'reviewer_role' => 'buyer', 'rating' => $rating,
    'title' => mb_substr(trim((string) ($in['title'] ?? '')), 0, 160) ?: null,
    'comment' => mb_substr(trim((string) ($in['comment'] ?? '')), 0, 2000) ?: null, 'status' => 'pending']);
apiJson(['ok' => true, 'review_id' => $rid, 'status' => 'pending'], 201);
