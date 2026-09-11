<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
$listingId = (int) ($payload['listing_id'] ?? 0);
verifyApiCsrf($payload);
$list = compareIds();

if ($listingId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid car.']);
    exit;
}
if (in_array($listingId, $list, true)) {
    $list = array_values(array_diff($list, [$listingId]));
    $added = false;
    $message = 'Removed from comparison.';
} elseif (count($list) >= 4) {
    echo json_encode(['ok' => false, 'message' => 'You can compare up to 4 cars.']);
    exit;
} else {
    $list[] = $listingId;
    $added = true;
    $message = 'Added to comparison.';
}
$_SESSION['compare'] = $list;

echo json_encode(['ok' => true, 'added' => $added, 'count' => count($list), 'message' => $message]);
