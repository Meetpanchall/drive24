<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL helper that works from the site root, /admin and /seller. */
function base(string $path = ''): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (preg_match('#/(admin|seller|api)$#', $dir)) { $dir = rtrim(dirname($dir), '/'); }
    return ($dir === '' ? '' : $dir) . '/' . ltrim($path, '/');
}

/** Indian digit grouping, e.g. RS12,45,000 */
function rupees(float|int|string|null $amount): string
{
    $n = (int) round((float) $amount);
    $sign = $n < 0 ? '-' : '';
    $s = (string) abs($n);
    if (strlen($s) > 3) {
        $last3 = substr($s, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($s, 0, -3));
        $s = $rest . ',' . $last3;
    }
    return $sign . "\u{20B9}" . $s;
}

function money(float|int|string|null $amount): string
{
    $a = (float) $amount;
    if ($a >= 10000000) { return "\u{20B9}" . rtrim(rtrim(number_format($a / 10000000, 2), '0'), '.') . ' Cr'; }
    if ($a >= 100000) { return "\u{20B9}" . rtrim(rtrim(number_format($a / 100000, 2), '0'), '.') . ' Lakh'; }
    return rupees($a);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $sent = $_POST['_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
        flash('error', 'Your session expired, please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? base('index.php'));
    }
}

function flash(?string $type = null, ?string $message = null): array
{
    if ($type !== null && $message !== null) { $_SESSION['flash'][] = ['type' => $type, 'message' => $message]; return []; }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function redirect(string $url): void { header('Location: ' . $url); exit; }

function user(): ?array
{
    static $cached = null;
    if ($cached !== null) { return $cached ?: null; }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id || !dbReady()) { $cached = false; return null; }
    $row = fetchOne('SELECT id, name, email, mobile, role, city, kyc_status FROM users WHERE id = ? AND status = "active"', [$id]);
    $cached = $row ?: false;
    return $cached ?: null;
}

function isRole(string ...$roles): bool
{
    $u = user();
    return $u !== null && in_array($u['role'], $roles, true);
}

function requireLogin(?string $area = null): array
{
    $u = user();
    if ($u === null) {
        flash('error', 'Please sign in to continue.');
        redirect(base('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
    }
    if ($area === 'admin' && $u['role'] !== 'admin') { http_response_code(403); exit('Admin access only.'); }
    if ($area === 'seller' && !in_array($u['role'], ['seller', 'dealer', 'admin'], true)) { http_response_code(403); exit('Seller access only.'); }
    return $u;
}

function wishlistIds(): array
{
    $u = user();
    if ($u === null || !dbReady()) { return array_map('intval', $_SESSION['wishlist'] ?? []); }
    return array_map('intval', array_column(fetchAll('SELECT listing_id FROM wishlists WHERE user_id = ?', [$u['id']]), 'listing_id'));
}

function compareIds(): array
{
    return array_values(array_unique(array_map('intval', $_SESSION['compare'] ?? [])));
}

function statusBadge(?string $status): string
{
    $status = (string) $status;
    $map = [
        'active' => 'ok', 'approved' => 'ok', 'paid' => 'ok', 'delivered' => 'ok', 'verified' => 'ok',
        'completed' => 'ok', 'accepted' => 'ok', 'sold' => 'ok',
        'pending' => 'warn', 'processing' => 'warn', 'scheduled' => 'warn', 'requested' => 'warn',
        'in_transit' => 'warn', 'countered' => 'warn',
        'confirmed' => 'info', 'reserved' => 'info', 'draft' => 'info', 'open' => 'info', 'new' => 'info',
        'rejected' => 'bad', 'failed' => 'bad', 'cancelled' => 'bad', 'returned' => 'bad', 'suspended' => 'bad',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'info') . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function vehicleTitle(array $row): string
{
    return trim(($row['year'] ?? '') . ' ' . ($row['make'] ?? '') . ' ' . ($row['model'] ?? '') . ' ' . ($row['variant'] ?? ''));
}

function listingImage(array $row): string
{
    $img = trim((string) ($row['image'] ?? ''));
    return base('assets/img/' . ($img !== '' ? $img : 'car1.jpg'));
}

function listingThumb(array $row): string
{
    $img = trim((string) ($row['image'] ?? ''));
    $img = $img !== '' ? $img : 'car1.jpg';
    $thumb = __DIR__ . '/../assets/img/thumbs/' . $img;
    return base('assets/img/' . (is_file($thumb) ? 'thumbs/' . $img : $img));
}

function emiAmount(float $principal, float $ratePct, int $months): float
{
    if ($principal <= 0 || $months <= 0) { return 0.0; }
    $r = $ratePct / 1200;
    if ($r <= 0) { return $principal / $months; }
    return $principal * $r * ((1 + $r) ** $months) / (((1 + $r) ** $months) - 1);
}

function refCode(string $prefix, int $id): string
{
    return $prefix . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}
