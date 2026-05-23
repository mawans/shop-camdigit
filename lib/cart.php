<?php
/**
 * CamDigit — session-backed shopping cart
 * ─────────────────────────────────────────────────────────────────────────────
 * Required by cart.php, checkout.php, order-domain.php, order-hosting.php.
 *
 *   $_SESSION['cd_cart'] = [
 *     'currency' => 'XAF',
 *     'hosting'  => [
 *         'pid'           => 12,
 *         'name'          => 'Hébergement Starter',
 *         'description'   => '...',
 *         'billingcycle'  => 'annually',
 *         'years'         => 1,
 *         'price'         => 41.88,
 *         'original'      => 71.88,
 *         'features'      => ['SSL offert à vie', 'Sauvegardes quotidiennes', ...],
 *         'pricing'       => [ 'monthly' => x, 'annually' => y, ... ],
 *     ],
 *     'domains'  => [
 *         'ghhh.site' => [
 *             'domain'    => 'ghhh.site',
 *             'type'      => 'register',  // or 'transfer'
 *             'years'     => 1,
 *             'price'     => 0.99,
 *             'original'  => 28.99,
 *             'free'      => false,
 *         ],
 *     ],
 *     'main_domain' => 'ghhh.site',
 *     'promo'       => null,
 *   ];
 *
 * Free-domain rule: when a hosting plan is in the cart, the first registered
 * domain ≤ FREE_DOMAIN_PRICE_CAP is marked free (price 0). Recomputed by
 * cd_cart_recompute() after any mutation.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);

const FREE_DOMAIN_PRICE_CAP = 15.00; // any TLD up to this gets first one free w/ hosting

function cd_cart(): array
{
    if (!isset($_SESSION['cd_cart']) || !is_array($_SESSION['cd_cart'])) {
        $_SESSION['cd_cart'] = [
            'currency'    => 'XAF',
            'hosting'     => null,
            'domains'     => [],
            'main_domain' => null,
            'promo'       => null,
        ];
    }
    return $_SESSION['cd_cart'];
}

function cd_cart_save(array $cart): void
{
    $_SESSION['cd_cart'] = $cart;
}

function cd_cart_clear(): void
{
    unset($_SESSION['cd_cart']);
}

function cd_cart_is_empty(): bool
{
    $c = cd_cart();
    return empty($c['hosting']) && empty($c['domains']);
}

function cd_cart_count(): int
{
    $c = cd_cart();
    return (int)(!empty($c['hosting'])) + count($c['domains'] ?? []);
}

// ── Hosting ──────────────────────────────────────────────────────────────────
function cd_cart_add_hosting(array $hosting): void
{
    $c = cd_cart();
    $c['hosting'] = [
        'pid'          => (int)($hosting['pid'] ?? 0),
        'name'         => (string)($hosting['name'] ?? 'Hosting'),
        'description'  => (string)($hosting['description'] ?? ''),
        'billingcycle' => (string)($hosting['billingcycle'] ?? 'annually'),
        'years'        => (int)($hosting['years'] ?? 1),
        'price'        => (float)($hosting['price'] ?? 0),
        'original'     => (float)($hosting['original'] ?? $hosting['price'] ?? 0),
        'features'     => (array)($hosting['features'] ?? cd_cart_default_features()),
        'pricing'      => (array)($hosting['pricing'] ?? []),
    ];
    if (!empty($hosting['currency'])) $c['currency'] = (string)$hosting['currency'];
    cd_cart_save($c);
    cd_cart_recompute();
}

function cd_cart_remove_hosting(): void
{
    $c = cd_cart();
    $c['hosting'] = null;
    cd_cart_save($c);
    cd_cart_recompute();
}

function cd_cart_default_features(): array
{
    return [
        t('Free privacy protection with every eligible domain',
          'Protection de la vie privée offerte avec chaque domaine éligible'),
        t('Free lifetime SSL certificate (https)',
          'Certificat SSL (https) offert à vie'),
        t('Free lifetime daily backups',
          'Sauvegardes quotidiennes offertes à vie'),
    ];
}

/** Change duration of the hosting plan (years). Picks the matching billing cycle. */
function cd_cart_set_hosting_years(int $years): void
{
    $c = cd_cart();
    if (!$c['hosting']) return;
    $map = [
        1 => 'annually', 2 => 'biennially', 3 => 'triennially',
    ];
    $cycle = $map[$years] ?? 'annually';
    $pricing = $c['hosting']['pricing'] ?? [];
    if (isset($pricing[$cycle])) {
        // pricing block: stored values keyed by cycle, may be ['price'=>x,'original'=>y]
        $p = $pricing[$cycle];
        $c['hosting']['price']    = (float)($p['price']    ?? $p);
        $c['hosting']['original'] = (float)($p['original'] ?? $c['hosting']['price']);
    }
    $c['hosting']['years']        = $years;
    $c['hosting']['billingcycle'] = $cycle;
    cd_cart_save($c);
    cd_cart_recompute();
}

// ── Domains ──────────────────────────────────────────────────────────────────
function cd_cart_add_domain(array $d): void
{
    $c = cd_cart();
    $domain = strtolower(trim((string)($d['domain'] ?? '')));
    if ($domain === '') return;
    $c['domains'][$domain] = [
        'domain'   => $domain,
        'type'     => in_array($d['type'] ?? 'register', ['register','transfer'], true) ? $d['type'] : 'register',
        'years'    => max(1, (int)($d['years'] ?? 1)),
        'price'    => (float)($d['price']    ?? 0),
        'original' => (float)($d['original'] ?? $d['price'] ?? 0),
        'free'     => false,
    ];
    if ($c['main_domain'] === null) $c['main_domain'] = $domain;
    cd_cart_save($c);
    cd_cart_recompute();
}

function cd_cart_remove_domain(string $domain): void
{
    $domain = strtolower($domain);
    $c = cd_cart();
    unset($c['domains'][$domain]);
    if ($c['main_domain'] === $domain) {
        $c['main_domain'] = $c['domains'] ? array_key_first($c['domains']) : null;
    }
    cd_cart_save($c);
    cd_cart_recompute();
}

function cd_cart_set_domain_years(string $domain, int $years): void
{
    $domain = strtolower($domain);
    $c = cd_cart();
    if (!isset($c['domains'][$domain])) return;
    $years = max(1, min(10, $years));
    $perYear = $c['domains'][$domain]['original'] / max(1, $c['domains'][$domain]['years']);
    $c['domains'][$domain]['years']    = $years;
    $c['domains'][$domain]['original'] = round($perYear * $years, 2);
    cd_cart_save($c);
    cd_cart_recompute();
}

function cd_cart_set_main_domain(string $domain): void
{
    $domain = strtolower($domain);
    $c = cd_cart();
    if (isset($c['domains'][$domain])) {
        $c['main_domain'] = $domain;
        cd_cart_save($c);
    }
}

// ── Recompute (apply free-domain rule) ───────────────────────────────────────
function cd_cart_recompute(): void
{
    $c = cd_cart();
    $hasHosting = !empty($c['hosting']);
    $freeUsed   = false;
    $mainKey    = $c['main_domain'];

    // sort so main domain is processed first (eligible for "free")
    $domains = $c['domains'];
    if ($mainKey && isset($domains[$mainKey])) {
        $main = [$mainKey => $domains[$mainKey]];
        unset($domains[$mainKey]);
        $domains = $main + $domains;
    }

    foreach ($domains as $key => $d) {
        if (!is_array($d)) { unset($domains[$key]); continue; }
        $orig  = (float)($d['original'] ?? $d['price'] ?? 0);
        $years = max(1, (int)($d['years'] ?? 1));
        $type  = (string)($d['type'] ?? 'register');
        $d['original'] = $orig;
        $d['years']    = $years;
        $d['type']     = $type;
        $d['free']     = false;
        $d['price']    = $orig;
        if ($hasHosting && !$freeUsed && $type === 'register'
            && ($orig / $years) <= FREE_DOMAIN_PRICE_CAP) {
            $d['free']  = true;
            $d['price'] = 0.0;
            $freeUsed   = true;
        }
        $domains[$key] = $d;
    }
    $c['domains'] = $domains;
    cd_cart_save($c);
}

// ── Totals ───────────────────────────────────────────────────────────────────
function cd_cart_totals(): array
{
    $c = cd_cart();
    $sub = 0.0; $orig = 0.0;
    if (!empty($c['hosting']) && is_array($c['hosting'])) {
        $sub  += (float)($c['hosting']['price']    ?? 0);
        $orig += (float)($c['hosting']['original'] ?? $c['hosting']['price'] ?? 0);
    }
    foreach (($c['domains'] ?? []) as $d) {
        if (!is_array($d)) continue;
        $sub  += (float)($d['price']    ?? 0);
        $orig += (float)($d['original'] ?? $d['price'] ?? 0);
    }
    $discount = max(0, $orig - $sub);
    $cur = $c['currency'] ?? 'XAF';
    if (is_array($cur)) $cur = (string)($cur['code'] ?? 'XAF');
    return [
        'subtotal' => round($sub, 2),
        'original' => round($orig, 2),
        'discount' => round($discount, 2),
        'total'    => round($sub, 2),
        'currency' => (string)$cur,
        'savings_pct' => $orig > 0 ? (int)round(($discount / $orig) * 100) : 0,
    ];
}

function cd_money(float $amount, string|array $currency = ''): string
{
    // Tolerate a WHMCS currency object (array with 'code') instead of a code string.
    if (is_array($currency)) $currency = (string)($currency['code'] ?? '');
    if (!$currency) {
        $c   = cd_cart();
        $cur = $c['currency'] ?? 'XAF';
        $currency = is_array($cur) ? (string)($cur['code'] ?? 'XAF') : (string)$cur;
    }
    $sym = match (strtoupper($currency)) {
        'EUR' => '€', 'USD' => '$', 'GBP' => '£',
        default => $currency . ' ',
    };
    return ($sym === '€' || $sym === '$' || $sym === '£')
        ? number_format($amount, 2, ',', ' ') . ' ' . $sym
        : $sym . number_format($amount, 2, ',', ' ');
}
