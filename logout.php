<?php
require_once __DIR__ . '/includes/helpers.php';
$u = user();
if ($u) { logActivity((int) $u['id'], 'auth.logout', (string) $u['email']); }
$_SESSION = [];
session_destroy();
session_start();
flash('success', 'You have been signed out.');
redirect(base('index.php'));
