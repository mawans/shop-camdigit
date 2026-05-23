<?php
/**
 * CamDigit — site homepage
 * ─────────────────────────────────────────────────────────────────────────────
 * Canonical WHMCS 8.x homepage entry point. Renders the active theme's
 * homepage.tpl (six/header.tpl + six/homepage.tpl + six/footer.tpl).
 *
 * Internal WHMCS routes (?rp=...) — password reset, store, etc. — get handed
 * off to clientarea.php so they keep working.
 * ─────────────────────────────────────────────────────────────────────────────
 */

if (!empty($_GET['rp'])) {
    require __DIR__ . '/clientarea.php';
    exit;
}

define('CLIENTAREA', true);

require __DIR__ . '/init.php';

$ca = new WHMCS\ClientArea();

$ca->setPageTitle('');
$ca->initPage();

$ca->setTemplate('homepage');
$ca->output();
