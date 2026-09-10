<?php
require_once __DIR__ . '/includes/listings.php';
require_once __DIR__ . '/includes/layout.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'save_search') {
        $u = requireLogin();
        insert('saved_searches', [
            'user_id' => $u['id'],
            'title' => trim((string) ($_POST['title'] ?? 'My search')),
            'query_string' => (string) ($_POST['query_string'] ?? ''),
            'alert_enabled' => 1,
        ]);
        flash('success', 'Search saved. You will get alerts when matching cars go live.');
        redirect(base('cars.php?' . (string) ($_POST['query_string'] ?? '')));
    }
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'make' => (string) ($_GET['make'] ?? ''),
    'body' => (string) ($_GET['body'] ?? ''),
    'fuel' => (string) ($_GET['fuel'] ?? ''),
    'transmission' => (string) ($_GET['transmission'] ?? ''),
    'city' => (string) ($_GET['city'] ?? ''),
    'min' => (string) ($_GET['min'] ?? ''),
    'max' => (string) ($_GET['max'] ?? ''),
    'year_from' => (string) ($_GET['year_from'] ?? ''),
    'km_max' => (string) ($_GET['km_max'] ?? ''),
    'owners' => (string) ($_GET['owners'] ?? ''),
    'seller_id' => (int) ($_GET['seller'] ?? 0),
    'sort' => (string) ($_GET['sort'] ?? ''),
];
$perPage = 9;
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = searchListings($filters, $perPage, ($page - 1) * $perPage);
$total = $result['total'];
$pages = max(1, (int) ceil($total / $perPage));
$wish = wishlistIds();
$cmp = compareIds();
$queryString = http_build_query(array_filter($filters, static fn($v) => $v !== ''));

renderHeader('Buy used cars', 'cars');
?>
<div class="wrap section">
  <h1 style="font-size:1.7rem">Used cars for sale <span class="muted num" style="font-size:1rem">(<?= $total ?> results)</span></h1>
  <div class="split">
    <aside class="card card-pad sticky">
      <form method="get">
        <h3 style="font-size:1rem">Filters</h3>
        <div style="margin-bottom:10px"><label class="form-label">Keyword</label><input class="form-control" name="q" value="<?= e($filters['q']) ?>"></div>
        <?php
        $selects = [
            'make' => ['Brand', filterOptions('make')],
            'body' => ['Body type', filterOptions('body_type')],
            'fuel' => ['Fuel', filterOptions('fuel_type')],
            'transmission' => ['Transmission', filterOptions('transmission')],
            'city' => ['City', filterOptions('city')],
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
          <div><label class="form-label">Min price</label><input class="form-control num" type="number" name="min" value="<?= e($filters['min']) ?>"></div>
          <div><label class="form-label">Max price</label><input class="form-control num" type="number" name="max" value="<?= e($filters['max']) ?>"></div>
          <div><label class="form-label">Year from</label><input class="form-control num" type="number" name="year_from" value="<?= e($filters['year_from']) ?>"></div>
          <div><label class="form-label">Max KM</label><input class="form-control num" type="number" name="km_max" value="<?= e($filters['km_max']) ?>"></div>
        </div>
        <button class="btn btn-primary btn-block" style="margin-top:12px" type="submit">Apply filters</button>
        <a class="btn btn-ghost btn-block btn-sm" href="<?= e(base('cars.php')) ?>">Reset</a>
      </form>
      <form method="post" style="margin-top:12px;border-top:1px solid var(--line);padding-top:12px">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_search">
        <input type="hidden" name="query_string" value="<?= e($queryString) ?>">
        <label class="form-label">Save this search</label>
        <input class="form-control" name="title" value="<?= e(trim(($filters['make'] ?: 'All') . ' ' . ($filters['body'] ?: 'cars') . ' ' . ($filters['city'] ? 'in ' . $filters['city'] : ''))) ?>">
        <button class="btn btn-outline btn-block btn-sm" style="margin-top:8px" type="submit">Save &amp; get alerts</button>
      </form>
    </aside>

    <div>
      <form method="get" class="card card-pad" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php foreach ($filters as $k => $v): if ($k === 'sort' || $v === '') { continue; } ?>
          <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
        <?php endforeach; ?>
        <span class="muted">Sort by</span>
        <select class="form-select" name="sort" style="max-width:220px" data-autosubmit>
          <?php foreach (['' => 'Recommended', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'km' => 'Lowest KM', 'year' => 'Newest year', 'popular' => 'Most viewed'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $filters['sort'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <a class="btn btn-outline btn-sm" style="margin-left:auto" href="<?= e(base('compare.php')) ?>">Compare (<span data-compare-count><?= count($cmp) ?></span>)</a>
      </form>

      <?php if (!$result['rows']): ?>
        <div class="card card-pad empty" style="margin-top:16px">No cars match these filters. Try widening your budget or removing a filter.</div>
      <?php endif; ?>
      <div class="grid cars" style="margin-top:16px"><?php foreach ($result['rows'] as $r) { carCard($r, $wish, $cmp); } ?></div>

      <?php if ($pages > 1): ?>
        <nav class="pagination">
          <?php for ($i = 1; $i <= $pages; $i++): $qs = $queryString . ($queryString ? '&' : '') . 'page=' . $i; ?>
            <?php if ($i === $page): ?><span class="on num"><?= $i ?></span>
            <?php else: ?><a class="num" href="<?= e(base('cars.php?' . $qs)) ?>"><?= $i ?></a><?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
