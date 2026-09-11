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
        redirect(safeNext($_SERVER['HTTP_REFERER'] ?? '', base('index.php')));
    }
}

/**
 * Allow only same-site relative redirect targets. Absolute URLs, protocol-
 * relative URLs and backslash tricks fall back to $fallback (open-redirect
 * guard for ?next=, Referer and stored link redirects).
 */
function safeNext(string $url, string $fallback): string
{
    $url = trim($url);
    if ($url === '' || str_contains($url, "\r") || str_contains($url, "\n") || str_contains($url, "\\")) {
        return $fallback;
    }
    // Reject absolute + protocol-relative URLs (case-insensitive scheme check).
    if ((bool) preg_match('#^\s*(?:[a-z][a-z0-9+.-]*:|//)#i', $url)) { return $fallback; }
    // Plain relative path or root-relative path only.
    if (!str_starts_with($url, '/') && !preg_match('#^[A-Za-z0-9][A-Za-z0-9._~:/?#\[\]@!$&\'()*+,;=%-]*$#', $url)) {
        return $fallback;
    }
    return $url;
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
        'completed' => 'ok', 'accepted' => 'ok', 'sold' => 'ok', 'disbursed' => 'ok', 'purchased' => 'ok', 'settled' => 'ok',
        'pending' => 'warn', 'processing' => 'warn', 'scheduled' => 'warn', 'requested' => 'warn',
        'in_transit' => 'warn', 'countered' => 'warn', 'quoted' => 'warn', 'under_review' => 'warn',
        'confirmed' => 'info', 'reserved' => 'info', 'draft' => 'info', 'open' => 'info', 'new' => 'info',
        'rejected' => 'bad', 'failed' => 'bad', 'cancelled' => 'bad', 'returned' => 'bad', 'suspended' => 'bad',
        'flagged' => 'bad', 'expired' => 'bad',
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
    if ($img === '') { $img = 'car1.jpg'; }
    if (str_starts_with($img, 'uploads/')) { return base('assets/' . $img); }
    return base('assets/img/' . $img);
}

function listingThumb(array $row): string
{
    $img = trim((string) ($row['image'] ?? ''));
    if ($img === '') { $img = 'car1.jpg'; }
    if (str_starts_with($img, 'uploads/')) { return base('assets/' . $img); }
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

/* ---------------- SRS helpers: notifications, chat safety, VIN, uploads, ratings ---------------- */

function notify(int $userId, string $title, string $body = '', string $link = ''): void
{
    if ($userId <= 0 || !dbReady()) { return; }
    try { insert('notifications', ['user_id' => $userId, 'title' => $title, 'body' => $body, 'link' => $link]); }
    catch (Throwable $e) { /* notifications must never break a request */ }
}

/** Notify every admin (used for reports, KYC, support, document alerts). */
function notifyAdmins(string $title, string $body = '', string $link = ''): void
{
    if (!dbReady()) { return; }
    try {
        foreach (fetchAll("SELECT id FROM users WHERE role = 'admin'") as $a) {
            notify((int) $a['id'], $title, $body, $link);
        }
    } catch (Throwable $e) { /* never break a request */ }
}

/** 0-100 score rendered as a 5-star string, e.g. ★★★★☆. */
function stars(?int $score): string
{
    if ($score === null) { return '☆☆☆☆☆'; }
    $filled = (int) round(max(0, min(100, $score)) / 20);
    return str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
}

/** Human label for a 0-100 inspection/condition score. */
function conditionLabel(?int $score): string
{
    if ($score === null) { return 'Not inspected'; }
    if ($score >= 85) { return 'Excellent'; }
    if ($score >= 70) { return 'Good'; }
    if ($score >= 55) { return 'Average'; }
    return 'Needs attention';
}

function unreadNotifications(): int
{
    $u = user();
    if ($u === null || !dbReady()) { return 0; }
    try { return (int) fetchValue('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$u['id']], 0); }
    catch (Throwable $e) { return 0; }
}

/** Fraud / spam keywords flagged in chat (SRS: fraud-resistant messaging). */
function fraudWords(): array
{
    return ['wire transfer', 'western union', 'advance fee', 'gift card', 'otp', 'upi pin',
        'account number', 'cvv', 'password', 'outside the platform', 'direct payment'];
}

function containsFraud(string $text): bool
{
    $t = strtolower($text);
    foreach (fraudWords() as $w) { if (str_contains($t, $w)) { return true; } }
    return (bool) preg_match('/\b\d{6,}\b/', $t);
}

/**
 * Masks phone numbers and e-mail addresses in chat until buyer and seller
 * are connected through an order/offer (SRS: hidden contact details).
 */
function maskContact(string $text, bool $connected): string
{
    if ($connected) { return $text; }
    $text = (string) preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[contact hidden]', $text);
    return (string) preg_replace('/(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}/', '[phone hidden]', $text);
}

/** Offline VIN decoder (WMI + year-code tables, stands in for a VIN API). */
function vinDecode(string $vin): array
{
    $vin = strtoupper(trim($vin));
    $out = ['vin' => $vin, 'valid' => false, 'make' => '', 'country' => 'India', 'year' => null];
    if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) { return $out; }
    $wmi = substr($vin, 0, 3);
    $makes = [
        'MAL' => 'Hyundai', 'MA3' => 'Maruti Suzuki', 'MAK' => 'Honda', 'MAT' => 'Tata',
        'MA1' => 'Mahindra', 'MBJ' => 'Toyota', 'MZB' => 'Kia', 'MEX' => 'Skoda',
        'MEE' => 'Renault', 'MEC' => 'Mercedes-Benz', 'WBA' => 'BMW', 'WDD' => 'Mercedes-Benz',
        'WAU' => 'Audi', 'SAL' => 'Land Rover', 'VF1' => 'Renault',
    ];
    $years = ['A' => 2010, 'B' => 2011, 'C' => 2012, 'D' => 2013, 'E' => 2014, 'F' => 2015,
        'G' => 2016, 'H' => 2017, 'J' => 2018, 'K' => 2019, 'L' => 2020, 'M' => 2021,
        'N' => 2022, 'P' => 2023, 'R' => 2024, 'S' => 2025, 'T' => 2026];
    $out['valid'] = true;
    $out['make'] = $makes[$wmi] ?? '';
    $out['country'] = str_starts_with($wmi, 'M') ? 'India' : 'Imported';
    $out['year'] = $years[$vin[9] ?? ''] ?? null;
    return $out;
}

/** Average approved rating received by a seller (buyers only). */
function sellerRating(int $sellerId): array
{
    if (!dbReady()) { return ['avg' => 0.0, 'count' => 0]; }
    try {
        $row = fetchOne("SELECT COALESCE(AVG(rating),0) AS avg, COUNT(*) AS c FROM reviews
            WHERE target_user_id = ? AND reviewer_role = 'buyer' AND status = 'approved'", [$sellerId]);
        return ['avg' => round((float) ($row['avg'] ?? 0), 1), 'count' => (int) ($row['c'] ?? 0)];
    } catch (Throwable $e) { return ['avg' => 0.0, 'count' => 0]; }
}

function listingRating(int $listingId): array
{
    if (!dbReady()) { return ['avg' => 0.0, 'count' => 0]; }
    try {
        $row = fetchOne("SELECT COALESCE(AVG(rating),0) AS avg, COUNT(*) AS c FROM reviews
            WHERE listing_id = ? AND reviewer_role = 'buyer' AND status = 'approved'", [$listingId]);
        return ['avg' => round((float) ($row['avg'] ?? 0), 1), 'count' => (int) ($row['c'] ?? 0)];
    } catch (Throwable $e) { return ['avg' => 0.0, 'count' => 0]; }
}

/** Gallery images for a listing: uploaded photos first, then the cover image. */
function listingGallery(array $row): array
{
    $out = [];
    if (dbReady()) {
        try {
            foreach (fetchAll('SELECT image, label FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [(int) $row['id']]) as $img) {
                $out[] = ['src' => base('assets/uploads/' . $img['image']), 'label' => (string) ($img['label'] ?? '')];
            }
        } catch (Throwable $e) { /* gallery is optional */ }
    }
    if ($out === []) {
        $out[] = ['src' => listingImage($row), 'label' => 'Cover photo'];
    }
    return $out;
}

/** Validate + move an uploaded image into assets/uploads, returns stored file name. */
function saveUpload(array $file, string $prefix = 'img'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    if ((int) ($file['size'] ?? 0) > 4 * 1024 * 1024) { return null; }
    $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
    if ($info === false) { return null; }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if ($ext === null) { return null; }
    $dir = __DIR__ . '/../assets/uploads';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $name = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    return move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name) ? $name : null;
}

/** Store an uploaded 3D model (.glb/.gltf, max 30 MB). Returns the filename or null. */
function saveModel3D(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    if ((int) ($file['size'] ?? 0) > 30 * 1024 * 1024) { return null; }
    $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['glb', 'gltf'], true)) { return null; }
    $dir = __DIR__ . '/../assets/uploads';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $name = 'model3d_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    return move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name) ? $name : null;
}

/** Read a site setting (admin/settings.php). Falls back when DB is unreachable. */
function setting(string $key, mixed $default = ''): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) { return $cache[$key]; }
    if (!dbReady()) { return $default; }
    try { $v = fetchValue('SELECT v FROM settings WHERE k = ?', [$key], null); }
    catch (Throwable) { return $default; }
    $cache[$key] = $v === null ? $default : $v;
    return $cache[$key];
}

function saveSetting(string $key, string $value): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
}

/** Sale-agreement e-signatures on an order: ['buyer' => bool, 'seller' => bool]. */
function orderSignatures(int $orderId): array
{
    $out = ['buyer' => false, 'seller' => false];
    if (!dbReady()) { return $out; }
    $ord = fetchOne('SELECT o.buyer_id, l.seller_id FROM orders o JOIN listings l ON l.id = o.listing_id WHERE o.id = ?', [$orderId]);
    if (!$ord) { return $out; }
    $rows = fetchAll("SELECT user_id FROM documents WHERE order_id = ? AND doc_type = 'sale_agreement' AND status = 'verified'", [$orderId]);
    foreach ($rows as $r) {
        if ((int) $r['user_id'] === (int) $ord['buyer_id']) { $out['buyer'] = true; }
        if ((int) $r['user_id'] === (int) $ord['seller_id']) { $out['seller'] = true; }
    }
    return $out;
}

/** Store an uploaded document (image or PDF) for KYC / vault. */
function saveDocument(array $file, string $prefix = 'doc'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) { return null; }
    $dir = __DIR__ . '/../assets/uploads';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $safe = (string) preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) ($file['name'] ?? 'file'));
    $ext = strtolower((string) pathinfo($safe, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) { return null; }
    $name = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    return move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name) ? $name : null;
}

/** Stable server secret for API token HMACs (survives session rotation). */
function apiSecret(): string
{
    $cfg = config();
    $secret = (string) (getenv('APP_SECRET') ?: ($cfg['app']['secret'] ?? ''));
    if ($secret === '') {
        // Last-resort fallback: derived from the configured DB credentials so
        // it is stable per deployment without hard-coding a secret here.
        $secret = hash('sha256', 'drive24|' . ($cfg['db']['host'] ?? '') . '|' . ($cfg['db']['name'] ?? '') . '|' . ($cfg['db']['user'] ?? ''));
    }
    return $secret;
}

/** Signed API token (HMAC) for JSON clients; sessions remain the primary auth. */
function apiToken(int $userId): string
{
    $exp = time() + 7 * 86400;
    $body = $userId . '.' . $exp;
    return $body . '.' . hash_hmac('sha256', $body, apiSecret());
}

/** Authenticate a JSON request via session cookie or `Authorization: Bearer` token. */
function apiUser(): ?array
{
    $u = user();
    if ($u !== null) { return $u; }
    $hdr = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (!str_starts_with($hdr, 'Bearer ') || !dbReady()) { return null; }
    $parts = explode('.', substr($hdr, 7));
    if (count($parts) !== 3) { return null; }
    [$id, $exp, $sig] = $parts;
    if ((int) $exp < time()) { return null; }
    if (!hash_equals(hash_hmac('sha256', $id . '.' . $exp, apiSecret()), $sig)) { return null; }
    return fetchOne('SELECT id, name, email, mobile, role, city, kyc_status FROM users WHERE id = ? AND status = "active"', [(int) $id]);
}

/**
 * CSRF guard for session-authenticated JSON API calls. Browsers can be
 * tricked into POSTing JSON cross-site (e.g. via text/plain forms), so any
 * state-changing API reached through the session cookie must also present
 * the CSRF token (header X-CSRF-Token, already sent by postJson(), or a
 * _token field in the JSON body). Bearer-token callers are exempt because
 * the token itself is not ambient authority.
 */
function verifyApiCsrf(?array $body = null): void
{
    if (user() === null) { return; } // Bearer or guest: no ambient session to forge.
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (is_string($hdr) && str_starts_with($hdr, 'Bearer ')) { return; }
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ((!is_string($sent) || $sent === '') && is_array($body)) {
        $sent = $body['_token'] ?? '';
    }
    if (!is_string($sent) || $sent === '' || !hash_equals(csrfToken(), $sent)) {
        apiJson(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
    }
}

function apiJson(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}
