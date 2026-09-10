<?php
// Dealer bulk upload (SRS: dealers can bulk-upload via the dealers' portal).
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

$imported = [];
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $file = $_FILES['csv'] ?? null;
    if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        flash('error', 'Please choose a CSV file to upload.');
        redirect(base('seller/bulk-upload.php'));
    }
    $rows = array_map('str_getcsv', file((string) $file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $header = array_map(static fn($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
    $need = ['make', 'model', 'year', 'price'];
    $missing = array_diff($need, $header);
    if ($missing !== []) {
        flash('error', 'CSV must include columns: ' . implode(', ', $need) . '.');
        redirect(base('seller/bulk-upload.php'));
    }
    foreach (array_slice($rows, 1, 200) as $line => $cols) {
        $r = array_combine($header, array_pad($cols, count($header), ''));
        try {
            $vid = insert('vehicles', [
                'make' => trim((string) $r['make']), 'model' => trim((string) $r['model']),
                'variant' => trim((string) ($r['variant'] ?? '')), 'year' => (int) $r['year'],
                'body_type' => trim((string) ($r['body_type'] ?? 'SUV')),
                'fuel_type' => in_array($r['fuel_type'] ?? '', ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'], true) ? $r['fuel_type'] : 'Petrol',
                'transmission' => ($r['transmission'] ?? '') === 'Automatic' ? 'Automatic' : 'Manual',
                'km_driven' => (int) ($r['km_driven'] ?? 0), 'owners' => (int) ($r['owners'] ?? 1),
                'color' => trim((string) ($r['color'] ?? '')), 'reg_number' => trim((string) ($r['reg_number'] ?? '')),
                'vin' => trim((string) ($r['vin'] ?? '')), 'city' => trim((string) ($r['city'] ?? $u['city'] ?? '')),
                'image' => 'car' . random_int(1, 6) . '.svg',
                'description' => mb_substr(trim((string) ($r['description'] ?? 'Bulk-uploaded by dealer.')), 0, 2000)]);
            $lid = insert('listings', ['vehicle_id' => $vid, 'seller_id' => $u['id'],
                'price' => (float) $r['price'], 'original_price' => (float) $r['price'],
                'status' => 'pending', 'certified' => 0, 'inspection_score' => 0]);
            $imported[] = $lid;
        } catch (Throwable $ex) {
            $errors[] = 'Row ' . ($line + 2) . ': ' . $ex->getMessage();
        }
    }
    logActivity((int) $u['id'], 'listing.bulk', count($imported) . ' imported');
    flash('success', count($imported) . ' listing(s) imported as pending. ' . count($errors) . ' error(s).');
}

adminHeader('Bulk upload (dealers)', 'listings', 'seller');
?>
<div class="card card-pad">
  <h2 style="font-size:1.1rem">Upload a CSV of your stock</h2>
  <p class="muted">Required columns: <code>make, model, year, price</code>. Optional: <code>variant, body_type, fuel_type, transmission, km_driven, owners, color, reg_number, vin, city, description</code>. Every row becomes a <b>pending</b> listing for admin approval.</p>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap">
    <?= csrfField() ?>
    <input class="form-control" style="max-width:320px" type="file" name="csv" accept=".csv" required>
    <button class="btn btn-primary" type="submit">Import CSV</button>
    <a class="btn btn-outline" href="data:text/csv,make%2Cmodel%2Cyear%2Cprice%2Ckm_driven%2Cfuel_type%2Ctransmission%2Ccity%0AHyundai%2CCreta%2C2021%2C1150000%2C32000%2CPetrol%2CAutomatic%2CMumbai" download="drive24-template.csv">Download template</a>
  </form>
  <?php if ($imported): ?><div class="alert success">Imported listing IDs: <?= e(implode(', ', $imported)) ?></div><?php endif; ?>
  <?php foreach (array_slice($errors, 0, 10) as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
</div>
<?php adminFooter(); ?>
