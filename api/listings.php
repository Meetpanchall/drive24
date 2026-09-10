<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/listings.php';
header('Content-Type: application/json');

// Public read-only JSON feed of the MySQL inventory: api/listings.php?make=Kia&max=1500000
$filters = [
    'q' => (string) ($_GET['q'] ?? ''), 'make' => (string) ($_GET['make'] ?? ''),
    'body' => (string) ($_GET['body'] ?? ''), 'fuel' => (string) ($_GET['fuel'] ?? ''),
    'transmission' => (string) ($_GET['transmission'] ?? ''), 'city' => (string) ($_GET['city'] ?? ''),
    'min' => (string) ($_GET['min'] ?? ''), 'max' => (string) ($_GET['max'] ?? ''),
    'sort' => (string) ($_GET['sort'] ?? ''),
];
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 12)));
$result = searchListings($filters, $limit, max(0, (int) ($_GET['offset'] ?? 0)));

echo json_encode([
    'ok' => dbReady(),
    'total' => $result['total'],
    'items' => array_map(static fn(array $r) => [
        'id' => (int) $r['id'],
        'title' => vehicleTitle($r),
        'price' => (float) $r['price'],
        'km_driven' => (int) $r['km_driven'],
        'fuel' => $r['fuel_type'],
        'transmission' => $r['transmission'],
        'city' => $r['city'],
        'inspection_score' => (int) $r['inspection_score'],
        'url' => base('car.php?id=' . (int) $r['id']),
    ], $result['rows']),
], JSON_PRETTY_PRINT);
