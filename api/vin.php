<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';

// GET /api/vin.php?vin=MALC281CLNM100231 -> decoded make/year (mock VIN-data API)
$vin = (string) ($_GET['vin'] ?? '');
if ($vin === '') { apiJson(['ok' => false, 'message' => 'Pass ?vin=17-char-code.'], 422); }
$out = vinDecode($vin);
apiJson(['ok' => $out['valid'], 'vin' => $out['vin'], 'make' => $out['make'],
    'country' => $out['country'], 'year' => $out['year'],
    'message' => $out['valid'] ? 'VIN decoded.' : 'Invalid VIN (17 chars, no I/O/Q).']);
