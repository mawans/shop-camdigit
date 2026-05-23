<?php
/**
 * CamDigit — Cart AJAX API
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/cart-api.php
 *
 * JSON POST endpoint for realtime cart mutations from the homepage and
 * domain-search pages. Same-origin + CSRF + rate-limited.
 *
 *   POST { action: "__csrf__" }                     → { csrf_token }
 *   POST { action: "get" }                          → { cart, count, totals }
 *   POST { action: "add_domain", _csrf, domain }    → { cart, count, totals, added }
 *   POST { action: "remove_domain", _csrf, domain } → { cart, count, totals }
 *
 * Pricing for add_domain is resolved server-side via GetTLDPricing so the
 * client cannot spoof prices.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/cart.php';

const ALLOWED_HOST_CART = 'shop-camdigit.cm';

header('Content-Type: application/json; charset=utf-8');

function cart_bail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['result' => 'error', 'message' => $msg]);
    exit;
}

function cart_ok(array $data): never
{
    http_response_code(200);
    echo json_encode(array_merge(['result' => 'success'], $data));
    exit;
}

function cart_state_payload(): array
{
    $c = cd_cart();
    return [
        'count'   => cd_cart_count(),
        'totals'  => cd_cart_totals(),
        'cart'    => [
            'currency'    => $c['currency'] ?? 'XAF',
            'hosting'     => $c['hosting'] ?? null,
            'domains'     => array_values($c['domains'] ?? []),
            'main_domain' => $c['main_domain'] ?? null,
        ],
    ];
}

function cart_origin_allowed(): bool
{
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        $v = $_SERVER[$h] ?? '';
        if ($v) {
            $host = parse_url($v, PHP_URL_HOST) ?? '';
            return $host === ALLOWED_HOST_CART;
        }
    }
    return true;
}

/** Look up the per-year register price for a TLD via WHMCS. */
function cart_tld_price(string $tld): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = whmcs_api('GetTLDPricing', []);
        } catch (RuntimeException) {
            $cache = [];
        }
    }
    // WHMCS returns currency as an object: { id, code, prefix, suffix, ... }
    // We only ever want the ISO code string downstream — never the array.
    $cur = $cache['currency'] ?? 'USD';
    if (is_array($cur)) $cur = (string)($cur['code'] ?? 'USD');
    $pricing = $cache['pricing'] ?? [];
    $key = ltrim($tld, '.');
    if (!isset($pricing[$key])) return [0.0, $cur];
    $reg = $pricing[$key]['register'] ?? [];
    if (!$reg) return [0.0, $cur];
    $first = reset($reg);
    return [is_numeric($first) ? (float)$first : 0.0, $cur];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') cart_bail('Method not allowed', 405);
if (!cart_origin_allowed())                 cart_bail('Forbidden', 403);

$raw  = (string)file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) cart_bail('Invalid request body — expected JSON');

$action = (string)($body['action'] ?? '');

// CSRF issuance — handled before CSRF check.
if ($action === '__csrf__') {
    cart_ok(['csrf_token' => cd_csrf()]);
}

// Read-only state (no CSRF needed; same-origin enforced above).
if ($action === 'get') {
    cart_ok(cart_state_payload());
}

// All mutations require CSRF.
$sent = (string)($body['_csrf'] ?? '');
$sess = (string)($_SESSION['cd_csrf'] ?? '');
if (!$sent || !$sess || !hash_equals($sess, $sent)) {
    cart_bail('Invalid or missing CSRF token', 403);
}

if (!cd_rate_limit('cart_api_' . md5(cd_client_ip()), 60, 300)) {
    cart_bail('Too many requests. Please wait a few minutes.', 429);
}

if ($action === 'add_domain') {
    $domain = strtolower(trim((string)($body['domain'] ?? '')));
    // Validate domain syntax: <sld>.<tld>
    if (!preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.([a-z]{2}(?:\.[a-z]{2,})?|[a-z]{3,})$/', $domain, $m)) {
        cart_bail('Invalid domain name', 400);
    }
    $sld = $m[1];
    $tld = '.' . $m[2];
    $years = max(1, min(10, (int)($body['years'] ?? 1)));

    [$perYear, $currency] = cart_tld_price($tld);
    $total = round($perYear * $years, 2);

    cd_cart_add_domain([
        'domain'   => $domain,
        'type'     => 'register',
        'years'    => $years,
        'price'    => $total,
        'original' => $total,
    ]);

    if ($currency) {
        $c = cd_cart();
        if (empty($c['currency']) || $c['currency'] === 'XAF') {
            $c['currency'] = $currency;
            cd_cart_save($c);
        }
    }

    cart_ok(array_merge(cart_state_payload(), [
        'added' => ['domain' => $domain, 'years' => $years, 'price' => $total],
    ]));
}

if ($action === 'remove_domain') {
    $domain = strtolower(trim((string)($body['domain'] ?? '')));
    if ($domain === '') cart_bail('Missing domain', 400);
    cd_cart_remove_domain($domain);
    cart_ok(cart_state_payload());
}

if ($action === 'remove_hosting') {
    cd_cart_remove_hosting();
    cart_ok(cart_state_payload());
}

if ($action === 'set_hosting_years') {
    $years = max(1, min(3, (int)($body['years'] ?? 1)));
    cd_cart_set_hosting_years($years);
    cart_ok(cart_state_payload());
}

if ($action === 'set_domain_years') {
    $domain = strtolower(trim((string)($body['domain'] ?? '')));
    if ($domain === '') cart_bail('Missing domain', 400);
    $years = max(1, min(10, (int)($body['years'] ?? 1)));
    cd_cart_set_domain_years($domain, $years);
    cart_ok(cart_state_payload());
}

if ($action === 'set_main_domain') {
    $domain = strtolower(trim((string)($body['domain'] ?? '')));
    if ($domain === '') cart_bail('Missing domain', 400);
    cd_cart_set_main_domain($domain);
    cart_ok(cart_state_payload());
}

if ($action === 'apply_promo') {
    $promo = cd_sanitize_text((string)($body['promo'] ?? ''), 40);
    $c = cd_cart();
    $c['promo'] = $promo;
    cd_cart_save($c);
    cart_ok(cart_state_payload());
}

if ($action === 'clear') {
    cd_cart_clear();
    cart_ok(cart_state_payload());
}

cart_bail("Action '$action' not permitted", 403);