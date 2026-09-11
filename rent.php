<?php
require_once __DIR__ . '/includes/rentals.php';
require_once __DIR__ . '/includes/layout.php';

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'make' => (string) ($_GET['make'] ?? ''),
    'body' => (string) ($_GET['body'] ?? ''),
    'fuel' => (string) ($_GET['fuel'] ?? ''),
    'transmission' => (string) ($_GET['transmission'] ?? ''),
    'city' => (string) ($_GET['city'] ?? ''),
    'max_ppd' => (string) ($_GET['max_ppd'] ?? ''),
    'seats' => (string) ($_GET['seats'] ?? ''),
    'sort' => (string) ($_GET['sort'] ?? ''),
    'pickup_loc' => trim((string) ($_GET['pickup_loc'] ?? '')),
    'pickup_at' => (string) ($_GET['pickup_at'] ?? ''),
    'return_at' => (string) ($_GET['return_at'] ?? ''),
];
// Normalise datetimes for the availability check.
foreach (['pickup_at', 'return_at'] as $k) {
    $t = $filters[$k] !== '' ? strtotime($filters[$k]) : false;
    $filters[$k] = $t !== false ? date('Y-m-d H:i:s', $t) : '';
}
if ($filters['pickup_at'] !== '' && $filters['return_at'] !== '' && $filters['return_at'] <= $filters['pickup_at']) {
    $filters['return_at'] = '';
}
$perPage = 9;
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = searchRentals($filters, $perPage, ($page - 1) * $perPage);
$total = $result['total'];
$pages = max(1, (int) ceil($total / $perPage));
$queryString = http_build_query(array_filter($filters, static fn($v) => $v !== ''));
$cities = filterOptions('city');

renderHeader('Rent self-drive cars', 'rent');
?>
<div class="wrap section">
  <h1 style="font-size:1.7rem">Self-drive rentals <span class="muted num" style="font-size:1rem">(<?= $total ?> cars<?= $filters['pickup_at'] !== '' && $filters['return_at'] !== '' ? ' free for your dates' : '' ?>)</span></h1>
  <form method="get" class="card card-pad rent-bar">
    <div>
      <label class="form-label">Pickup location</label>
      <input class="form-control" name="pickup_loc" list="rentCities" placeholder="City, airport, hub..." value="<?= e($filters['pickup_loc']) ?>">
      <datalist id="rentCities"><?php foreach ($cities as $c): ?><option><?= e((string) $c) ?></option><?php endforeach; ?></datalist>
    </div>
    <div><label class="form-label">Pickup date &amp; time</label><input class="form-control" type="datetime-local" name="pickup_at" value="<?= $filters['pickup_at'] !== '' ? e(date('Y-m-d\TH:i', strtotime($filters['pickup_at']))) : '' ?>"></div>
    <div><label class="form-label">Return date &amp; time</label><input class="form-control" type="datetime-local" name="return_at" value="<?= $filters['return_at'] !== '' ? e(date('Y-m-d\TH:i', strtotime($filters['return_at']))) : '' ?>"></div>
    <div style="display:flex;align-items:flex-end"><button class="btn btn-primary btn-block" type="submit">Check availability</button></div>
  </form>
  <div class="split" style="margin-top:18px">
    <aside class="card card-pad sticky">
      <form method="get">
        <?php foreach (['pickup_loc' => $filters['pickup_loc'], 'pickup_at' => $filters['pickup_at'], 'return_at' => $filters['return_at']] as $k => $v): ?>
          <?php if ($v !== ''): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
        <?php endforeach; ?>
        <h3 style="font-size:1rem">Filters</h3>
        <div style="margin-bottom:10px"><label class="form-label">Keyword</label><input class="form-control" name="q" value="<?= e($filters['q']) ?>"></div>
        <?php
        $selects = [
            'make' => ['Brand', filterOptions('make')],
            'body' => ['Body type', filterOptions('body_type')],
            'fuel' => ['Fuel', filterOptions('fuel_type')],
            'transmission' => ['Transmission', filterOptions('transmission')],
            'city' => ['City', $cities],
        ];
        foreach ($selects as $key => [$label, $options]): ?>
          <div style="margin-bottom:10px">
            <label class="form-label"><?= e($label) ?></label>
            <select class="form-select" name="<?= e($key) ?>">
              <option value="">Any</option>
              <?php foreach ($options as $opt): ?>
                <option <?= $filters[$key] === (string) $opt ? 'selected' : '' ?>><?= e((string) $opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
        <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
          <div><label class="form-label">Max &#8377;/day</label><input class="form-control num" type="number" name="max_ppd" value="<?= e($filters['max_ppd']) ?>"></div>
          <div><label class="form-label">Min seats</label><input class="form-control num" type="number" name="seats" value="<?= e($filters['seats']) ?>"></div>
        </div>
        <button class="btn btn-primary btn-block" style="margin-top:12px" type="submit">Apply filters</button>
        <a class="btn btn-ghost btn-block btn-sm" href="<?= e(base('rent.php')) ?>">Reset</a>
      </form>
    </aside>

    <div>
      <form method="get" class="card card-pad" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php foreach ($filters as $k => $v): if ($k === 'sort' || $v === '') { continue; } ?>
          <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
        <?php endforeach; ?>
        <span class="muted">Sort by</span>
        <select class="form-select" name="sort" style="max-width:230px" data-autosubmit>
          <?php foreach (['' => 'Recommended', 'price_asc' => 'Price/day: low to high', 'price_desc' => 'Price/day: high to low', 'rating' => 'Highest rated host'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $filters['sort'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if (!$result['rows']): ?>
        <div class="card card-pad empty" style="margin-top:16px">No rental cars match. Try different dates or widen your filters.</div>
      <?php endif; ?>
      <div class="grid cars" style="margin-top:16px"><?php foreach ($result['rows'] as $r) { rentalCard($r); } ?></div>

      <?php if ($pages > 1): ?>
        <nav class="pagination">
          <?php for ($i = 1; $i <= $pages; $i++): $qs = $queryString . ($queryString ? '&' : '') . 'page=' . $i; ?>
            <?php if ($i === $page): ?><span class="on num"><?= $i ?></span>
            <?php else: ?><a class="num" href="<?= e(base('rent.php?' . $qs)) ?>"><?= $i ?></a><?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
