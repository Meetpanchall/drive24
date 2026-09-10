<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

// POST /api/auth.php?action=signup  {name,email,mobile,password,role}
// POST /api/auth.php?action=login   {email,password} -> {token}
if (!dbReady()) { apiJson(['ok' => false, 'message' => 'Database unavailable.'], 503); }
$action = (string) ($_GET['action'] ?? '');
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];

if ($action === 'signup') {
    $email = trim((string) ($in['email'] ?? ''));
    $pw = (string) ($in['password'] ?? '');
    $role = in_array($in['role'] ?? '', ['buyer', 'seller', 'dealer'], true) ? (string) $in['role'] : 'buyer';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8 || trim((string) ($in['name'] ?? '')) === '') {
        apiJson(['ok' => false, 'message' => 'Name, valid e-mail and 8+ character password required.'], 422);
    }
    if (fetchOne('SELECT id FROM users WHERE email = ?', [$email])) {
        apiJson(['ok' => false, 'message' => 'E-mail already registered.'], 409);
    }
    $id = insert('users', ['name' => trim((string) $in['name']), 'email' => $email,
        'mobile' => trim((string) ($in['mobile'] ?? '')) ?: null, 'role' => $role,
        'password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
    $_SESSION['user_id'] = $id;
    apiJson(['ok' => true, 'user_id' => $id, 'token' => apiToken($id), 'message' => 'Signup successful.'], 201);
}

if ($action === 'login') {
    $row = fetchOne('SELECT * FROM users WHERE email = ?', [trim((string) ($in['email'] ?? ''))]);
    if (!$row || !password_verify((string) ($in['password'] ?? ''), (string) $row['password_hash'])) {
        apiJson(['ok' => false, 'message' => 'Incorrect e-mail or password.'], 401);
    }
    if ($row['status'] !== 'active') { apiJson(['ok' => false, 'message' => 'Account suspended.'], 403); }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    logActivity((int) $row['id'], 'auth.login_api', '');
    apiJson(['ok' => true, 'token' => apiToken((int) $row['id']),
        'user' => ['id' => (int) $row['id'], 'name' => $row['name'], 'role' => $row['role']]]);
}

apiJson(['ok' => false, 'message' => 'Use ?action=signup or ?action=login.'], 404);
