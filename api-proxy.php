<?php
/**
 * CamDigit WHMCS API Proxy (generalised)
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/api-proxy.php
 *
 * Accepts POST (JSON) from custom front-end pages, validates origin + CSRF +
 * rate-limit, then forwards the request to the WHMCS API with credentials
 * injected server-side. Credentials never reach the browser.
 *
 * Special-case handlers run before forwarding:
 *   - DomainWhois on a .cm domain → direct WHOIS to whois.nic.cm
 *   - AddOrder for a .cm domain   → server-side cart enforcement
 *
 * For all other actions on the allowlist, the proxy forwards transparently.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/whmcs.php';

// Same-origin allowed host (no protocol, no trailing slash)
const ALLOWED_HOST = 'shop-camdigit.cm';

// Actions callable through this proxy. Add to this list as you build flows.
// Read-only / lookup actions
const ALLOWED_LOOKUP = [
    'DomainWhois', 'CheckMultiAvailability', 'GetProducts', 'GetTldPricing', 'GetCurrencies',
    'GetPaymentMethods', 'GetPromotions',
];
// Client-authenticated actions (require a logged-in client id matching session)
const ALLOWED_CLIENT = [
    'GetClientsDetails', 'GetClientsProducts', 'GetClientsDomains',
    'GetInvoices', 'GetInvoice', 'GetTickets', 'GetTicket', 'OpenTicket',
    'UpdateClient',
];
// Mutating actions that create resources
const ALLOWED_CREATE = [
    'AddClient', 'ValidateLogin', 'AddOrder', 'ApplyCredit', 'CreateSsoToken',
];

const ALLOWED_ACTIONS = [...ALLOWED_LOOKUP, ...ALLOWED_CLIENT, ...ALLOWED_CREATE];

const RATE_LIMIT_MAX    = 30;
const RATE_LIMIT_WINDOW = 300;

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ──────────────────────────────────────────────────────────────────
function bail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['result' => 'error', 'message' => $msg]);
    exit;
}

function ok(array $data): never
{
    http_response_code(200);
    echo json_encode($data);
    exit;
}

// ── Method ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bail('Method not allowed', 405);

// ── Origin ──────────────────────────────────────────────────────────────────
function origin_allowed(): bool
{
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        $v = $_SERVER[$h] ?? '';
        if ($v) {
            $host = parse_url($v, PHP_URL_HOST) ?? '';
            return $host === ALLOWED_HOST;
        }
    }
    return true;
}
if (!origin_allowed()) bail('Forbidden', 403);

// ── Body ────────────────────────────────────────────────────────────────────
$raw  = (string) file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) bail('Invalid request body — expected JSON');

$action = (string) ($body['action'] ?? '');

// ── CSRF issuance ───────────────────────────────────────────────────────────
if ($action === '__csrf__') {
    ok(['csrf_token' => cd_csrf()]);
}

// ── CSRF check ──────────────────────────────────────────────────────────────
$sent = (string)($body['_csrf'] ?? '');
$sess = (string)($_SESSION['cd_csrf'] ?? '');
if (!$sent || !$sess || !hash_equals($sess, $sent)) bail('Invalid or missing CSRF token', 403);

// ── Allowlist ───────────────────────────────────────────────────────────────
if (!in_array($action, ALLOWED_ACTIONS, true)) bail("Action '$action' not permitted", 403);

// ── Client-scoped actions: bind clientid to session ─────────────────────────
if (in_array($action, ALLOWED_CLIENT, true)) {
    $sessionClientId = cd_client_id();
    if ($sessionClientId === 0) bail('You must be logged in.', 401);
    // Force clientid to match session — client cannot impersonate
    $body['clientid'] = $sessionClientId;
}

// ── Rate limit ──────────────────────────────────────────────────────────────
if (!cd_rate_limit('proxy_' . md5(cd_client_ip()), RATE_LIMIT_MAX, RATE_LIMIT_WINDOW)) {
    bail('Too many requests. Please wait a few minutes.', 429);
}

// ── Special: CheckMultiAvailability — batch check multiple TLDs ─────────────
if ($action === 'CheckMultiAvailability') {
    $sld  = cd_sanitize_sld((string)($body['sld'] ?? ''));
    $tlds = array_slice(array_unique((array)($body['tlds'] ?? [])), 0, 8);

    if (!$sld) bail('Invalid domain name', 400);

    $results = [];
    foreach ($tlds as $rawTld) {
        $tld = '.' . ltrim(strtolower(preg_replace('/[^a-z0-9.]/', '', (string)$rawTld)), '.');
        if (strlen($tld) < 2 || strlen($tld) > 20) continue;
        $domain = $sld . $tld;

        if ($tld === '.cm') {
            $status = cd_check_cm_whois($domain);
            $results[] = ['domain' => $domain, 'tld' => $tld, 'status' => $status];
        } else {
            try {
                $r = whmcs_api('DomainWhois', ['domain' => $domain]);
                $status = strtolower((string)($r['status'] ?? 'unknown'));
                $results[] = ['domain' => $domain, 'tld' => $tld, 'status' => $status];
            } catch (RuntimeException) {
                $results[] = ['domain' => $domain, 'tld' => $tld, 'status' => 'unknown'];
            }
        }
    }

    ok(['result' => 'success', 'domains' => $results]);
}

// ── Special: .cm WHOIS ──────────────────────────────────────────────────────
if ($action === 'DomainWhois') {
    $domain = strtolower(trim((string)($body['domain'] ?? '')));
    if (str_ends_with($domain, '.cm')) {
        $status = cd_check_cm_whois($domain);
        if ($status === 'available') {
            $_SESSION['cm_cart_domain'] = $domain;
            $_SESSION['cm_cart_set_at'] = time();
        } else {
            unset($_SESSION['cm_cart_domain'], $_SESSION['cm_cart_set_at']);
        }
        ok(['result' => 'success', 'status' => $status, 'rawdata' => '']);
    }
}

// ── Special: .cm AddOrder pulls domain from session, not client ─────────────
if ($action === 'AddOrder') {
    $cart       = (string) ($_SESSION['cm_cart_domain'] ?? '');
    $setAt      = (int)    ($_SESSION['cm_cart_set_at'] ?? 0);
    $domainArg  = strtolower(trim((string) ($body['domain[0]'] ?? $body['domain'][0] ?? '')));

    if ($cart && str_ends_with($cart, '.cm') && ($domainArg === '' || $domainArg === $cart)) {
        // Enforce session-verified .cm cart
        if (time() - $setAt > 30 * 60) {
            unset($_SESSION['cm_cart_domain'], $_SESSION['cm_cart_set_at']);
            bail('Your domain reservation expired. Please search again.', 400);
        }
        $body['domain[0]']     = $cart;
        $body['domaintype[0]'] = 'register';
        $body['regperiod[0]']  = 1;
        $body['nameserver1']   = CM_NS1;
        $body['nameserver2']   = CM_NS2;
    }
}

// ── ValidateLogin: set session uid on success ───────────────────────────────
// (we still forward to WHMCS, but capture the userid for our session)
$captureLogin = ($action === 'ValidateLogin');

// ── Build payload, strip our internal keys ──────────────────────────────────
$exclude = ['_csrf', 'action'];
$forward = [];
foreach ($body as $k => $v) {
    if (!in_array($k, $exclude, true)) $forward[$k] = $v;
}

// ── Forward to WHMCS ────────────────────────────────────────────────────────
try {
    $decoded = whmcs_api($action, $forward);
} catch (RuntimeException $e) {
    bail($e->getMessage(), 502);
}

// ── Post-processing ─────────────────────────────────────────────────────────
if ($captureLogin && ($decoded['result'] ?? '') === 'success' && !empty($decoded['userid'])) {
    $_SESSION['uid'] = (int) $decoded['userid'];
}

if ($action === 'AddOrder' && ($decoded['result'] ?? '') === 'success') {
    unset($_SESSION['cm_cart_domain'], $_SESSION['cm_cart_set_at']);
}

ok($decoded);
