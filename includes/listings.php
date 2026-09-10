<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

const LISTING_SELECT = 'SELECT l.id, l.price, l.original_price, l.status, l.featured, l.certified,
        l.inspection_score, l.views, l.seller_id, l.created_at,
        v.id AS vehicle_id, v.make, v.model, v.variant, v.year, v.body_type, v.fuel_type, v.transmission,
        v.km_driven, v.owners, v.color, v.reg_number, v.reg_state, v.vin, v.engine_cc, v.power_bhp,
        v.mileage_kmpl, v.seats, v.insurance_valid_till, v.city, v.image, v.description,
        u.name AS seller_name, u.company AS seller_company, u.city AS seller_city
    FROM listings l
    JOIN vehicles v ON v.id = l.vehicle_id
    JOIN users u ON u.id = l.seller_id';

/** Filtered inventory search straight from MySQL. */
function searchListings(array $f, int $limit = 12, int $offset = 0): array
{
    if (!dbReady()) { return ['rows' => [], 'total' => 0]; }
    $where = ["l.status = 'approved'"];
    $params = [];

    if (!empty($f['q'])) {
        $where[] = '(v.make LIKE ? OR v.model LIKE ? OR v.variant LIKE ? OR v.city LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like);
    }
    foreach (['make' => 'v.make', 'body' => 'v.body_type', 'fuel' => 'v.fuel_type', 'transmission' => 'v.transmission', 'city' => 'v.city'] as $key => $col) {
        if (!empty($f[$key])) { $where[] = $col . ' = ?'; $params[] = $f[$key]; }
    }
    if (!empty($f['min'])) { $where[] = 'l.price >= ?'; $params[] = (float) $f['min']; }
    if (!empty($f['max'])) { $where[] = 'l.price <= ?'; $params[] = (float) $f['max']; }
    if (!empty($f['year_from'])) { $where[] = 'v.year >= ?'; $params[] = (int) $f['year_from']; }
    if (!empty($f['km_max'])) { $where[] = 'v.km_driven <= ?'; $params[] = (int) $f['km_max']; }
    if (!empty($f['owners'])) { $where[] = 'v.owners <= ?'; $params[] = (int) $f['owners']; }

    $sorts = [
        'price_asc' => 'l.price ASC', 'price_desc' => 'l.price DESC',
        'km' => 'v.km_driven ASC', 'year' => 'v.year DESC',
        'popular' => 'l.views DESC', '' => 'l.featured DESC, l.created_at DESC',
    ];
    $order = $sorts[$f['sort'] ?? ''] ?? $sorts[''];
    $clause = ' WHERE ' . implode(' AND ', $where);

    $total = (int) fetchValue('SELECT COUNT(*) FROM listings l JOIN vehicles v ON v.id = l.vehicle_id' . $clause, $params);
    $rows = fetchAll(LISTING_SELECT . $clause . ' ORDER BY ' . $order . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), $params);

    return ['rows' => $rows, 'total' => $total];
}

function findListing(int $id): ?array
{
    if (!dbReady()) { return null; }
    return fetchOne(LISTING_SELECT . ' WHERE l.id = ?', [$id]);
}

/** Distinct filter values (whitelisted columns only). */
function filterOptions(string $column): array
{
    $allowed = ['make', 'body_type', 'fuel_type', 'transmission', 'city'];
    if (!in_array($column, $allowed, true) || !dbReady()) { return []; }
    $rows = fetchAll("SELECT DISTINCT v.$column AS val FROM listings l JOIN vehicles v ON v.id = l.vehicle_id WHERE l.status = 'approved' AND v.$column <> '' ORDER BY val");
    return array_column($rows, 'val');
}

/** Reusable product card. */
function carCard(array $r, array $wish = [], array $cmp = []): void
{
    $saved = in_array((int) $r['id'], $wish, true);
    $inCompare = in_array((int) $r['id'], $cmp, true);
    ?>
    <article class="car-card">
      <div class="thumb">
        <a href="<?= e(base('car.php?id=' . (int) $r['id'])) ?>"><img src="<?= e(listingThumb($r)) ?>" alt="<?= e(vehicleTitle($r)) ?>" loading="lazy"></a>
        <div class="tag-float">
          <?php if ((int) $r['certified'] === 1): ?><span class="badge ok">Certified</span><?php endif; ?>
          <?php if ((int) $r['featured'] === 1): ?><span class="badge info">Featured</span><?php endif; ?>
        </div>
        <button class="fav <?= $saved ? 'on' : '' ?>" type="button" data-wishlist="<?= (int) $r['id'] ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>" aria-label="Save car">&#10084;</button>
      </div>
      <div class="body">
        <h3><a href="<?= e(base('car.php?id=' . (int) $r['id'])) ?>"><?= e(vehicleTitle($r)) ?></a></h3>
        <div class="meta">
          <span><?= number_format((int) $r['km_driven']) ?> km</span>
          <span><?= e((string) $r['fuel_type']) ?></span>
          <span><?= e((string) $r['transmission']) ?></span>
          <span><?= (int) $r['owners'] ?><?= ((int) $r['owners'] === 1) ? 'st' : 'nd' ?> owner</span>
        </div>
        <div class="price num"><?= rupees($r['price']) ?></div>
        <div class="muted num" style="font-size:12.6px">EMI from <?= rupees(emiAmount((float) $r['price'] * 0.8, 9.5, 60)) ?>/mo &middot; <?= e((string) $r['city']) ?></div>
        <div style="display:flex;gap:8px;margin-top:auto;padding-top:10px">
          <a class="btn btn-primary btn-sm" style="flex:1" href="<?= e(base('car.php?id=' . (int) $r['id'])) ?>">View details</a>
          <button class="btn btn-outline btn-sm <?= $inCompare ? 'active' : '' ?>" type="button" data-compare="<?= (int) $r['id'] ?>">Compare</button>
        </div>
        <?php if ((int) $r['inspection_score'] > 0): ?>
          <div class="muted" style="font-size:12.4px">Inspection score <b class="num"><?= (int) $r['inspection_score'] ?>/100</b></div>
        <?php endif; ?>
      </div>
    </article>
    <?php
}
