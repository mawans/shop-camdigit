<?php
/**
 * CamDigit .CM Domain Order Proxy
 * ─────────────────────────────────────────────────────────────────────────────
 * Lives in your WHMCS root directory (same folder as index.php, clientarea.php).
 * Called via AJAX from cm-domain-order.tpl.
 *
 * Security:
 *   - API credentials never reach the browser.
 *   - All user input is validated and sanitised before use.
 *   - CSRF token checked on every POST.
 *   - Rate-limited per IP (5 submissions / 10 minutes) via session.
 *   - Referrer checked to same-origin requests only.
 *
 * WHMCS API docs: https://developers.whmcs.com/api/
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

// ─── Configuration ──────────────────────────────────────────────────────────
define('WHMCS_API_URL',        'https://shop-camdigit.cm/includes/api.php');
define('WHMCS_API_IDENTIFIER', 'YOUR_API_IDENTIFIER_HERE');   // ← replace
define('WHMCS_API_SECRET',     'YOUR_API_SECRET_HERE');        // ← replace

// .cm TLD product details — update these IDs after checking your WHMCS admin
// Admin → Setup → Products/Services → Domain Pricing → .cm
define('CM_TLD',               '.cm');
define('CM_REGISTER_YEARS',    1);          // default registration period

// Payment gateway to attach the invoice to (slug from WHMCS admin)
define('DEFAULT_PAYMENT_GW',   'banktransfer');  // ← update to match your gateway

// Allowed origin for CSRF / referrer check
define('ALLOWED_ORIGIN',       'https://shop-camdigit.cm');

// Rate-limit: max submissions per window
define('RATE_LIMIT_MAX',       5);
define('RATE_LIMIT_WINDOW',    600); // seconds (10 minutes)
// ─────────────────────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json; charset=utf-8');

// ─── Helpers ────────────────────────────────────────────────────────────────

function json_error(string $msg, int $http = 400): never
{
    http_response_code($http);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function json_ok(array $data): never
{
    http_response_code(200);
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Call the WHMCS API. Returns decoded response array.
 * Throws RuntimeException on transport failure.
 */
function whmcs_api(string $action, array $params): array
{
    $payload = array_merge($params, [
        'identifier' => WHMCS_API_IDENTIFIER,
        'secret'     => WHMCS_API_SECRET,
        'action'     => $action,
        'responsetype' => 'json',
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => WHMCS_API_URL,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        throw new RuntimeException("cURL error $errno: $error");
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON response from WHMCS API');
    }

    return $decoded;
}

// ─── Security Checks ────────────────────────────────────────────────────────

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Same-origin check (HTTP_REFERER is advisory but still useful as a layer)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!str_starts_with($referer, ALLOWED_ORIGIN)) {
    json_error('Forbidden', 403);
}

// Parse JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    json_error('Invalid request body');
}

// CSRF token
$csrfToken   = trim((string) ($body['csrf_token'] ?? ''));
$sessionCsrf = $_SESSION['cm_order_csrf'] ?? '';
if (!$csrfToken || !hash_equals($sessionCsrf, $csrfToken)) {
    json_error('Invalid CSRF token', 403);
}

// Rate limiting
$now    = time();
$hits   = $_SESSION['cm_rl_hits']   ?? [];
$window = $_SESSION['cm_rl_window'] ?? 0;

if ($now - $window > RATE_LIMIT_WINDOW) {
    // Reset window
    $hits   = [];
    $window = $now;
}
if (count($hits) >= RATE_LIMIT_MAX) {
    json_error('Too many requests. Please wait a few minutes and try again.', 429);
}
$hits[]                   = $now;
$_SESSION['cm_rl_hits']   = $hits;
$_SESSION['cm_rl_window'] = $window;

// ─── Routing ────────────────────────────────────────────────────────────────

$step = trim((string) ($body['step'] ?? ''));

match ($step) {
    'check_domain' => handle_check_domain($body),
    'submit_order' => handle_submit_order($body),
    'get_csrf'     => handle_get_csrf(),
    default        => json_error('Unknown step'),
};

// ─── Step Handlers ──────────────────────────────────────────────────────────

function handle_get_csrf(): never
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['cm_order_csrf'] = $token;
    json_ok(['csrf_token' => $token]);
}

function handle_check_domain(array $body): never
{
    $sld = sanitize_sld($body['sld'] ?? '');
    if (!$sld) {
        json_error('Invalid domain name');
    }

    try {
        $result = whmcs_api('DomainWhois', ['domain' => $sld . CM_TLD]);
    } catch (RuntimeException $e) {
        error_log('[cm-order-proxy] check_domain error: ' . $e->getMessage());
        json_error('Could not reach domain availability service. Please try again.', 502);
    }

    // WHMCS DomainWhois returns "status" => "available" | "registered" (or similar)
    if (($result['result'] ?? '') !== 'success') {
        json_error($result['message'] ?? 'Domain lookup failed');
    }

    $available = strtolower($result['status'] ?? '') === 'available';
    json_ok([
        'domain'    => $sld . CM_TLD,
        'available' => $available,
        'status'    => $result['status'] ?? 'unknown',
    ]);
}

function handle_submit_order(array $body): never
{
    // ── Validate & sanitise contact fields ──────────────────────────────────
    $sld       = sanitize_sld($body['sld'] ?? '');
    $firstname = sanitize_name($body['firstname'] ?? '');
    $lastname  = sanitize_name($body['lastname'] ?? '');
    $email     = filter_var(trim($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $phone     = sanitize_phone($body['phone'] ?? '');
    $address   = sanitize_text($body['address1'] ?? '');
    $city      = sanitize_text($body['city'] ?? '');
    $country   = sanitize_country($body['country'] ?? '');
    $postcode  = sanitize_text($body['postcode'] ?? '');
    $password  = $body['password'] ?? '';

    $errors = [];
    if (!$sld)       $errors[] = 'Invalid domain name.';
    if (!$firstname) $errors[] = 'First name is required.';
    if (!$lastname)  $errors[] = 'Last name is required.';
    if (!$email)     $errors[] = 'Valid email address is required.';
    if (!$address)   $errors[] = 'Address is required.';
    if (!$city)      $errors[] = 'City is required.';
    if (!$country)   $errors[] = 'Country is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if ($errors) {
        json_error(implode(' ', $errors));
    }

    $domain = $sld . CM_TLD;

    // ── Step 1: AddClient ────────────────────────────────────────────────────
    try {
        $clientResult = whmcs_api('AddClient', [
            'firstname'   => $firstname,
            'lastname'    => $lastname,
            'email'       => $email,
            'address1'    => $address,
            'city'        => $city,
            'country'     => $country,
            'postcode'    => $postcode,
            'phonenumber' => $phone,
            'password2'   => $password,
            'currency'    => 1,          // 1 = default (USD). Adjust if needed.
            'clientip'    => get_client_ip(),
            'noemail'     => false,      // sends welcome email
        ]);
    } catch (RuntimeException $e) {
        error_log('[cm-order-proxy] AddClient error: ' . $e->getMessage());
        json_error('Could not create account. Please try again.', 502);
    }

    if (($clientResult['result'] ?? '') !== 'success') {
        // WHMCS may return "duplicate" if email already registered
        $msg = $clientResult['message'] ?? 'Account creation failed.';
        if (stripos($msg, 'duplicate') !== false || stripos($msg, 'already') !== false) {
            json_error('An account with this email already exists. Please log in to place your order.');
        }
        json_error($msg);
    }

    $clientId = (int) $clientResult['clientid'];

    // ── Step 2: AddOrder ─────────────────────────────────────────────────────
    try {
        $orderResult = whmcs_api('AddOrder', [
            'clientid'        => $clientId,
            'domain'          => [$domain],
            'domaintype'      => ['register'],
            'regperiod'       => [CM_REGISTER_YEARS],
            'dnsmanagement'   => [true],
            'emailforwarding' => [false],
            'idprotection'    => [false],
            'nameserver1'     => 'ns1.shop-camdigit.cm',
            'nameserver2'     => 'ns2.shop-camdigit.cm',
            'paymentmethod'   => DEFAULT_PAYMENT_GW,
            'noemail'         => false,
        ]);
    } catch (RuntimeException $e) {
        error_log('[cm-order-proxy] AddOrder error: ' . $e->getMessage());
        json_error('Order could not be created. Please contact support.', 502);
    }

    if (($orderResult['result'] ?? '') !== 'success') {
        json_error($orderResult['message'] ?? 'Order creation failed.');
    }

    $orderId   = (int) $orderResult['orderid'];
    $invoiceId = (int) ($orderResult['invoiceid'] ?? 0);

    json_ok([
        'client_id'  => $clientId,
        'order_id'   => $orderId,
        'invoice_id' => $invoiceId,
        'domain'     => $domain,
        'message'    => 'Your order has been placed! Check your email for confirmation.',
    ]);
}

// ─── Sanitisation helpers ────────────────────────────────────────────────────

/** Strip everything except lowercase letters, digits, hyphens. Max 63 chars. */
function sanitize_sld(string $input): string
{
    $clean = preg_replace('/[^a-zA-Z0-9\-]/', '', trim($input));
    $clean = strtolower(substr($clean, 0, 63));
    // Must not start or end with a hyphen
    return (strlen($clean) >= 2 && $clean[0] !== '-' && substr($clean, -1) !== '-')
        ? $clean
        : '';
}

function sanitize_name(string $input): string
{
    return substr(trim(strip_tags($input)), 0, 100);
}

function sanitize_text(string $input): string
{
    return substr(trim(strip_tags($input)), 0, 255);
}

function sanitize_phone(string $input): string
{
    // Allow +, digits, spaces, dashes, parentheses
    return substr(preg_replace('/[^0-9+\s\-()]/', '', $input), 0, 20);
}

function sanitize_country(string $input): string
{
    // ISO-3166-1 alpha-2
    $clean = strtoupper(preg_replace('/[^a-zA-Z]/', '', $input));
    return strlen($clean) === 2 ? $clean : '';
}

function get_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $val = $_SERVER[$key] ?? '';
        if ($val) {
            // Take first IP in case of comma-separated list
            $ip = trim(explode(',', $val)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
