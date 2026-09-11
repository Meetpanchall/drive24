<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $_SESSION['compare'] = [];
    flash('success', 'Comparison cleared.');
    redirect(base('compare.php'));
}

$ids = compareIds();
$cars = [];
if ($ids && dbReady()) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $cars = fetchAll(LISTING_SELECT . ' WHERE l.id IN (' . $in . ')', $ids);
}
$rows = [
    'Price' => static fn(array $c) => rupees($c['price']),
    'EMI (60 months)' => static fn(array $c) => rupees(emiAmount((float) $c['price'] * 0.8, 9.5, 60)) . '/mo',
    'Year' => static fn(array $c) => (string) $c['year'],
    'KM driven' => static fn(array $c) => number_format((int) $c['km_driven']) . ' km',
    'Fuel' => static fn(array $c) => (string) $c['fuel_type'],
    'Transmission' => static fn(array $c) => (string) $c['transmission'],
    'Body type' => static fn(array $c) => (string) $c['body_type'],
    'Engine' => static fn(array $c) => (int) $c['engine_cc'] . ' cc',
    'Power' => static fn(array $c) => (string) $c['power_bhp'],
    'Mileage' => static fn(array $c) => $c['mileage_kmpl'] . ' kmpl',
    'Owners' => static fn(array $c) => (string) $c['owners'],
    'Inspection score' => static fn(array $c) => (int) $c['inspection_score'] . '/100',
    'City' => static fn(array $c) => (string) $c['city'],
    'Seller' => static fn(array $c) => (string) ($c['seller_company'] ?: $c['seller_name']),
];

renderHeader('Compare cars', 'compare');
?>
<div class="wrap section">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <h1 style="font-size:1.6rem">Compare cars</h1>
    <?php if ($cars): ?>
      <form method="post" style="margin-left:auto"><?= csrfField() ?><button class="btn btn-outline btn-sm">Clear all</button></form>
    <?php endif; ?>
  </div>
  <?php if (!$cars): ?>
    <div class="card card-pad empty">Add up to 4 cars from any listing to compare them side by side. <a href="<?= e(base('cars.php')) ?>">Browse cars</a></div>
  <?php else: ?>
    <div class="table-wrap sticky-first" style="margin-top:16px">
      <table class="data">
        <thead>
          <tr><th style="min-width:150px">Specification</th>
            <?php foreach ($cars as $c): ?>
              <th style="min-width:220px">
                <img src="<?= e(listingImage($c)) ?>" alt="" style="width:100%;border-radius:10px;margin-bottom:6px">
                <a href="<?= e(base('car.php?id=' . (int) $c['id'])) ?>"><?= e(vehicleTitle($c)) ?></a>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $label => $fn): ?>
          <tr><td><b><?= e($label) ?></b></td>
            <?php foreach ($cars as $c): ?><td class="num"><?= e((string) $fn($c)) ?></td><?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
          <tr><td></td>
            <?php foreach ($cars as $c): ?>
              <td><a class="btn btn-primary btn-sm" href="<?= e(base('checkout.php?listing=' . (int) $c['id'])) ?>">Book now</a>
                <button class="btn btn-ghost btn-sm" type="button" data-compare="<?= (int) $c['id'] ?>">Remove</button></td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
