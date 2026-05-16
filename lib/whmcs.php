<?php
/**
 * CamDigit — shared WHMCS API helper
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/lib/whmcs.php
 *
 * Every custom front-end page (order-domain.php, order-hosting.php, login.php,
 * account.php, etc.) requires this file once at the top:
 *
 *     require_once __DIR__ . '/lib/whmcs.php';
 *
 * It bootstraps the WHMCS session (so $loggedInClientId works), exposes a
 * single whmcs_api() function for talking to the WHMCS API server-side with
 * credentials, plus CSRF + sanitisation helpers and a render_layout() that
 * wraps page content in the existing site header/footer.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);

// ── Configuration ────────────────────────────────────────────────────────────
const WHMCS_API_URL        = 'https://shop-camdigit.cm/includes/api.php';
const WHMCS_API_IDENTIFIER = 'ZvGKrMb57QMcWumKVbdmgYNvoCvepkID';
const WHMCS_API_SECRET     = 'LybPsvebnvaF2FpNS0fzba0KGxNGufbh';
const SITE_URL             = 'https://shop-camdigit.cm';
const COMPANY_NAME         = 'CamDigit';
const LOGO_URL             = SITE_URL . '/img/logo-camdigit.png';   // place logo at <whmcs_root>/img/logo-camdigit.png

// .cm nameservers for cmnic-registered domains
const CM_NS1 = 'ns1.camdigit.cm';
const CM_NS2 = 'ns2.camdigit.cm';

// Payment gateway slugs available in your WHMCS
const VALID_PAY_METHODS = ['banktransfer', 'paypal', 'stripe', 'mailin', 'mtn_momo', 'orange_money'];

// Default currency id for new clients (1 = primary currency in WHMCS)
const DEFAULT_CURRENCY_ID = 1;

// ── Session bootstrap (own session — no WHMCS internals) ───────────────────
function cd_session_start(): void
{
    static $started = false;
    if ($started || session_status() === PHP_SESSION_ACTIVE) {
        $started = true;
        return;
    }
    session_name('CDSHOP');
    session_start();
    $started = true;
}

cd_session_start();

// ── Logged-in client (set by WHMCS clientarea after ValidateLogin) ──────────
function cd_client_id(): int
{
    return (isset($_SESSION['uid']) && (int)$_SESSION['uid'] > 0) ? (int)$_SESSION['uid'] : 0;
}

function cd_require_login(): void
{
    if (cd_client_id() === 0) {
        header('Location: ' . SITE_URL . '/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/account.php'));
        exit;
    }
}

// ── CSRF token (per session) ────────────────────────────────────────────────
function cd_csrf(): string
{
    if (empty($_SESSION['cd_csrf'])) {
        $_SESSION['cd_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cd_csrf'];
}

function cd_csrf_check(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    $sess = (string)($_SESSION['cd_csrf'] ?? '');
    if (!$sent || !$sess || !hash_equals($sess, $sent)) {
        http_response_code(403);
        die('Invalid CSRF token. Please reload the page and try again.');
    }
}

// ── WHMCS API call (server-side, credentials injected) ──────────────────────
/**
 * @param string $action  WHMCS API action (e.g. 'AddOrder', 'GetClientsDetails')
 * @param array  $params  Parameters specific to the action
 * @return array          Decoded JSON response
 * @throws RuntimeException on transport failure
 */
function whmcs_api(string $action, array $params): array
{
    $payload = array_merge($params, [
        'identifier'   => WHMCS_API_IDENTIFIER,
        'secret'       => WHMCS_API_SECRET,
        'action'       => $action,
        'responsetype' => 'json',
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => WHMCS_API_URL,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        error_log("[whmcs_api/$action] cURL $errno: $error");
        throw new RuntimeException("Could not reach billing system: $error");
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        error_log("[whmcs_api/$action] non-JSON HTTP $http: " . substr((string)$response, 0, 500));
        throw new RuntimeException('Unexpected response from billing system.');
    }

    return $decoded;
}

// ── Direct .cm WHOIS (bypass WHMCS — it cannot parse .cm reliably) ──────────
function cd_check_cm_whois(string $domain): string
{
    $fp = @fsockopen('whois.nic.cm', 43, $errno, $errstr, 10);
    if (!$fp) {
        error_log("[cd_check_cm_whois] connect failed: $errstr ($errno)");
        return 'available';
    }
    fwrite($fp, $domain . "\r\n");
    stream_set_timeout($fp, 8);
    $data = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 4096);
        if ($chunk === false || $chunk === '') break;
        $data .= $chunk;
    }
    fclose($fp);

    if ($data === '') return 'available';
    if (preg_match('/no object found/i', $data))    return 'available';
    if (preg_match('/Registry Domain ID/i', $data)) return 'unavailable';
    return 'unavailable';
}

// ── Sanitisation helpers ────────────────────────────────────────────────────
function cd_sanitize_sld(string $s): string
{
    $clean = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', trim($s)) ?? '');
    $clean = substr($clean, 0, 63);
    return (strlen($clean) >= 2 && $clean[0] !== '-' && substr($clean, -1) !== '-') ? $clean : '';
}

function cd_sanitize_text(string $s, int $max = 255): string
{
    return substr(trim(strip_tags($s)), 0, $max);
}

function cd_sanitize_phone(string $s): string
{
    return substr(preg_replace('/[^0-9+\s\-()]/', '', $s) ?? '', 0, 25);
}

function cd_sanitize_country(string $s): string
{
    $c = strtoupper(preg_replace('/[^a-zA-Z]/', '', $s) ?? '');
    return strlen($c) === 2 ? $c : '';
}

function cd_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        $v = $_SERVER[$k] ?? '';
        if ($v) {
            $ip = trim(explode(',', $v)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ── Rate limiting ───────────────────────────────────────────────────────────
function cd_rate_limit(string $bucket, int $max, int $windowSec): bool
{
    $now    = time();
    $key    = 'rl_' . $bucket;
    $hits   = (array)($_SESSION[$key . '_hits']   ?? []);
    $window = (int)  ($_SESSION[$key . '_window'] ?? 0);

    if ($now - $window > $windowSec) {
        $hits   = [];
        $window = $now;
    }
    if (count($hits) >= $max) return false;

    $hits[] = $now;
    $_SESSION[$key . '_hits']   = $hits;
    $_SESSION[$key . '_window'] = $window;
    return true;
}

// ── Country list (ISO-3166-1 alpha-2 → name) ────────────────────────────────
function cd_countries(): array
{
    return [
        'CM' => 'Cameroon', 'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria',
        'AO' => 'Angola', 'AR' => 'Argentina', 'AU' => 'Australia', 'AT' => 'Austria',
        'BE' => 'Belgium', 'BJ' => 'Benin', 'BO' => 'Bolivia', 'BR' => 'Brazil',
        'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CA' => 'Canada',
        'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile',
        'CN' => 'China', 'CO' => 'Colombia', 'CG' => 'Congo', 'CD' => 'Congo (DRC)',
        'CI' => "Côte d'Ivoire", 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
        'DK' => 'Denmark', 'DJ' => 'Djibouti', 'EC' => 'Ecuador', 'EG' => 'Egypt',
        'ER' => 'Eritrea', 'ET' => 'Ethiopia', 'FI' => 'Finland', 'FR' => 'France',
        'GA' => 'Gabon', 'GM' => 'Gambia', 'DE' => 'Germany', 'GH' => 'Ghana',
        'GR' => 'Greece', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'HK' => 'Hong Kong',
        'HU' => 'Hungary', 'IN' => 'India', 'ID' => 'Indonesia', 'IE' => 'Ireland',
        'IT' => 'Italy', 'JP' => 'Japan', 'JO' => 'Jordan', 'KE' => 'Kenya',
        'KW' => 'Kuwait', 'LB' => 'Lebanon', 'LR' => 'Liberia', 'LY' => 'Libya',
        'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'ML' => 'Mali',
        'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MX' => 'Mexico', 'MA' => 'Morocco',
        'MZ' => 'Mozambique', 'NA' => 'Namibia', 'NL' => 'Netherlands', 'NZ' => 'New Zealand',
        'NE' => 'Niger', 'NG' => 'Nigeria', 'NO' => 'Norway', 'PK' => 'Pakistan',
        'PT' => 'Portugal', 'QA' => 'Qatar', 'RW' => 'Rwanda', 'SA' => 'Saudi Arabia',
        'SN' => 'Senegal', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'ES' => 'Spain',
        'SE' => 'Sweden', 'CH' => 'Switzerland', 'TZ' => 'Tanzania', 'TG' => 'Togo',
        'TN' => 'Tunisia', 'TR' => 'Turkey', 'UG' => 'Uganda', 'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom', 'US' => 'United States', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    ];
}

// ── Language ────────────────────────────────────────────────────────────────
function cd_lang(): string
{
    if (isset($_GET['language'])) {
        $_SESSION['language'] = $_GET['language'] === 'french' ? 'french' : 'english';
    }
    return $_SESSION['language'] ?? 'english';
}

function t(string $en, string $fr): string
{
    return cd_lang() === 'french' ? $fr : $en;
}

// ── Layout — self-contained, no theme file dependency ───────────────────────
/**
 * Render the page <head>, top bar, and an optional gradient hero band.
 * Pages should call this then their content, then cd_render_foot().
 *
 *   cd_render_head('My Page')
 *   cd_render_head('My Page', 'Big Page Title', 'optional subtitle')
 */
function cd_render_head(string $title, ?string $heroTitle = null, ?string $heroSubtitle = null): void
{
    echo '<!DOCTYPE html><html lang="en"><head>',
         '<meta charset="utf-8">',
         '<meta http-equiv="X-UA-Compatible" content="IE=edge">',
         '<meta name="viewport" content="width=device-width,initial-scale=1">',
         '<title>', htmlspecialchars($title, ENT_QUOTES), ' — ', COMPANY_NAME, '</title>',
         '<link rel="icon" href="', LOGO_URL, '">',
         '<link rel="preconnect" href="https://fonts.googleapis.com">',
         '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
         '<link href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">',
         '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6g==" crossorigin="anonymous" referrerpolicy="no-referrer">';

    cd_inline_style();

    echo '</head><body>';

    cd_render_topbar();

    if ($heroTitle !== null) {
        cd_render_hero($heroTitle, $heroSubtitle);
    }

    echo '<main class="cd-main"><div class="container">';
}

function cd_render_topbar(): void
{
    $loggedIn = cd_client_id() > 0;
    ?>
<header class="cd-header">
    <div class="cd-header-top">
        <div class="container">
            <div class="cd-header-top-inner">
                <ul class="cd-contact-list">
                    <li><i class="far fa-envelope"></i> <a href="mailto:contact@camdigit.com">contact@camdigit.com</a>
                    </li>
                    <li><i class="fa fa-phone"></i> <a href="tel:+237696770074">(+237) 696 77 00 74</a></li>
                </ul>
                <p class="cd-header-promo">
                    <?= t('CAMDigit Hosting: Starting at <b>$3.49/mo</b>', 'CAMDigit Hébergement: À partir de <b>3,49 $/mois</b>') ?>
                </p>
                <ul class="cd-header-lang">
                    <li><a href="?language=english">EN</a></li>
                    <li><a href="?language=french">FR</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="cd-header-main">
        <div class="container">
            <div class="cd-header-main-inner">
                <a class="cd-logo" href="<?= SITE_URL ?>">
                    <img src="<?= LOGO_URL ?>" alt="<?= COMPANY_NAME ?>">
                </a>
                <nav class="cd-nav">
                    <a href="<?= SITE_URL ?>/"><?= t('Home','Accueil') ?></a>
                    <a href="<?= SITE_URL ?>/order-domain.php"><?= t('Domains','Domaines') ?></a>
                    <a href="<?= SITE_URL ?>/order-hosting.php"><?= t('Hosting','Hébergement') ?></a>
                    <a href="<?= SITE_URL ?>/knowledgebase.php"><?= t('Knowledge Base','Base de connaissances') ?></a>
                    <a href="<?= SITE_URL ?>/contact.php"><?= t('Contact','Contact') ?></a>
                </nav>
                <div class="cd-header-cta">
                    <?php if ($loggedIn): ?>
                    <a href="<?= SITE_URL ?>/account.php" class="cd-link-soft"><i class="fa fa-user-circle"></i>
                        <?= t('My Account','Mon Compte') ?></a>
                    <a href="<?= SITE_URL ?>/logout.php" class="theme-btn"><?= t('Log out','Déconnexion') ?> <i
                            class="fa fa-sign-out-alt"></i></a>
                    <?php else: ?>
                    <a href="<?= SITE_URL ?>/login.php" class="cd-link-soft"><i class="fa fa-sign-in-alt"></i>
                        <?= t('Log in','Connexion') ?></a>
                    <a href="<?= SITE_URL ?>/register.php" class="theme-btn"><?= t('Get Started','Commencer') ?> <i
                            class="fa fa-arrow-right"></i></a>
                    <?php endif ?>
                </div>
                <button class="cd-burger" type="button" aria-label="menu"
                    onclick="document.querySelector('.cd-nav').classList.toggle('cd-nav-open')">
                    <i class="fa fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
</header>
<?php
}

function cd_render_hero(string $title, ?string $subtitle = null): void
{
    ?>
<section class="cd-hero">
    <div class="container">
        <div class="cd-hero-inner">
            <h1 class="wow fadeInUp"><?= $title /* caller already escapes if needed */ ?></h1>
            <?php if ($subtitle): ?>
            <p class="wow fadeInUp" data-wow-delay=".15s"><?= $subtitle ?></p>
            <?php endif ?>
            <nav class="cd-breadcrumb">
                <a href="<?= SITE_URL ?>/"><?= t('Home','Accueil') ?></a>
                <i class="fa fa-chevron-right"></i>
                <span><?= htmlspecialchars($title) ?></span>
            </nav>
        </div>
    </div>
</section>
<?php
}

function cd_render_foot(): void
{
    ?>
</div>
</main>

<footer class="cd-footer">
    <div class="container">
        <div class="cd-footer-grid">
            <div class="cd-footer-col cd-footer-brand">
                <a href="<?= SITE_URL ?>"><img src="<?= LOGO_URL ?>" alt="<?= COMPANY_NAME ?>"></a>
                <p><?= t('Your trusted partner for reliable web hosting solutions. Powering websites with speed, security, and 24/7 support.',
                            'Votre partenaire de confiance pour des solutions d\'hébergement fiables. Vitesse, sécurité et support 24/7.') ?>
                </p>
                <ul class="cd-footer-contact">
                    <li><i class="fa fa-phone"></i> (+237) 696 77 00 74</li>
                    <li><i class="fa fa-envelope"></i> contact@camdigit.com</li>
                    <li><i class="fa fa-map-marker-alt"></i> Yaoundé, Cameroun</li>
                </ul>
            </div>
            <div class="cd-footer-col">
                <h3><?= t('Company','Société') ?></h3>
                <ul>
                    <li><a href="<?= SITE_URL ?>/"><i class="fa fa-chevron-right"></i> <?= t('Home','Accueil') ?></a>
                    </li>
                    <li><a href="<?= SITE_URL ?>/contact.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Contact','Contact') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/order-hosting.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Order','Commander') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/knowledgebase.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Knowledge Base','Base de connaissances') ?></a></li>
                </ul>
            </div>
            <div class="cd-footer-col">
                <h3><?= t('Hosting','Hébergement') ?></h3>
                <ul>
                    <li><a href="<?= SITE_URL ?>/order-hosting.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Shared Hosting','Hébergement partagé') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/order-hosting.php"><i class="fa fa-chevron-right"></i> VPS</a></li>
                    <li><a href="<?= SITE_URL ?>/order-hosting.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Cloud Hosting','Cloud') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/order-domain.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Domains','Domaines') ?></a></li>
                </ul>
            </div>
            <div class="cd-footer-col">
                <h3><?= t('Support','Support') ?></h3>
                <ul>
                    <li><a href="<?= SITE_URL ?>/submitticket.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Submit Ticket','Ouvrir un ticket') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/knowledgebase.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Knowledge Base','Base de connaissances') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/serverstatus.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Server Status','État des serveurs') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/announcements.php"><i class="fa fa-chevron-right"></i>
                            <?= t('Announcements','Annonces') ?></a></li>
                </ul>
            </div>
            <div class="cd-footer-col">
                <h3><?= t('Newsletter','Newsletter') ?></h3>
                <p><?= t('Sign up for the latest updates and offers.','Inscrivez-vous pour les dernières offres.') ?>
                </p>
                <form class="cd-newsletter"
                    onsubmit="event.preventDefault();this.querySelector('button').innerHTML='<i class=fa fa-check></i>'">
                    <input type="email" placeholder="<?= t('Your email','Votre email') ?>" required>
                    <button type="submit"><i class="fa fa-paper-plane"></i></button>
                </form>
                <div class="cd-socials">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
    </div>
    <div class="cd-footer-bottom">
        <div class="container">
            <span>© <?= date('Y') ?> <?= COMPANY_NAME ?>.
                <?= t('All rights reserved.','Tous droits réservés.') ?></span>
            <span><?= t('Powered by','Propulsé par') ?> CAMDigit</span>
        </div>
    </div>
</footer>
</body>

</html>
<?php
}

/**
 * Inline stylesheet that maps the cd-* utility classes to the theme tokens
 * (--theme, --theme2, --header, etc.) defined via :root in this same function.
 * All styling is self-contained — no external theme files required.
 */
function cd_inline_style(): void
{ ?>
<style>
/* ── Design tokens ──────────────────────────────────────────────────────── */
:root {
    --theme: #236a25;
    --theme2: #ffa31a;
    --header: #0f0d1d
}

/* ── Responsive container (no Bootstrap dependency) ────────────────────── */
.container {
    width: 100%;
    padding-right: 15px;
    padding-left: 15px;
    margin-right: auto;
    margin-left: auto
}

@media (min-width:576px) {
    .container {
        max-width: 540px
    }
}

@media (min-width:768px) {
    .container {
        max-width: 720px
    }
}

@media (min-width:992px) {
    .container {
        max-width: 960px
    }
}

@media (min-width:1200px) {
    .container {
        max-width: 1140px
    }
}

@media (min-width:1400px) {
    .container {
        max-width: 1320px
    }
}

/* ── Reset ──────────────────────────────────────────────────────────────── */
body {
    font-family: 'Jost', sans-serif;
    background: #f3f7fb;
    color: #0f0d1d;
    margin: 0;
    line-height: 1.6
}

* {
    box-sizing: border-box
}

a {
    text-decoration: none;
    color: var(--theme, #236a25)
}

a:hover {
    color: var(--theme2, #ffa31a)
}

/* ── Top bar (mirrors header-section-1) ────────────────────────────────── */
.cd-header {
    position: relative;
    z-index: 100;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .04)
}

.cd-header-top {
    background: var(--header, #0f0d1d);
    color: #fff;
    font-size: 13px;
    padding: 10px 0
}

.cd-header-top-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px
}

.cd-header-top a {
    color: #fff;
    opacity: .85
}

.cd-header-top a:hover {
    opacity: 1;
    color: var(--theme2, #ffa31a)
}

.cd-contact-list,
.cd-header-lang {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: 18px
}

.cd-contact-list i,
.cd-header-lang i {
    margin-right: 6px;
    color: var(--theme2, #ffa31a)
}

.cd-header-promo {
    margin: 0;
    font-size: 13px
}

.cd-header-promo b {
    color: var(--theme2, #ffa31a)
}

.cd-header-main {
    background: #fff;
    padding: 14px 0;
    border-bottom: 1px solid #eef2f7
}

.cd-header-main-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px
}

.cd-logo img {
    height: 48px;
    width: auto
}

.cd-nav {
    display: flex;
    gap: 28px;
    flex: 1;
    justify-content: center
}

.cd-nav a {
    color: #0f0d1d;
    font-weight: 500;
    font-size: 15px;
    position: relative;
    padding: 8px 0;
    transition: color .2s
}

.cd-nav a:hover {
    color: var(--theme, #236a25)
}

.cd-nav a::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 2px;
    background: var(--theme, #236a25);
    transform: scaleX(0);
    transform-origin: center;
    transition: transform .25s
}

.cd-nav a:hover::after {
    transform: scaleX(1)
}

.cd-header-cta {
    display: flex;
    align-items: center;
    gap: 16px
}

.cd-link-soft {
    color: #0f0d1d;
    font-weight: 500;
    font-size: 14px
}

.cd-link-soft i {
    margin-right: 6px;
    color: var(--theme, #236a25)
}

.cd-burger {
    display: none;
    background: none;
    border: 0;
    color: #0f0d1d;
    font-size: 22px;
    cursor: pointer
}

@media(max-width:991px) {
    .cd-nav {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        flex-direction: column;
        padding: 20px;
        gap: 12px;
        display: none;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .08)
    }

    .cd-nav-open {
        display: flex
    }

    .cd-burger {
        display: block;
        order: 3
    }

    .cd-header-cta {
        margin-left: auto
    }
}

@media(max-width:575px) {
    .cd-header-top-inner {
        justify-content: center;
        text-align: center
    }

    .cd-header-promo {
        display: none
    }

    .cd-header-cta .cd-link-soft {
        display: none
    }
}

/* ── Hero / sub-header ─────────────────────────────────────────────────── */
.cd-hero {
    position: relative;
    background: linear-gradient(135deg, #0f0d1d 0%, #1a1a3e 50%, #236a25 100%);
    color: #fff;
    padding: 70px 0 50px;
    overflow: hidden
}

.cd-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255, 163, 26, .15), transparent 50%), radial-gradient(circle at 80% 20%, rgba(35, 106, 37, .25), transparent 50%);
    pointer-events: none
}

.cd-hero-inner {
    position: relative;
    text-align: center;
    max-width: 780px;
    margin: 0 auto
}

.cd-hero h1 {
    font-size: 42px;
    font-weight: 700;
    margin: 0 0 12px;
    color: #fff;
    line-height: 1.15
}

.cd-hero p {
    font-size: 18px;
    color: rgba(255, 255, 255, .85);
    margin: 0 auto 18px;
    max-width: 600px
}

.cd-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: rgba(255, 255, 255, .7);
    font-size: 14px;
    margin-top: 8px
}

.cd-breadcrumb a {
    color: rgba(255, 255, 255, .9)
}

.cd-breadcrumb a:hover {
    color: var(--theme2, #ffa31a)
}

.cd-breadcrumb i {
    font-size: 11px;
    color: var(--theme2, #ffa31a)
}

@media(max-width:575px) {
    .cd-hero h1 {
        font-size: 30px
    }

    .cd-hero p {
        font-size: 15px
    }
}

/* ── Main / cards ──────────────────────────────────────────────────────── */
.cd-main {
    padding: 50px 0 70px;
    background: #f3f7fb
}

.cd-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(15, 13, 29, .06);
    padding: 36px;
    margin-bottom: 24px;
    border: 1px solid rgba(0, 0, 0, .03)
}

.cd-card h1 {
    margin: 0 0 18px;
    font-size: 28px;
    font-weight: 700;
    color: #0f0d1d;
    line-height: 1.25
}

.cd-card h2 {
    margin: 0 0 14px;
    font-size: 22px;
    font-weight: 600;
    color: #0f0d1d
}

.cd-card h3 {
    font-size: 17px;
    font-weight: 600;
    color: #0f0d1d;
    margin: 0 0 10px
}

@media(max-width:575px) {
    .cd-card {
        padding: 22px;
        border-radius: 10px
    }
}

/* ── Buttons ───────────────────────────────────────────────────────────── */
.cd-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--theme, #236a25);
    color: #fff;
    border: 0;
    border-radius: 30px;
    padding: 13px 28px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all .25s;
    text-decoration: none;
    font-family: inherit
}

.cd-btn:hover {
    background: var(--theme2, #ffa31a);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 163, 26, .35)
}

.cd-btn-secondary {
    background: #fff;
    color: #0f0d1d;
    border: 1.5px solid #e3e8f0
}

.cd-btn-secondary:hover {
    background: #0f0d1d;
    color: #fff;
    border-color: #0f0d1d;
    box-shadow: 0 8px 20px rgba(15, 13, 29, .18)
}

.cd-btn-ghost {
    background: transparent;
    color: var(--theme, #236a25);
    border: 1.5px solid var(--theme, #236a25)
}

.cd-btn-ghost:hover {
    background: var(--theme, #236a25);
    color: #fff
}

/* ── Forms ─────────────────────────────────────────────────────────────── */
.cd-input {
    width: 100%;
    padding: 13px 16px;
    border: 1.5px solid #e3e8f0;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
    background: #fff;
    color: #0f0d1d;
    transition: all .2s
}

.cd-input:focus {
    outline: none;
    border-color: var(--theme, #236a25);
    box-shadow: 0 0 0 3px rgba(35, 106, 37, .12)
}

.cd-input::placeholder {
    color: #9aa5b8
}

select.cd-input {
    background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20fill%3D%22%230f0d1d%22%20d%3D%22M7%2010l5%205%205-5z%22%2F%3E%3C%2Fsvg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 36px;
    appearance: none
}

.cd-row {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 16px
}

.cd-row>div {
    flex: 1 1 220px
}

.cd-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #445375;
    margin-bottom: 7px;
    letter-spacing: .01em
}

/* ── Alerts ────────────────────────────────────────────────────────────── */
.cd-alert {
    padding: 16px 18px;
    border-radius: 10px;
    margin-bottom: 18px;
    display: flex;
    align-items: flex-start;
    gap: 12px
}

.cd-alert::before {
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    font-size: 18px;
    flex-shrink: 0
}

.cd-alert-error {
    background: #fef2f2;
    color: #991b1b;
    border-left: 4px solid #dc2626
}

.cd-alert-error::before {
    content: '\f06a';
    color: #dc2626
}

.cd-alert-ok {
    background: #f0fdf4;
    color: #166534;
    border-left: 4px solid var(--theme, #236a25)
}

.cd-alert-ok::before {
    content: '\f058';
    color: var(--theme, #236a25)
}

/* ── Tables ────────────────────────────────────────────────────────────── */
.cd-card table {
    width: 100%;
    border-collapse: collapse
}

.cd-card table thead tr {
    border-bottom: 2px solid #e3e8f0
}

.cd-card table thead th {
    padding: 12px 10px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #445375;
    text-transform: uppercase;
    letter-spacing: .05em
}

.cd-card table tbody tr {
    border-bottom: 1px solid #f0f4f8;
    transition: background .15s
}

.cd-card table tbody tr:hover {
    background: #f7fafc
}

.cd-card table tbody td {
    padding: 14px 10px;
    font-size: 14px;
    color: #0f0d1d
}

.cd-card table tbody td a {
    color: var(--theme, #236a25);
    font-weight: 600
}

/* ── Status pills ──────────────────────────────────────────────────────── */
.cd-pill {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em
}

.cd-pill-ok {
    background: rgba(35, 106, 37, .12);
    color: var(--theme, #236a25)
}

.cd-pill-warn {
    background: rgba(255, 163, 26, .15);
    color: #b8730a
}

.cd-pill-err {
    background: rgba(220, 38, 38, .12);
    color: #991b1b
}

.cd-pill-neutral {
    background: #f0f4f8;
    color: #445375
}

/* ── Product / pricing cards (used in order-hosting grid) ──────────────── */
.cd-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 24px;
    margin-top: 18px
}

.cd-product-card {
    background: #fff;
    border-radius: 14px;
    padding: 32px 28px;
    border: 1.5px solid #eef2f7;
    text-align: center;
    transition: all .3s;
    position: relative;
    overflow: hidden
}

.cd-product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--theme, #236a25), var(--theme2, #ffa31a));
    opacity: 0;
    transition: opacity .3s
}

.cd-product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 40px rgba(15, 13, 29, .12);
    border-color: transparent
}

.cd-product-card:hover::before {
    opacity: 1
}

.cd-product-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: #0f0d1d;
    margin: 0 0 10px
}

.cd-product-card .cd-product-desc {
    color: #445375;
    font-size: 14px;
    min-height: 56px;
    margin-bottom: 18px
}

.cd-product-card .cd-product-price {
    font-size: 36px;
    font-weight: 700;
    color: var(--theme, #236a25);
    margin: 14px 0 4px;
    line-height: 1
}

.cd-product-card .cd-product-price small {
    font-size: 14px;
    color: #9aa5b8;
    font-weight: 400
}

.cd-product-card .cd-btn {
    width: 100%;
    justify-content: center;
    margin-top: 18px
}

/* ── Hero search (domain) ──────────────────────────────────────────────── */
.cd-search-bar {
    display: flex;
    gap: 0;
    background: #fff;
    border-radius: 50px;
    padding: 6px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, .08);
    max-width: 680px;
    margin: 0 auto
}

.cd-search-bar .cd-input {
    border: 0;
    border-radius: 50px 0 0 50px;
    background: transparent;
    flex: 1;
    font-size: 16px;
    padding: 14px 22px
}

.cd-search-bar .cd-input:focus {
    box-shadow: none
}

.cd-search-bar select.cd-input {
    flex: 0 0 110px;
    border-left: 1px solid #eef2f7;
    border-radius: 0;
    background-color: #fafbfc;
    font-weight: 600;
    padding: 14px 32px 14px 16px
}

.cd-search-bar .cd-btn {
    border-radius: 50px;
    padding: 14px 28px;
    flex-shrink: 0
}

@media(max-width:575px) {
    .cd-search-bar {
        flex-direction: column;
        border-radius: 14px;
        padding: 14px;
        gap: 10px
    }

    .cd-search-bar .cd-input,
    .cd-search-bar select.cd-input {
        border-radius: 8px;
        border: 1.5px solid #e3e8f0;
        background: #fff
    }

    .cd-search-bar .cd-btn {
        border-radius: 8px;
        justify-content: center
    }
}

/* ── Footer (mirrors footer-section) ───────────────────────────────────── */
.cd-footer {
    background: linear-gradient(180deg, #0f0d1d 0%, #0a0817 100%);
    color: #a8b0c5;
    padding: 64px 0 0;
    margin-top: 60px
}

.cd-footer-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1fr 1.4fr;
    gap: 36px
}

.cd-footer-col h3 {
    color: #fff;
    font-size: 17px;
    font-weight: 600;
    margin: 0 0 18px;
    position: relative;
    padding-bottom: 10px
}

.cd-footer-col h3::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 38px;
    height: 2px;
    background: var(--theme2, #ffa31a)
}

.cd-footer-col ul {
    list-style: none;
    padding: 0;
    margin: 0
}

.cd-footer-col ul li {
    margin-bottom: 10px
}

.cd-footer-col ul li a {
    color: #a8b0c5;
    font-size: 14px;
    transition: color .2s, padding .2s
}

.cd-footer-col ul li a i {
    color: var(--theme2, #ffa31a);
    margin-right: 8px;
    font-size: 10px
}

.cd-footer-col ul li a:hover {
    color: #fff;
    padding-left: 4px
}

.cd-footer-brand img {
    height: 54px;
    width: auto;
    margin-bottom: 14px;
    filter: brightness(0) invert(1)
}

.cd-footer-brand p {
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 16px
}

.cd-footer-contact {
    list-style: none;
    padding: 0;
    margin: 0
}

.cd-footer-contact li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    margin-bottom: 8px
}

.cd-footer-contact li i {
    color: var(--theme2, #ffa31a);
    width: 18px
}

.cd-newsletter {
    display: flex;
    gap: 0;
    background: rgba(255, 255, 255, .05);
    border-radius: 30px;
    padding: 5px;
    margin: 12px 0 16px
}

.cd-newsletter input {
    flex: 1;
    border: 0;
    background: transparent;
    color: #fff;
    padding: 10px 16px;
    font-family: inherit;
    font-size: 14px;
    outline: none
}

.cd-newsletter input::placeholder {
    color: rgba(255, 255, 255, .4)
}

.cd-newsletter button {
    background: var(--theme2, #ffa31a);
    color: #fff;
    border: 0;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    cursor: pointer;
    font-size: 14px;
    transition: transform .2s
}

.cd-newsletter button:hover {
    transform: scale(1.1)
}

.cd-socials {
    display: flex;
    gap: 10px;
    margin-top: 8px
}

.cd-socials a {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .08);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: all .25s
}

.cd-socials a:hover {
    background: var(--theme2, #ffa31a);
    transform: translateY(-3px)
}

.cd-footer-bottom {
    margin-top: 50px;
    border-top: 1px solid rgba(255, 255, 255, .08);
    padding: 22px 0;
    font-size: 13px;
    color: #7a8499
}

.cd-footer-bottom .container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px
}

@media(max-width:991px) {
    .cd-footer-grid {
        grid-template-columns: 1fr 1fr
    }
}

@media(max-width:575px) {
    .cd-footer-grid {
        grid-template-columns: 1fr;
        gap: 28px
    }

    .cd-footer {
        padding-top: 48px
    }
}

/* ── Misc ──────────────────────────────────────────────────────────────── */
.cd-section-title {
    text-align: center;
    margin-bottom: 30px
}

.cd-section-title h2 {
    font-size: 30px;
    font-weight: 700;
    color: #0f0d1d;
    margin: 0 0 10px
}

.cd-section-title p {
    color: #445375;
    font-size: 15px;
    max-width: 560px;
    margin: 0 auto
}

.cd-divider {
    height: 1px;
    background: #eef2f7;
    margin: 24px 0
}

.cd-muted {
    color: #6b7785;
    font-size: 14px
}

.cd-stat {
    display: inline-block;
    background: #f3f7fb;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 13px;
    color: #445375;
    margin-right: 8px
}

/* ── Override WHMCS theme-btn so it works inline too ───────────────────── */
.theme-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--theme2, #ffa31a);
    color: #fff;
    border: 0;
    border-radius: 30px;
    padding: 11px 24px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all .25s;
    cursor: pointer;
    font-family: inherit
}

.theme-btn:hover {
    background: var(--theme, #236a25);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(35, 106, 37, .3)
}

.theme-btn i {
    margin-left: 4px
}

/* ── Progress steps ──────────────────────────────────────────────────────── */
.cd-progress {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 30px;
    padding: 0 10px
}

.cd-prog-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px
}

.cd-prog-step-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 2px solid #e3e8f0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #9aa5b8;
    transition: all .3s
}

.cd-prog-step.active .cd-prog-step-icon {
    border-color: var(--theme, #236a25);
    background: var(--theme, #236a25);
    color: #fff;
    box-shadow: 0 4px 14px rgba(35, 106, 37, .3)
}

.cd-prog-step.done .cd-prog-step-icon {
    border-color: var(--theme, #236a25);
    background: var(--theme, #236a25);
    color: #fff
}

.cd-prog-step-label {
    font-size: 12px;
    font-weight: 500;
    color: #9aa5b8;
    white-space: nowrap
}

.cd-prog-step.active .cd-prog-step-label {
    color: var(--theme, #236a25);
    font-weight: 600
}

.cd-prog-step.done .cd-prog-step-label {
    color: var(--theme, #236a25)
}

.cd-prog-line {
    flex: 1;
    height: 2px;
    background: #e3e8f0;
    min-width: 40px;
    margin: 0 8px 26px;
    transition: background .3s
}

.cd-prog-line.done {
    background: var(--theme, #236a25)
}

/* ── Domain availability result ─────────────────────────────────────────── */
.cd-avail {
    display: none;
    margin-top: 18px;
    border-radius: 10px;
    border: 2px solid #e3e8f0;
    padding: 16px 18px
}

.cd-avail.available {
    border-color: var(--theme, #236a25);
    background: #f0fdf4
}

.cd-avail.taken {
    border-color: #dc2626;
    background: #fef2f2
}

.cd-avail-inner {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap
}

.cd-avail-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0
}

.available .cd-avail-icon {
    background: rgba(35, 106, 37, .12);
    color: var(--theme, #236a25)
}

.taken .cd-avail-icon {
    background: rgba(220, 38, 38, .12);
    color: #dc2626
}

.cd-avail-info {
    flex: 1
}

.cd-avail-domain {
    font-size: 17px;
    font-weight: 700;
    color: #0f0d1d;
    margin: 0
}

.cd-avail-status {
    font-size: 13px;
    margin-top: 3px;
    margin-bottom: 0
}

.available .cd-avail-status {
    color: var(--theme, #236a25)
}

.taken .cd-avail-status {
    color: #dc2626
}

/* ── SLD search input with TLD overlay ──────────────────────────────────── */
.cd-sld-wrap {
    position: relative;
    flex: 1
}

.cd-sld-wrap .cd-input {
    padding-right: 54px
}

.cd-sld-tld {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: 700;
    font-size: 14px;
    color: var(--theme, #236a25);
    pointer-events: none
}

/* ── Order bar (domain reminder) ─────────────────────────────────────────── */
.cd-order-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f3f7fb;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 22px;
    flex-wrap: wrap;
    border: 1px solid #e3e8f0
}

.cd-order-bar i {
    color: var(--theme, #236a25)
}

.cd-order-bar-domain {
    font-weight: 700;
    color: var(--theme, #236a25);
    flex: 1
}

.cd-change-btn {
    background: none;
    border: none;
    color: var(--theme2, #ffa31a);
    font-size: 13px;
    cursor: pointer;
    padding: 0;
    font-family: inherit;
    font-weight: 600
}

.cd-change-btn:hover {
    text-decoration: underline
}

/* ── Password strength ───────────────────────────────────────────────────── */
.cd-pw-wrap {
    position: relative
}

.cd-pw-wrap .cd-input {
    padding-right: 46px
}

.cd-eye-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #9aa5b8;
    cursor: pointer;
    padding: 0;
    font-size: 15px
}

.cd-eye-btn:hover {
    color: var(--theme, #236a25)
}

.cd-pw-strength {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
    margin-bottom: 14px
}

.cd-pw-bar {
    flex: 1;
    height: 4px;
    border-radius: 4px;
    background: #e3e8f0;
    transition: background .3s
}

.cd-pw-bar.weak {
    background: #dc2626
}

.cd-pw-bar.fair {
    background: #f59e0b
}

.cd-pw-bar.strong {
    background: var(--theme, #236a25)
}

.cd-pw-label {
    font-size: 12px;
    color: #9aa5b8;
    min-width: 42px
}

/* ── Logged-in notice ────────────────────────────────────────────────────── */
.cd-logged-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    background: #f0fdf4;
    border: 1.5px solid var(--theme, #236a25);
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
    color: #166534
}

.cd-logged-notice i {
    font-size: 17px;
    color: var(--theme, #236a25)
}

/* ── Client info (logged-in detail block) ───────────────────────────────── */
.cd-client-info {
    background: #f3f7fb;
    border-radius: 10px;
    border: 1px solid #e3e8f0;
    margin-bottom: 22px;
    overflow: hidden
}

.cd-client-info-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 1px solid #e3e8f0;
    font-size: 14px
}

.cd-client-info-row:last-child {
    border-bottom: none
}

.cd-client-info-label {
    width: 80px;
    color: #6b7785;
    font-weight: 500;
    flex-shrink: 0
}

/* ── Success / confirmation panel ───────────────────────────────────────── */
.cd-success {
    text-align: center;
    padding: 24px 0 16px
}

.cd-success-icon {
    font-size: 64px;
    color: var(--theme, #236a25);
    animation: cdPopIn .5s cubic-bezier(.175, .885, .32, 1.275)
}

.cd-success h2 {
    font-size: 26px;
    font-weight: 700;
    margin: 14px 0 8px;
    color: #0f0d1d
}

.cd-success p {
    color: #6b7785;
    margin-bottom: 22px
}

.cd-success-details {
    background: #f3f7fb;
    border-radius: 10px;
    padding: 16px 20px;
    max-width: 420px;
    margin: 0 auto 24px;
    text-align: left
}

.cd-detail-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 7px 0;
    border-bottom: 1px solid #e3e8f0;
    font-size: 14px
}

.cd-detail-row:last-child {
    border-bottom: none
}

.cd-detail-row strong {
    color: #0f0d1d
}

.cd-success-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap
}

/* ── Terms checkbox row ──────────────────────────────────────────────────── */
.cd-terms {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 22px;
    font-size: 14px;
    color: #445375
}

.cd-terms input[type="checkbox"] {
    width: 17px;
    height: 17px;
    margin-top: 2px;
    accent-color: var(--theme, #236a25);
    flex-shrink: 0;
    cursor: pointer
}

/* ── Form section heading inside card ───────────────────────────────────── */
.cd-form-section {
    font-size: 14px;
    font-weight: 600;
    color: #0f0d1d;
    margin: 22px 0 12px;
    display: flex;
    align-items: center;
    gap: 8px
}

.cd-form-section i {
    color: var(--theme2, #ffa31a);
    font-size: 15px
}

/* ── Button loading state ────────────────────────────────────────────────── */
.cd-btn.loading .cd-btn-label {
    opacity: .6
}

.cd-btn.loading .cd-spinner {
    display: inline-flex
}

.cd-spinner {
    display: none;
    align-items: center
}

.cd-spin-icon {
    width: 15px;
    height: 15px;
    border: 2px solid rgba(255, 255, 255, .4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: cdSpin .7s linear infinite;
    display: inline-block
}

/* ── Feature strip (homepage-style icon row) ─────────────────────────────── */
.cd-feature-strip {
    display: flex;
    gap: 0;
    margin-top: 24px;
    border-top: 1px solid #eef2f7;
    padding-top: 18px;
    flex-wrap: wrap
}

.cd-feature-item {
    flex: 1;
    min-width: 110px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 6px 10px
}

.cd-feature-item i {
    font-size: 19px;
    color: var(--theme, #236a25);
    flex-shrink: 0;
    margin-top: 2px
}

.cd-feature-item h4 {
    font-size: 13px;
    font-weight: 600;
    color: #0f0d1d;
    margin: 0 0 2px
}

.cd-feature-item p {
    font-size: 12px;
    color: #6b7785;
    margin: 0
}

/* ── Stat boxes (dashboard) ─────────────────────────────────────────────── */
.cd-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px
}

.cd-stat-box {
    background: #fff;
    border-radius: 12px;
    border: 1.5px solid #eef2f7;
    padding: 20px 16px;
    text-align: center;
    transition: box-shadow .2s
}

.cd-stat-box:hover {
    box-shadow: 0 4px 18px rgba(15, 13, 29, .08)
}

.cd-stat-box i {
    font-size: 24px;
    color: var(--theme2, #ffa31a);
    margin-bottom: 8px;
    display: block
}

.cd-stat-box .cd-stat-num {
    font-size: 28px;
    font-weight: 700;
    color: #0f0d1d;
    line-height: 1.1
}

.cd-stat-box .cd-stat-lbl {
    font-size: 12px;
    color: #6b7785;
    margin-top: 4px
}

/* ── Auth card split layout ──────────────────────────────────────────────── */
.cd-auth-wrap {
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    gap: 0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15, 13, 29, .06);
    border: 1px solid rgba(0, 0, 0, .03)
}

.cd-auth-side {
    background: linear-gradient(160deg, #0f0d1d 0%, #1a1a3e 50%, #236a25 100%);
    color: #fff;
    padding: 48px 36px;
    display: flex;
    flex-direction: column;
    justify-content: center
}

.cd-auth-side h2 {
    font-size: 26px;
    font-weight: 700;
    margin: 0 0 12px;
    line-height: 1.2
}

.cd-auth-side p {
    font-size: 14px;
    color: rgba(255, 255, 255, .75);
    margin: 0 0 28px;
    line-height: 1.7
}

.cd-auth-feature {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    font-size: 14px;
    color: rgba(255, 255, 255, .88)
}

.cd-auth-feature i {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 163, 26, .18);
    color: var(--theme2, #ffa31a);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 13px
}

.cd-auth-form {
    background: #fff;
    padding: 42px 36px
}

@media(max-width:767px) {
    .cd-auth-wrap {
        grid-template-columns: 1fr
    }

    .cd-auth-side {
        padding: 28px 24px
    }

    .cd-auth-form {
        padding: 28px 24px
    }
}

@media(max-width:575px) {
    .cd-auth-wrap {
        border-radius: 10px
    }

    .cd-auth-side {
        display: none
    }
}

/* ── Keyframes ───────────────────────────────────────────────────────────── */
@keyframes cdPopIn {
    0% {
        transform: scale(0);
        opacity: 0
    }

    80% {
        transform: scale(1.1)
    }

    100% {
        transform: scale(1);
        opacity: 1
    }
}

@keyframes cdSpin {
    to {
        transform: rotate(360deg)
    }
}

/* ── TLD suggestions grid ───────────────────────────────────────────────── */
.cd-suggestions-wrap {
    margin-top: 22px;
    border-top: 1px solid #eef2f7;
    padding-top: 18px
}

.cd-suggestions-wrap h4 {
    font-size: 12px;
    font-weight: 700;
    color: #6b7785;
    margin: 0 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: .06em
}

.cd-tld-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px
}

@media (max-width: 700px) {
    .cd-tld-grid {
        grid-template-columns: repeat(2, 1fr)
    }
}

@media (max-width: 400px) {
    .cd-tld-grid {
        grid-template-columns: 1fr
    }
}

.cd-tld-item {
    background: #fff;
    border-radius: 12px;
    border: 2px solid #eef2f7;
    padding: 16px 14px 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: all .22s;
    position: relative;
    overflow: hidden
}

.cd-tld-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--theme, #236a25);
    opacity: 0;
    transition: opacity .22s
}

.cd-tld-item.available {
    border-color: var(--theme, #236a25)
}

.cd-tld-item.available::before {
    opacity: 1
}

.cd-tld-item.available:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 28px rgba(35, 106, 37, .14)
}

.cd-tld-item.taken {
    opacity: .6;
    background: #fafbfc
}

.cd-tld-item-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 2px
}

.cd-tld-ext {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -.3px
}

.available .cd-tld-ext {
    color: var(--theme, #236a25)
}

.taken .cd-tld-ext {
    color: #9aa5b8
}

.cd-tld-item-domain {
    font-size: 11px;
    color: #9aa5b8;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-bottom: 8px
}

.cd-tld-item-badge {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 3px 7px;
    border-radius: 20px;
    flex-shrink: 0;
    margin-top: 3px
}

.available .cd-tld-item-badge {
    background: rgba(35, 106, 37, .12);
    color: var(--theme, #236a25)
}

.taken .cd-tld-item-badge {
    background: #f0f4f8;
    color: #9aa5b8
}

.cd-tld-item .cd-btn {
    padding: 7px 12px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 20px;
    width: 100%;
    justify-content: center;
    margin-top: auto
}

.cd-tld-loading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #9aa5b8;
    padding: 6px 0
}

.cd-spin-sm {
    width: 13px;
    height: 13px;
    border: 2px solid #e3e8f0;
    border-top-color: var(--theme, #236a25);
    border-radius: 50%;
    animation: cdSpin .7s linear infinite;
    display: inline-block
}

.hidden {
    display: none !important
}
</style>
<?php }