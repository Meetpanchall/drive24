<?php
declare(strict_types=1);

require_once __DIR__ . '/listings.php';

/**
 * Self-drive rental module: availability, quotes, KYC gate, handover,
 * return inspection, charges and deposit settlement.
 */

const RENTAL_TAX_PCT = 18.0;        // GST on (rental - discount)
const RENTAL_WEEKLY_OFF_PCT = 10.0; // discount on 7+ day trips
const RENTAL_FUEL_PER_PCT = 60.0;   // Rs per 1% fuel shortfall at return

function rentalPolicies(): array
{
    return [
        'Valid original driving licence required at pickup (no licence, no handover).',
        'Fuel policy: return at the same level - shortfall is billed from the deposit.',
        'Speed limit 120 km/h; smoking, pets and off-roading are not allowed.',
        'Late return is billed at 1.5x the hourly rate from the deposit.',
        'Accidents/damage must be reported immediately on the trip screen (SOS).',
        'Deposit refund is initiated within 24 hours of a clean return.',
    ];
}

function rentalStatusLabel(string $status): string
{
    return [
        'pending' => 'Payment pending', 'confirmed' => 'Booking confirmed', 'active' => 'Trip active',
        'returned' => 'Car returned', 'settled' => 'Settled & closed', 'cancelled' => 'Cancelled',
    ][$status] ?? ucfirst($status);
}

/** A listing bookable for rent, or null. */
function findRentalCar(int $id): ?array
{
    $car = findListing($id);
    if (!$car || ($car['status'] ?? '') !== 'approved') { return null; }
    if ((int) ($car['rental_enabled'] ?? 0) !== 1 || (float) ($car['price_per_day'] ?? 0) <= 0) { return null; }
    return $car;
}

/** True when no live rental overlaps [pickup, return) for this listing. */
function rentalAvailable(int $listingId, string $pickupAt, string $returnAt, int $excludeId = 0): bool
{
    if (!dbReady()) { return false; }
    $n = (int) fetchValue(
        "SELECT COUNT(*) FROM rentals WHERE listing_id = ? AND id <> ? AND status IN ('confirmed','active','returned') AND pickup_at < ? AND return_at > ?",
        [$listingId, $excludeId, $returnAt, $pickupAt], 0
    );
    return $n === 0;
}

/** Price breakup for a trip. Returns days, base, discount, tax, deposit, total (+payable). */
function rentalTaxPct(): float { return (float) setting('rental_tax_pct', RENTAL_TAX_PCT); }
function rentalWeeklyOff(): float { return (float) setting('rental_weekly_off', RENTAL_WEEKLY_OFF_PCT); }
function rentalFuelRate(): float { return (float) setting('rental_fuel_per_pct', RENTAL_FUEL_PER_PCT); }

function rentalQuote(array $car, string $pickupAt, string $returnAt): array
{
    $ppd = (float) ($car['price_per_day'] ?? 0);
    $hours = max(1, (int) ceil((strtotime($returnAt) - strtotime($pickupAt)) / 3600));
    $days = max(1, (int) ceil($hours / 24));
    $base = round($days * $ppd, 2);
    $discount = $days >= 7 ? round($base * rentalWeeklyOff() / 100, 2) : 0.0;
    $tax = round(($base - $discount) * rentalTaxPct() / 100, 2);
    $deposit = (float) ($car['security_deposit'] ?? 0);
    return [
        'days' => $days, 'hours' => $hours, 'price_per_day' => $ppd,
        'base' => $base, 'discount' => $discount, 'tax' => $tax,
        'deposit' => $deposit, 'total' => round($base - $discount + $tax + $deposit, 2),
    ];
}

/** Driving-licence KYC state for the rental gate. */
function rentalKyc(int $userId): array
{
    $dl = dbReady() ? fetchOne(
        "SELECT * FROM documents WHERE user_id = ? AND doc_type = 'kyc' AND doc_name LIKE 'Driving Licence%' ORDER BY (status = 'verified') DESC, id DESC",
        [$userId]
    ) : null;
    $userKyc = dbReady() ? (string) fetchValue('SELECT kyc_status FROM users WHERE id = ?', [$userId], '') : '';
    $verified = ($dl && ($dl['status'] ?? '') === 'verified') || $userKyc === 'verified';
    return ['doc' => $dl, 'verified' => $verified, 'has' => $dl !== null];
}

/** Create a payment-pending rental. Caller must have validated dates + availability. */
function createRental(int $listingId, int $userId, string $pickupLoc, string $returnLoc, string $pickupAt, string $returnAt, array $quote): int
{
    return insert('rentals', [
        'booking_no' => 'RNT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3))),
        'listing_id' => $listingId, 'user_id' => $userId,
        'pickup_location' => mb_substr($pickupLoc, 0, 160), 'return_location' => mb_substr($returnLoc, 0, 160),
        'pickup_at' => $pickupAt, 'return_at' => $returnAt,
        'days' => $quote['days'], 'price_per_day' => $quote['price_per_day'],
        'rental_amount' => $quote['base'], 'discount' => $quote['discount'],
        'tax' => $quote['tax'], 'deposit' => $quote['deposit'],
        'total_charged' => $quote['total'], 'status' => 'pending',
        'pickup_otp' => (string) random_int(100000, 999999),
    ]);
}

function findRental(int $id, ?int $userId = null): ?array
{
    if (!dbReady()) { return null; }
    $sql = 'SELECT r.*, v.make, v.model, v.variant, v.year, v.image, v.seats, v.fuel_type, v.transmission, v.city,
            l.seller_id, l.km_limit_day, l.extra_km_rate
        FROM rentals r JOIN listings l ON l.id = r.listing_id JOIN vehicles v ON v.id = l.vehicle_id WHERE r.id = ?';
    $params = [$id];
    if ($userId !== null) { $sql .= ' AND r.user_id = ?'; $params[] = $userId; }
    return fetchOne($sql, $params);
}

/** Mark a pending rental confirmed after gateway verification. Idempotent. */
function confirmRentalPayment(int $rentalId, string $rzpPaymentId): void
{
    $r = findRental($rentalId);
    if (!$r) { throw new RuntimeException('Rental not found.'); }
    if (($r['status'] ?? '') !== 'pending') { return; }
    q("UPDATE rentals SET status = 'confirmed', rzp_payment_id = ? WHERE id = ? AND status = 'pending'", [$rzpPaymentId, $rentalId]);
    notify((int) $r['user_id'], 'Rental booking confirmed',
        'Booking ' . $r['booking_no'] . ' - pickup OTP: ' . ($r['pickup_otp'] ?? ''), 'rental.php?id=' . $rentalId);
    notify((int) $r['seller_id'], 'New rental booking',
        $r['booking_no'] . ' - ' . vehicleTitle($r), 'seller/orders.php');
}

/** Extra-KM math. Returns [drivenKm, includedKm, extraKm, amount]. */
function rentalKmMath(array $rental): array
{
    $driven = max(0, (int) ($rental['return_odo'] ?? 0) - (int) ($rental['pickup_odo'] ?? 0));
    $included = (int) ($rental['days'] ?? 1) * (int) ($rental['km_limit_day'] ?? 250);
    $extra = max(0, $driven - $included);
    return [$driven, $included, $extra, round($extra * (float) ($rental['extra_km_rate'] ?? 12), 2)];
}

function rentalChargesTotal(int $rentalId): float
{
    return (float) fetchValue('SELECT COALESCE(SUM(amount),0) FROM rental_charges WHERE rental_id = ?', [$rentalId], 0);
}

/** Average seller rating (for the rating sort + cards). */
function sellerRating(int $sellerId): array
{
    if (!dbReady()) { return ['avg' => 0.0, 'count' => 0]; }
    $row = fetchOne("SELECT COUNT(*) c, COALESCE(AVG(rating),0) a FROM reviews WHERE target_user_id = ? AND status = 'approved'", [$sellerId]);
    return ['avg' => round((float) ($row['a'] ?? 0), 1), 'count' => (int) ($row['c'] ?? 0)];
}

/** Filtered rental inventory search. $f supports q/make/body/fuel/transmission/city/max_ppd/seats/sort + pickup_at/return_at. */
function searchRentals(array $f, int $limit = 9, int $offset = 0): array
{
    if (!dbReady()) { return ['rows' => [], 'total' => 0]; }
    $where = ["l.status = 'approved'", 'l.rental_enabled = 1', 'l.price_per_day IS NOT NULL'];
    $params = [];
    if (!empty($f['q'])) {
        $where[] = '(v.make LIKE ? OR v.model LIKE ? OR v.variant LIKE ? OR v.city LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like);
    }
    foreach (['make' => 'v.make', 'body' => 'v.body_type', 'fuel' => 'v.fuel_type', 'transmission' => 'v.transmission', 'city' => 'v.city'] as $key => $col) {
        if (!empty($f[$key])) { $where[] = $col . ' = ?'; $params[] = $f[$key]; }
    }
    if (!empty($f['max_ppd'])) { $where[] = 'l.price_per_day <= ?'; $params[] = (float) $f['max_ppd']; }
    if (!empty($f['seats'])) { $where[] = 'v.seats >= ?'; $params[] = (int) $f['seats']; }
    // Date-based availability: hide cars already booked over the requested window.
    if (!empty($f['pickup_at']) && !empty($f['return_at'])) {
        $where[] = "NOT EXISTS (SELECT 1 FROM rentals r WHERE r.listing_id = l.id AND r.status IN ('confirmed','active','returned') AND r.pickup_at < ? AND r.return_at > ?)";
        array_push($params, $f['return_at'], $f['pickup_at']);
    }
    $sorts = [
        'price_asc' => 'l.price_per_day ASC', 'price_desc' => 'l.price_per_day DESC',
        'rating' => 'rcount DESC, ravg DESC', '' => 'l.featured DESC, l.price_per_day ASC',
    ];
    $order = $sorts[$f['sort'] ?? ''] ?? $sorts[''];
    $clause = ' WHERE ' . implode(' AND ', $where);
    $from = 'FROM listings l JOIN vehicles v ON v.id = l.vehicle_id JOIN users u ON u.id = l.seller_id';
    $total = (int) fetchValue('SELECT COUNT(*) ' . $from . $clause, $params);
    $rows = fetchAll(
        '[STRING_PLACEHOLDER_1]' .
        '(SELECT COUNT(*) FROM reviews rv WHERE rv.target_user_id = l.seller_id AND rv.status = ' . "'approved'" . ') AS rcount, ' .
        '(SELECT COALESCE(AVG(rv.rating),0) FROM reviews rv WHERE rv.target_user_id = l.seller_id AND rv.status = ' . "'approved'" . ') AS ravg ' .
        $from . $clause . ' ORDER BY ' . $order . ' LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) $offset),
        $params
    );
    return ['rows' => $rows, 'total' => $total];
}

/** Card for rental search results. */
function rentalCard(array $r): void
{
    $rating = ((float) ($r['ravg'] ?? 0) > 0)
        ? '&#9733; ' . number_format((float) $r['ravg'], 1) . ' (' . (int) ($r['rcount'] ?? 0) . ')'
        : 'New host';
    ?>
    <article class="car-card rent-card">
      <div class="thumb">
        <a href="<?= e(base('rent-car.php?id=' . (int) $r['id'])) ?>"><img src="<?= e(listingImage($r)) ?>" alt="<?= e(vehicleTitle($r)) ?>" loading="lazy"></a>
        <span class="rent-ppd num"><?= rupees($r['price_per_day']) ?>/day</span>
      </div>
      <div class="body">
        <h3><a href="<?= e(base('rent-car.php?id=' . (int) $r['id'])) ?>"><?= e(vehicleTitle($r)) ?></a></h3>
        <div class="meta"><span><?= e((string) ($r['fuel_type'] ?? '')) ?></span><span><?= e((string) ($r['transmission'] ?? '')) ?></span><span><?= (int) ($r['seats'] ?? 5) ?> seats</span><span><?= e((string) ($r['city'] ?? '')) ?></span></div>
        <div class="rent-meta"><span class="t-stars num" style="font-size:13px"><?= $rating ?></span><span class="muted num" style="font-size:12.5px"><?= (int) ($r['km_limit_day'] ?? 250) ?> km/day &middot; <?= rupees($r['security_deposit']) ?> deposit</span></div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <a class="btn btn-primary btn-sm" style="flex:1" href="<?= e(base('rent-car.php?id=' . (int) $r['id'])) ?>">Book now</a>
          <a class="btn btn-outline btn-sm" style="flex:1" href="<?= e(base('rent-car.php?id=' . (int) $r['id'] . '#viewer')) ?>">360&deg; view</a>
        </div>
      </div>
    </article>
    <?php
}
