<?php
// Printable / downloadable 280-point inspection report (SRS: downloadable PDF link).
require_once __DIR__ . '/includes/listings.php';

$car = findListing((int) ($_GET['listing'] ?? 0));
if ($car === null) { http_response_code(404); exit('Car not found.'); }
$rep = fetchOne('SELECT * FROM inspections WHERE vehicle_id = ? ORDER BY id DESC', [(int) $car['vehicle_id']]);
if (!$rep) { http_response_code(404); exit('No inspection report for this car yet.'); }
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Inspection report - <?= e(vehicleTitle($car)) ?></title>
<style>
body{font-family:Inter,Arial,sans-serif;color:#0f172a;max-width:760px;margin:24px auto;padding:0 16px}
h1{font-size:22px}p.mut{color:#64748b}table{width:100%;border-collapse:collapse;margin:14px 0}
td,th{border:1px solid #e2e8f0;padding:8px 10px;text-align:left;font-size:14px}th{background:#f8fafc}
.bar{height:10px;border-radius:99px;background:#eef2ff}.bar i{display:block;height:10px;border-radius:99px;background:#004ac6}
@media print{.noprint{display:none}}
</style></head><body>
<p class="noprint"><a href="<?= e(base('car.php?id=' . (int) $car['id'])) ?>">&larr; Back to car</a> &middot; <a href="#" onclick="window.print();return false">Print / save as PDF</a></p>
<h1>DRIVE24 280-point inspection report</h1>
<p class="mut"><b><?= e(vehicleTitle($car)) ?></b> &middot; <?= e((string) $car['reg_number']) ?> &middot; VIN <b><?= e((string) $car['vin']) ?></b><br>
Inspected by <?= e((string) $rep['inspector']) ?> on <?= e(date('d M Y', strtotime((string) $rep['inspected_on']))) ?> &middot; Status: <?= e(ucfirst((string) $rep['status'])) ?></p>
<h2>Overall score: <?= (int) $rep['score'] ?>/100</h2>
<div class="bar"><i style="width:<?= (int) $rep['score'] ?>%"></i></div>
<table><thead><tr><th>System</th><th>Score</th></tr></thead><tbody>
<?php foreach (['engine_score' => 'Engine & transmission', 'exterior_score' => 'Exterior & body', 'interior_score' => 'Interior', 'electrical_score' => 'Electricals', 'tyres_score' => 'Tyres & brakes'] as $k => $label): ?>
<tr><td><?= $label ?></td><td><?= (int) $rep[$k] ?>/100</td></tr>
<?php endforeach; ?>
</tbody></table>
<p><b>Accident history:</b> <?= e((string) $rep['accident_history']) ?><br>
<b>Engineer remarks:</b> <?= e((string) $rep['remarks']) ?></p>
<p class="mut">Report <?= e(refCode('RPT', (int) $rep['id'])) ?> &middot; generated <?= e(date('d M Y H:i')) ?> &middot; DRIVE24 Technologies Pvt. Ltd.</p>
</body></html>
