<?php
/**
 * CamDigit — redirect native WHMCS purchase / login flows into the custom
 * front-end. Lives in <whmcs_root>/includes/hooks/cm_cart_redirect.php.
 *
 * Redirect map:
 *   cart.php?a=add&domain=register&query=NAME       → /order-domain.php
 *   cart.php?gid=N      (product group listing)     → /order-hosting.php?gid=N
 *   cart.php?a=add&pid=N                            → /order-hosting.php?pid=N
 *
 * Anything we don't recognise falls through to native WHMCS so we don't
 * break edge cases (configure products, addons, etc.) before they're rebuilt.
 *
 * The original .cm-only JS intercept is retained for AJAX-style theme searches.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaPageCart', 1, function ($vars) {
    $action  = strtolower((string) ($_REQUEST['a']      ?? ''));
    $domain  = strtolower((string) ($_REQUEST['domain'] ?? ''));
    $query   = strtolower(trim((string) ($_REQUEST['query'] ?? '')));
    $pid     = (int)    ($_REQUEST['pid'] ?? 0);
    $gid     = (int)    ($_REQUEST['gid'] ?? 0);
    $sysurl  = rtrim((string) ($vars['systemurl'] ?? ''), '/');

    $target = null;

    // 1) Domain search add-to-cart
    if ($action === 'add' && $domain === 'register' && $query !== '') {
        // Strip a TLD if user typed full domain
        if (preg_match('/^([a-z0-9][a-z0-9\-]{0,61}[a-z0-9]?)(\.[a-z.]+)?$/i', $query, $m)) {
            $sld = strtolower($m[1]);
            $tld = !empty($m[2]) ? strtolower($m[2]) : '.cm';
            $target = $sysurl . '/order-domain.php?sld=' . rawurlencode($sld) . '&tld=' . rawurlencode($tld);
        }
    }

    // 2) Product purchase — pid takes precedence over gid
    elseif ($action === 'add' && $pid > 0) {
        $target = $sysurl . '/order-hosting.php?pid=' . $pid;
    }
    elseif ($gid > 0 && $action === '') {
        $target = $sysurl . '/order-hosting.php?gid=' . $gid;
    }

    if (!$target) return;

    if (!headers_sent()) {
        header('Location: ' . $target, true, 302);
    } else {
        echo '<script>window.location.href=' . json_encode($target) . ';</script>';
    }
    exit;
});

// ── Retain the .cm JS intercept for AJAX-driven theme searches ──────────────
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $action = strtolower((string) ($_GET['a']      ?? ''));
    $domain = strtolower((string) ($_GET['domain'] ?? ''));
    if ($action !== 'add' || $domain !== 'register') return '';

    $scriptUrl = rtrim((string) ($vars['systemurl'] ?? ''), '/') . '/cm-redirect.js';
    return '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES) . '"></script>';
});

// ── Send WHMCS native login form attempts into our custom /login.php ────────
add_hook('ClientAreaPageLogin', 1, function ($vars) {
    if (!empty($vars['loggedin'])) return;
    $sysurl = rtrim((string) ($vars['systemurl'] ?? ''), '/');
    $next   = isset($_GET['goto']) ? '?next=' . rawurlencode((string)$_GET['goto']) : '';
    if (!headers_sent()) {
        header('Location: ' . $sysurl . '/login.php' . $next, true, 302);
        exit;
    }
});

// ── Send the native registration page to our /register.php ──────────────────
add_hook('ClientAreaPageRegister', 1, function ($vars) {
    $sysurl = rtrim((string) ($vars['systemurl'] ?? ''), '/');
    if (!headers_sent()) {
        header('Location: ' . $sysurl . '/register.php', true, 302);
        exit;
    }
});
