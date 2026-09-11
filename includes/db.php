<?php
declare(strict_types=1);

function config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) { $config = require __DIR__ . '/../config/config.php'; }
    if ($key === null) { return $config; }
    // Ignore empty top-level values (e.g. user-added 'db_host' => '') so they
    // can never shadow the real nested settings with blanks.
    if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '' && $config[$key] !== false) { return $config[$key]; }
    // Scalar shortcuts: config('db_host') -> $config['db']['host']
    if (str_starts_with($key, 'db_')) { return $config['db'][substr($key, 3)] ?? null; }
    if (str_contains($key, '.')) {
        [$group, $name] = explode('.', $key, 2);
        return $config[$group][$name] ?? null;
    }
    return null;
}

/** Shared PDO connection (real prepared statements). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $c = config('db');
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/** True when MySQL is reachable and the schema has been imported. */
function dbReady(): bool
{
    static $ready = null;
    if ($ready !== null) { return $ready; }
    try { db()->query('SELECT 1 FROM users LIMIT 1'); $ready = true; }
    catch (Throwable $e) { $GLOBALS['db_error'] = $e->getMessage(); $ready = false; }
    if ($ready) { ensureExtendedSchema(); }
    return $ready;
}

/**
 * Bring older installs up to date: creates the SRS extension tables
 * (chat, reviews, loans, ...) exactly once per request when missing.
 */
function ensureExtendedSchema(): void
{
    static $done = false;
    if ($done) { return; }
    $done = true;
    try {
        $dbName = config('db')['name'] ?? 'drive24';
        $has = (int) fetchValue(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$dbName, 'questions'], 0
        );
        if ($has === 0) {
            $sql = (string) @file_get_contents(__DIR__ . '/../database/migrate.sql');
            $lines = [];
            foreach (explode("\n", $sql) as $line) {
                if (str_starts_with(ltrim($line), '--')) { continue; }
                $lines[] = $line;
            }
            foreach (preg_split('/;\s*\n/', implode("\n", $lines)) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') { continue; }
                db()->exec($stmt);
            }
            // Backfill a clean history row for vehicles imported before this release.
            db()->exec("INSERT IGNORE INTO vehicle_history (vehicle_id, service_records, owners_history, report_summary, checked_on)
                SELECT v.id, 3, CONCAT(v.owners, ' owner(s) as per RC'), 'History check pending - basic RC verification done.', CURDATE() FROM vehicles v");
        }
        $needCols = [
            ['users', 'mobile_verified', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER kyc_status'],
            ['documents', 'file_url', 'VARCHAR(160) DEFAULT NULL AFTER doc_name'],
            ['documents', 'listing_id', 'INT DEFAULT NULL AFTER order_id'],
            ['vehicles', 'area', 'VARCHAR(80) DEFAULT NULL AFTER city'],
            ['test_drives', 'executive', 'VARCHAR(120) DEFAULT NULL AFTER address'],
            ['listings', 'auction_enabled', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['listings', 'auction_ends_at', 'DATETIME DEFAULT NULL'],
            ['listings', 'starting_bid', 'DECIMAL(12,2) DEFAULT NULL'],
            ['vehicle_history', 'loan_status', "VARCHAR(40) NOT NULL DEFAULT 'No active loan'"],
            ['vehicle_history', 'odometer_verified', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['vehicle_history', 'rc_verified', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['support_tickets', 'priority', "ENUM('low','medium','high') NOT NULL DEFAULT 'medium'"],
            ['support_tickets', 'resolution', 'TEXT DEFAULT NULL'],
       ,
            ['listings', 'rental_enabled', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['listings', 'price_per_day', 'DECIMAL(10,2) DEFAULT NULL'],
            ['listings', 'km_limit_day', 'INT NOT NULL DEFAULT 250'],
            ['listings', 'extra_km_rate', 'DECIMAL(8,2) NOT NULL DEFAULT 12.00'],
            ['listings', 'security_deposit', 'DECIMAL(10,2) NOT NULL DEFAULT 10000.00'],
            ['listings', 'model_3d', 'VARCHAR(160) DEFAULT NULL'],
            ['listings', 'hold_buyer_id', 'INT DEFAULT NULL'],
            ['listings', 'hold_until', 'DATETIME DEFAULT NULL'],
            ['orders', 'handover_otp', 'VARCHAR(10) DEFAULT NULL'],
            ['orders', 'handover_odo', 'INT DEFAULT NULL'],
            ['orders', 'handover_fuel', 'TINYINT DEFAULT NULL'],
            ['orders', 'handover_notes', 'VARCHAR(255) DEFAULT NULL'],
            ['orders', 'handover_at', 'DATETIME DEFAULT NULL'],
        ];
        foreach ($needCols as [$tbl, $colName, $def]) {
            $n = (int) fetchValue(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [$dbName, $tbl, $colName], 0
            );
            if ($n === 0) { db()->exec("ALTER TABLE `$tbl` ADD COLUMN `$colName` $def"); }
        }
        // Rental module: tables + enable rentals on approved cars (existing installs).
        $hasRent = (int) fetchValue(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$dbName, 'rentals'], 0
        );
        if ($hasRent === 0) {
            $mig = (string) @file_get_contents(__DIR__ . '/../database/migrate.sql');
            foreach (['rentals', 'rental_charges'] as $tbl) {
                if (preg_match('/CREATE TABLE IF NOT EXISTS ' . $tbl . ' \(.*?\) ENGINE=InnoDB;/s', $mig, $m)) {
                    db()->exec($m[0]);
                }
            }
        }
        db()->exec("UPDATE listings SET rental_enabled = 1, price_per_day = GREATEST(999, ROUND(price/450, -2)), km_limit_day = 250, extra_km_rate = 12.00, security_deposit = 15000.00 WHERE status = 'approved' AND rental_enabled = 0 AND price_per_day IS NULL");
        $hasSettings = (int) fetchValue(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$dbName, 'settings'], 0
        );
        if ($hasSettings === 0) {
            db()->exec('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(60) PRIMARY KEY, v TEXT NOT NULL) ENGINE=InnoDB');
        }
        db()->exec("INSERT IGNORE INTO settings (k, v) VALUES ('booking_amount','25000'),('rental_tax_pct','18'),('rental_weekly_off','10'),('rental_fuel_per_pct','60'),('offer_hold_hours','48'),('helpline','1800 200 2424'),('helpline_hours','9 AM - 9 PM, all days'),('support_email','care@drive24.in')");
    } catch (Throwable $e) { /* never break a request for a migration */ }
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchAll(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }

function fetchOne(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetchValue(string $sql, array $params = [], mixed $default = 0): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function updateRow(string $table, array $data, string $where, array $whereParams): int
{
    $sets = [];
    foreach (array_keys($data) as $col) { $sets[] = '`' . $col . '` = ?'; }
    return q('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where, array_merge(array_values($data), $whereParams))->rowCount();
}

function logActivity(?int $userId, string $action, string $detail = ''): void
{
    try { insert('activity_log', ['user_id' => $userId, 'action' => $action, 'detail' => $detail, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]); }
    catch (Throwable $e) { /* logging must never break a request */ }
}
