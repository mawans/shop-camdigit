<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

// Clear session uid and other auth-related keys; preserve language pref
$lang = $_SESSION['language'] ?? null;
$_SESSION = [];
if ($lang) $_SESSION['language'] = $lang;

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: ' . SITE_URL . '/');
exit;
