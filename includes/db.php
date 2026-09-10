<?php
declare(strict_types=1);

function config(?string $group = null): array
{
    static $config = null;
    if ($config === null) { $config = require __DIR__ . '/../config/config.php'; }
    return $group !== null ? ($config[$group] ?? []) : $config;
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
    return $ready;
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
