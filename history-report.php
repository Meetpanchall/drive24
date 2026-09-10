<?php
// Vehicle history report (SRS: accident/salvage/insurance records via data API).
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

$car = findListing((int) ($_GET['listing'] ?? 0));
if ($car === null) { flash('error', 'Car not found.'); redirect(base('cars.php')); }
$h = fetchOne('SELECT * FROM vehicle_history WHERE vehicle_id = ?', [(int) $car['vehicle_id']]);
if (!$h) {
    $h = ['accidents' => 0, 'accident_details' => 'Check pending', 'insurance_claims' => 0, 'challans' => 0,
        'challan_amount' => 0, 'service_records' => 0, 'flood_damage' => 0, 'theft_record' => 0,
        'owners_history' => ((int) $car['owners']) . ' owner(s) as per RC', 'report_summary' => 'History check pending.',
        'checked_on' => date('Y-m-d')];
}
$clean = ((int) $h['accidents'] === 0 && !(int) $h['flood_damage'] && !(int) $h['theft_record']);

renderHeader('History report', 'cars');
?>
<div class="wrap section" style="max-width:860px">
  <p class="muted" style="font-size:13px"><a href="<?= e(base('car.php?id=' . (int) $car['id'])) ?>">&larr; Back to car</a></p>
  <h1 style="font-size:1.6rem">Vehicle history report</h1>
  <p class="muted"><?= e(vehicleTitle($car)) ?> &middot; <?= e((string) $car['reg_number']) ?> &middot; checked <?= e(date('d M Y', strtotime((string) $h['checked_on']))) ?></p>
  <div class="alert <?= $clean ? 'success' : 'error' ?>"><?= e((string) $h['report_summary']) ?></div>
  <div class="card card-pad">
    <div class="kv"><span>Accidents on record</span><b class="num"><?= (int) $h['accidents'] ?></b></div>
    <div class="kv"><span>Accident detail</span><span><?= e((string) $h['accident_details']) ?></span></div>
    <div class="kv"><span>Insurance claims</span><b class="num"><?= (int) $h['insurance_claims'] ?></b></div>
    <div class="kv"><span>Traffic challans</span><b class="num"><?= (int) $h['challans'] ?> (<?= rupees($h['challan_amount']) ?> settled)</b></div>
    <div class="kv"><span>Service records</span><b class="num"><?= (int) $h['service_records'] ?> entries</b></div>
    <div class="kv"><span>Flood damage</span><span><?= ((int) $h['flood_damage'] ? statusBadge('rejected') : statusBadge('verified')) ?></span></div>
    <div class="kv"><span>Theft record</span><span><?= ((int) $h['theft_record'] ? statusBadge('rejected') : statusBadge('verified')) ?></span></div>
    <div class="kv"><span>Ownership trail</span><span><?= e((string) $h['owners_history']) ?></span></div>
  </div>
  <p class="muted" style="font-size:12.5px;margin-top:10px">Compiled from RTO records, insurer claim exchanges and service data. Demo dataset - integrate Parivahan / carVertical APIs in production.</p>
</div>
<?php renderFooter(); ?>
