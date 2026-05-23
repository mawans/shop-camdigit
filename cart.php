<?php
/**
 * CamDigit Main — Cart
 * ─────────────────────────────────────────────────────────────────────────────
 * Self-contained, defensive (handles missing keys), AJAX cart mutations.
 *
 *   /cart.php          — normal cart view
 *   /cart.php?debug=1  — shows session contents for diagnostics
 *   /cart.php?test=1   — adds 3 sample domains to your session (testing only)
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/cart.php';
require_once __DIR__ . '/lib/layout.php';

// ── Test helper: add 3 sample domains so we can verify rendering ────────────
if (!empty($_GET['test'])) {
    cd_cart_add_domain(['domain' => 'example1.cm',  'type' => 'register', 'years' => 1, 'price' => 9.99,  'original' => 9.99]);
    cd_cart_add_domain(['domain' => 'example2.com', 'type' => 'register', 'years' => 1, 'price' => 12.99, 'original' => 12.99]);
    cd_cart_add_domain(['domain' => 'example3.net', 'type' => 'register', 'years' => 2, 'price' => 25.00, 'original' => 30.00]);
    header('Location: ' . SITE_URL . '/cart.php?debug=1');
    exit;
}

// ── Rescue: drop a corrupted cart session entirely ─────────────────────────
if (!empty($_GET['clear'])) {
    cd_cart_clear();
    header('Location: ' . SITE_URL . '/cart.php');
    exit;
}

// ── Self-heal: strip non-array / missing-domain entries and backfill any
//    missing required fields so a single bad item cannot kill the renderer
//    mid-foreach (which is what produced the "11 items but only one row +
//    no summary" symptom). Also re-key by domain and clear a stale main_domain.
if (isset($_SESSION['cd_cart']) && is_array($_SESSION['cd_cart'])) {
    $sess = $_SESSION['cd_cart'];
    // Normalize currency: WHMCS GetTLDPricing returns a currency *object*
    // (array with code/prefix/suffix). We only want the ISO code string.
    if (isset($sess['currency']) && is_array($sess['currency'])) {
        $_SESSION['cd_cart']['currency'] = (string)($sess['currency']['code'] ?? 'XAF');
        $sess['currency'] = $_SESSION['cd_cart']['currency'];
    }
    $rawDoms   = is_array($sess['domains'] ?? null) ? $sess['domains'] : [];
    $beforeN   = count($rawDoms);
    $clean     = [];
    foreach ($rawDoms as $k => $d) {
        if (!is_array($d)) continue;
        $dn = strtolower(trim((string)($d['domain'] ?? (is_string($k) ? $k : ''))));
        if ($dn === '') continue;
        $price = (float)($d['price']    ?? 0);
        $orig  = (float)($d['original'] ?? $price);
        $clean[$dn] = [
            'domain'   => $dn,
            'type'     => in_array(($d['type'] ?? 'register'), ['register','transfer'], true) ? $d['type'] : 'register',
            'years'    => max(1, (int)($d['years'] ?? 1)),
            'price'    => $price,
            'original' => $orig,
            'free'     => (bool)($d['free'] ?? false),
        ];
    }
    $_SESSION['cd_cart']['domains'] = $clean;
    // Drop a stale main_domain pointing at a removed entry.
    $main = (string)($sess['main_domain'] ?? '');
    if ($main !== '' && !isset($clean[$main])) {
        $_SESSION['cd_cart']['main_domain'] = $clean ? array_key_first($clean) : null;
    }
    // Drop a corrupt hosting entry.
    if (isset($sess['hosting']) && $sess['hosting'] !== null && !is_array($sess['hosting'])) {
        $_SESSION['cd_cart']['hosting'] = null;
    }
    if (count($clean) !== $beforeN) cd_cart_recompute();
}

// ── Legacy POST fallback (works if JS is off) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    $a = (string)($_POST['action'] ?? '');
    switch ($a) {
        case 'remove_hosting':    cd_cart_remove_hosting(); break;
        case 'remove_domain':     cd_cart_remove_domain((string)($_POST['domain'] ?? '')); break;
        case 'set_main_domain':   cd_cart_set_main_domain((string)($_POST['domain'] ?? '')); break;
        case 'set_hosting_years': cd_cart_set_hosting_years((int)($_POST['years'] ?? 1)); break;
        case 'set_domain_years':  cd_cart_set_domain_years((string)($_POST['domain'] ?? ''), (int)($_POST['years'] ?? 1)); break;
        case 'apply_promo':       $c = cd_cart(); $c['promo'] = cd_sanitize_text($_POST['promo'] ?? '', 40); cd_cart_save($c); break;
    }
    header('Location: ' . SITE_URL . '/cart.php'); exit;
}

$cart    = cd_cart();
$totals  = cd_cart_totals();
$count   = cd_cart_count();
$empty   = cd_cart_is_empty();
$cs      = $totals['currency'] ?? 'XAF';
$hosting = $cart['hosting'] ?? null;
$domains = $cart['domains'] ?? [];
$debug   = !empty($_GET['debug']);

cdm_head([
    'title'         => t('Your cart','Votre panier'),
    'active'        => 'cart',
    'hero_title'    => t('Your <span class="accent">cart</span>', 'Votre <span class="accent">panier</span>'),
    'hero_subtitle' => t('Review your selection and complete your order in a few clicks.',
                         'Vérifiez votre sélection et finalisez votre commande en quelques clics.'),
    'breadcrumb'    => t('Cart','Panier'),
]);
?>

<style>
.cart-steps {
    margin-top: -50px;
    position: relative;
    z-index: 5;
    background: #fff;
    border-radius: 16px;
    padding: 18px 22px;
    box-shadow: 0 4px 24px rgba(15, 13, 29, .08);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.cart-step {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-mute);
}

.cart-step .num {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--line);
    color: var(--ink-mute);
    font-weight: 700;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.cart-step.done {
    color: var(--theme);
}

.cart-step.done .num {
    background: var(--theme);
    color: #fff;
}

.cart-step.active {
    color: var(--ink);
    font-weight: 800;
}

.cart-step.active .num {
    background: var(--theme2);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(255, 163, 26, .2);
}

.cart-step-line {
    width: 30px;
    height: 2px;
    background: var(--line);
    border-radius: 2px;
}

.cart-step-line.done {
    background: var(--theme);
}

@media (max-width:575px) {
    .cart-step span:not(.num) {
        display: none;
    }

    .cart-step-line {
        width: 16px;
    }
}

.cart-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 26px;
    align-items: start;
    margin-top: 26px;
}

@media (max-width:991px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }
}

.cart-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 14px;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid var(--line-soft);
    transition: opacity .25s, transform .25s, max-height .25s;
    max-height: 300px;
    overflow: hidden;
}

.cart-row:last-of-type {
    border-bottom: 0;
}

.cart-row.removing {
    opacity: 0;
    transform: translateX(-20px);
    max-height: 0;
    padding: 0;
}

.cart-row-name {
    font-weight: 700;
    font-size: 15px;
    color: var(--ink);
    min-width: 0;
    word-break: break-all;
}

.cart-row-name .tag {
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 99px;
    margin-left: 6px;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.cart-row-name .tag.transfer {
    background: rgba(59, 130, 246, .15);
    color: #1d4ed8;
}

.cart-row-name .tag.main {
    background: rgba(255, 163, 26, .15);
    color: #b8730a;
}

.cart-row-price {
    text-align: right;
    font-weight: 800;
    font-size: 17px;
    color: var(--ink);
    letter-spacing: -.01em;
    white-space: nowrap;
}

.cart-row-price.free {
    color: var(--theme);
    text-transform: uppercase;
    font-size: 15px;
}

.cart-row-price .old {
    font-size: 12px;
    color: var(--ink-mute);
    text-decoration: line-through;
    font-weight: 500;
    margin-right: 5px;
}

.cart-row select {
    padding: 7px 26px 7px 12px;
    border: 1.5px solid var(--line);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    background: #fff;
    cursor: pointer;
    font-family: inherit;
    outline: none;
    color: var(--ink);
    appearance: none;
    background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%230f0d1d' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
}

.cart-row select:focus {
    border-color: var(--theme);
}

.cart-row .trash {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 0;
    background: rgba(220, 38, 38, .08);
    color: #dc2626;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .15s;
    flex-shrink: 0;
}

.cart-row .trash:hover {
    background: #dc2626;
    color: #fff;
}

@media (max-width:575px) {
    .cart-row {
        grid-template-columns: 1fr auto;
        row-gap: 10px;
    }

    .cart-row select,
    .cart-row-price {
        grid-column: 1 / -1;
        text-align: left;
    }
}

.host-top {
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.host-top .info {
    flex: 1;
    min-width: 0;
}

.host-top h2 {
    margin: 0 0 4px;
    font-size: 19px;
}

.host-top p {
    margin: 0;
    color: var(--ink-mute);
    font-size: 14px;
}

.host-features {
    list-style: none;
    padding: 0;
    margin: 14px 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 6px 18px;
}

.host-features li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 13.5px;
    color: var(--ink-soft);
}

.host-features li i {
    color: var(--theme);
    margin-top: 3px;
    font-size: 12px;
    flex-shrink: 0;
}

.host-bottom {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding-top: 14px;
    border-top: 1px solid var(--line-soft);
}

.host-bottom label {
    font-size: 13px;
    color: var(--ink-mute);
    font-weight: 600;
    margin: 0;
}

.host-bottom .price-block {
    margin-left: auto;
    text-align: right;
}

.host-bottom .price-block .old {
    font-size: 13px;
    color: var(--ink-mute);
    text-decoration: line-through;
    margin-right: 6px;
}

.host-bottom .price-block .new {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.02em;
}

.main-domain-bar {
    background: linear-gradient(135deg, rgba(255, 163, 26, .06), rgba(35, 106, 37, .05));
    padding: 14px 24px;
    border-top: 1px solid var(--line-soft);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.main-domain-bar label {
    font-size: 13px;
    color: var(--ink-soft);
    font-weight: 600;
    margin: 0;
}

.main-domain-bar select {
    flex: 1;
    min-width: 200px;
    padding: 9px 30px 9px 14px;
    border: 1.5px solid var(--line);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    background: #fff;
    cursor: pointer;
    font-family: inherit;
    outline: none;
    color: var(--ink);
}

.summary {
    position: sticky;
    top: 90px;
}

@media (max-width:991px) {
    .summary {
        position: relative;
        top: 0;
    }
}

.sum-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 14px;
    color: var(--ink-soft);
    border-bottom: 1px solid var(--line-soft);
}

.sum-line:last-of-type {
    border-bottom: 0;
}

.sum-line strong {
    color: var(--ink);
    font-weight: 600;
}

.sum-line.discount,
.sum-line.discount strong {
    color: var(--theme);
}

.sum-promo {
    display: flex;
    margin: 16px 0 6px;
    border: 1.5px solid var(--line);
    border-radius: 8px;
    overflow: hidden;
}

.sum-promo:focus-within {
    border-color: var(--theme);
    box-shadow: 0 0 0 3px rgba(35, 106, 37, .12);
}

.sum-promo input {
    flex: 1;
    border: 0;
    padding: 11px 14px;
    font-size: 13px;
    outline: none;
    font-family: inherit;
}

.sum-promo button {
    background: var(--theme);
    color: #fff;
    border: 0;
    padding: 0 18px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

.sum-promo button:hover {
    background: var(--theme2);
}

.sum-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 16px 0 4px;
    margin-top: 8px;
    border-top: 2px solid var(--line);
}

.sum-total .lbl {
    font-size: 15px;
    font-weight: 700;
}

.sum-total .val {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.02em;
}

.sum-tax {
    font-size: 11px;
    color: var(--ink-mute);
    text-align: right;
    margin: 0 0 14px;
}

.pay-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--line-soft);
}

.pay-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--ink-soft);
}

.pay-chip i {
    color: var(--theme);
    font-size: 12px;
}

.trust-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--line-soft);
    font-size: 11px;
    color: var(--ink-mute);
}

.trust-row span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.trust-row i {
    color: var(--theme);
}

.add-line {
    padding-top: 16px;
    margin-top: 8px;
    border-top: 1px dashed var(--line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.add-line a {
    font-weight: 600;
    color: var(--theme);
}

.hint-card {
    background: linear-gradient(135deg, #fff8eb, #fffbf2);
    border: 1.5px dashed var(--theme2);
    border-radius: 16px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.hint-card h3 {
    margin: 0 0 4px;
    font-size: 16px;
}

.hint-card p {
    margin: 0;
    color: var(--ink-mute);
    font-size: 13.5px;
}

.hint-card a {
    margin-left: auto;
}

.debug-panel {
    background: #0f0d1d;
    color: #a8b0c5;
    border-radius: 12px;
    padding: 20px 24px;
    margin: 26px 0;
    font-family: 'SF Mono', Menlo, monospace;
    font-size: 13px;
    line-height: 1.7;
}

.debug-panel h4 {
    color: #ffa31a;
    margin: 0 0 10px;
    font-family: inherit;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: .06em;
}

.debug-panel pre {
    color: #22c55e;
    background: #000;
    padding: 14px;
    border-radius: 8px;
    overflow: auto;
    margin: 8px 0 0;
    font-size: 12px;
}

.debug-panel .k {
    color: #ffa31a;
}

.debug-panel .v {
    color: #fff;
}
</style>

<!-- Step indicator -->
<div class="cart-steps">
    <div class="cart-step done">
        <div class="num"><i class="fa-solid fa-check"></i></div><span><?= t('Choose','Choix') ?></span>
    </div>
    <div class="cart-step-line done"></div>
    <div class="cart-step active">
        <div class="num">2</div><span><?= t('Cart','Panier') ?></span>
    </div>
    <div class="cart-step-line"></div>
    <div class="cart-step">
        <div class="num">3</div><span><?= t('Details','Coordonnées') ?></span>
    </div>
    <div class="cart-step-line"></div>
    <div class="cart-step">
        <div class="num">4</div><span><?= t('Payment','Paiement') ?></span>
    </div>
</div>

<?php if ($debug): ?>
<div class="debug-panel">
    <h4><i class="fa-solid fa-bug"></i> Cart Session Diagnostics</h4>
    <div><span class="k">Session ID:</span> <span class="v"><?= htmlspecialchars(session_id()) ?></span></div>
    <div><span class="k">Session name:</span> <span class="v"><?= htmlspecialchars(session_name()) ?></span></div>
    <div><span class="k">cd_cart_count():</span> <span class="v"><?= $count ?></span></div>
    <div><span class="k">cd_cart_is_empty():</span> <span class="v"><?= $empty ? 'true' : 'false' ?></span></div>
    <div><span class="k">$cart['hosting']:</span> <span class="v"><?= $hosting ? 'object' : 'null' ?></span></div>
    <div><span class="k">$cart['domains'] count:</span> <span class="v"><?= count($domains) ?></span></div>
    <div><span class="k">Logged in client:</span> <span class="v"><?= cd_client_id() ?></span></div>
    <h4 style="margin-top:14px">Full <code>$_SESSION['cd_cart']</code>:</h4>
    <pre><?= htmlspecialchars(print_r($cart, true)) ?></pre>
    <p style="margin:12px 0 0;font-size:12px;color:#a8b0c5">
        Visit <code style="color:#ffa31a"><?= SITE_URL ?>/cart.php?test=1</code> to add 3 sample domains so we can
        verify rendering works.
    </p>
</div>
<?php endif ?>

<?php if ($empty): ?>
<div class="cdm-empty cdm-mt-3">
    <div class="cdm-empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
    <h2><?= t('Your cart is empty','Votre panier est vide') ?></h2>
    <p><?= t('Browse our hosting plans or register a new domain to get started.',
                 'Parcourez nos plans d\'hébergement ou enregistrez un domaine pour commencer.') ?></p>
    <div class="cdm-flex cdm-gap-1 cdm-wrap" style="justify-content:center;margin-bottom:16px">
        <a class="cdm-btn cdm-btn-lg" href="<?= SITE_URL ?>/order-hosting.php">
            <i class="fa-solid fa-server"></i> <?= t('Choose hosting','Choisir un hébergement') ?>
        </a>
        <a class="cdm-btn cdm-btn-ghost cdm-btn-lg" href="<?= SITE_URL ?>/order-domain.php">
            <i class="fa-solid fa-globe"></i> <?= t('Find a domain','Chercher un domaine') ?>
        </a>
    </div>
    <p class="cdm-muted" style="font-size:13px">
        <?= t('Added items recently and they disappeared? Your session may have expired. Add them again.',
                  'Vos articles ont disparu ? Votre session a peut-être expiré. Réessayez.') ?>
        <a href="<?= SITE_URL ?>/cart.php?debug=1" style="font-weight:600;margin-left:6px"><i
                class="fa-solid fa-bug"></i> <?= t('Diagnose','Diagnostiquer') ?></a>
    </p>
</div>

<?php else: ?>

<div class="cart-layout">

    <!-- LEFT COL -->
    <div>
        <?php if ($hosting): ?>
        <div class="cdm-card" id="hostingCard">
            <div class="cdm-card-head">
                <h3><i class="fa-solid fa-box-open"></i> <?= t('Your hosting plan','Votre hébergement') ?></h3>
                <span class="meta">1 <?= t('plan','plan') ?></span>
            </div>
            <div class="cdm-card-body">
                <div class="host-top">
                    <div class="info">
                        <h2><?= htmlspecialchars((string)($hosting['name'] ?? '—')) ?></h2>
                        <p><?= t('Web hosting plan with all the essentials included.','Plan d\'hébergement avec tous les essentiels inclus.') ?>
                        </p>
                    </div>
                    <button class="cdm-btn cdm-btn-ghost cdm-btn-sm" data-act="remove_hosting"
                        style="color:#dc2626;border-color:#fecaca;flex-shrink:0">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>

                <?php if (!empty($hosting['features']) && is_array($hosting['features'])): ?>
                <ul class="host-features">
                    <?php foreach ($hosting['features'] as $feat): ?>
                    <li><i class="fa-solid fa-check"></i><span><?= htmlspecialchars((string)$feat) ?></span></li>
                    <?php endforeach ?>
                </ul>
                <?php endif ?>

                <div class="host-bottom">
                    <label><?= t('Duration','Durée') ?>:</label>
                    <select class="cdm-select" data-act="set_hosting_years" style="width:auto">
                        <?php $hYears = (int)($hosting['years'] ?? 1); foreach ([1,2,3] as $y):
                            $lbl = $y === 1 ? t('1 year','1 an') : ($y . ' ' . t('years','ans')); ?>
                        <option value="<?= $y ?>" <?= $hYears === $y ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach ?>
                    </select>
                    <a href="<?= SITE_URL ?>/order-hosting.php?pid=<?= (int)($hosting['pid'] ?? 0) ?>"
                        style="font-size:13px;font-weight:600;color:var(--theme2)">
                        <i class="fa-solid fa-pen"></i> <?= t('Modify','Modifier') ?>
                    </a>
                    <div class="price-block">
                        <?php $hPrice = (float)($hosting['price'] ?? 0); $hOriginal = (float)($hosting['original'] ?? $hPrice);
                              if ($hOriginal > $hPrice): ?>
                        <span class="old"><?= cd_money($hOriginal, $cs) ?></span>
                        <?php endif ?>
                        <span class="new"><?= cd_money($hPrice, $cs) ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($domains)): ?>
            <div class="main-domain-bar">
                <label><i class="fa-solid fa-star" style="color:var(--theme2)"></i>
                    <?= t('Main domain','Domaine principal') ?>:</label>
                <select data-act="set_main_domain">
                    <?php $mainDomain = (string)($cart['main_domain'] ?? '');
                    foreach ($domains as $d):
                        if (!is_array($d)) continue;
                        $dn = (string)($d['domain'] ?? '');
                        if ($dn === '') continue; ?>
                    <option value="<?= htmlspecialchars($dn) ?>" <?= $mainDomain === $dn ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dn) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?php endif ?>
        </div>
        <?php endif ?>

        <?php if (!empty($domains)): ?>
        <div class="cdm-card">
            <div class="cdm-card-head">
                <h3><i class="fa-solid fa-globe"></i> <?= t('Domain(s)','Domaine(s)') ?></h3>
                <span class="meta" id="domainCount"><?= count($domains) ?>
                    <?= count($domains) === 1 ? t('item','article') : t('items','articles') ?></span>
            </div>
            <div class="cdm-card-body" id="domainList">
                <?php
                $mainDomain = (string)($cart['main_domain'] ?? '');
                $rendered = 0;
                foreach ($domains as $d):
                    if (!is_array($d)) continue;
                    $dn = (string)($d['domain'] ?? '');
                    if ($dn === '') continue;
                    $dType  = (string)($d['type']     ?? 'register');
                    $dYears = (int)   ($d['years']    ?? 1);
                    $dPrice = (float) ($d['price']    ?? 0);
                    $dOrig  = (float) ($d['original'] ?? $dPrice);
                    $dFree  = (bool)  ($d['free']     ?? false);
                    $isMain = $mainDomain === $dn;
                    $rendered++;
                ?>
                <div class="cart-row" data-domain="<?= htmlspecialchars($dn) ?>">
                    <div class="cart-row-name">
                        <?= htmlspecialchars($dn) ?>
                        <?php if ($dType === 'transfer'): ?><span
                            class="tag transfer"><?= t('Transfer','Transfert') ?></span><?php endif ?>
                        <?php if ($isMain): ?><span class="tag main"><?= t('Main','Principal') ?></span><?php endif ?>
                    </div>
                    <select data-act="set_domain_years" data-domain="<?= htmlspecialchars($dn) ?>">
                        <?php foreach ([1,2,3,5,10] as $yy): ?>
                        <option value="<?= $yy ?>" <?= $dYears === $yy ? 'selected' : '' ?>>
                            <?= $yy ?> <?= $yy === 1 ? t('yr','an') : t('yrs','ans') ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                    <div class="cart-row-price">
                        <?php if ($dFree): ?>
                        <span class="old"><?= cd_money($dOrig, $cs) ?></span>
                        <span class="free"><?= t('FREE','GRATUIT') ?></span>
                        <?php else: ?>
                        <?php if ($dOrig > $dPrice && $dOrig > 0): ?>
                        <span class="old"><?= cd_money($dOrig, $cs) ?></span>
                        <?php endif ?>
                        <span><?= cd_money($dPrice, $cs) ?></span>
                        <?php endif ?>
                    </div>
                    <button class="trash" data-act="remove_domain" data-domain="<?= htmlspecialchars($dn) ?>"
                        aria-label="Remove">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                <?php endforeach ?>

                <?php if ($rendered === 0): ?>
                <div class="cdm-alert cdm-alert-warn">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <strong><?= t('No domains rendered.','Aucun domaine affiché.') ?></strong><br>
                        <?= t('Cart says it has', 'Le panier déclare avoir') ?> <?= count($domains) ?>
                        <?= t('items but none have a valid domain field. Visit','articles mais aucun n\'a de champ domain valide. Visitez') ?>
                        <a href="?debug=1"><strong><i class="fa-solid fa-bug"></i> ?debug=1</strong></a>
                        <?= t('to see raw data.','pour voir les données brutes.') ?>
                    </div>
                </div>
                <?php endif ?>

                <div class="add-line">
                    <span style="font-size:13px;color:var(--ink-mute)">
                        <i class="fa-solid fa-circle-info" style="color:var(--theme2);margin-right:5px"></i>
                        <?= t('Need another extension?','Besoin d\'une autre extension ?') ?>
                    </span>
                    <a href="<?= SITE_URL ?>/order-domain.php">
                        <i class="fa-solid fa-plus"></i> <?= t('Add a domain','Ajouter un domaine') ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif ?>

        <?php if (!$hosting): ?>
        <div class="hint-card">
            <div style="flex:1;min-width:240px">
                <h3><i class="fa-solid fa-gift"
                        style="color:var(--theme2);margin-right:6px"></i><?= t('Get a free domain with hosting','Domaine gratuit avec hébergement') ?>
                </h3>
                <p><?= t('Add a hosting plan and your first eligible domain registration is free, for life.',
                         'Ajoutez un hébergement et votre premier domaine éligible est gratuit, à vie.') ?></p>
            </div>
            <a href="<?= SITE_URL ?>/order-hosting.php" class="cdm-btn cdm-btn-accent">
                <i class="fa-solid fa-server"></i> <?= t('Browse hosting','Voir l\'hébergement') ?>
            </a>
        </div>
        <?php endif ?>
    </div>

    <!-- RIGHT COL: summary -->
    <aside class="summary">
        <div class="cdm-card">
            <div class="cdm-card-head dark">
                <h3><i class="fa-solid fa-receipt"></i> <?= t('Order summary','Récapitulatif') ?></h3>
                <span class="meta"><?= $count ?> <?= t('items','articles') ?></span>
            </div>
            <div class="cdm-card-body">
                <?php if ($hosting): ?>
                <div class="sum-line">
                    <span><?= htmlspecialchars((string)($hosting['name'] ?? '—')) ?></span>
                    <strong><?= cd_money((float)($hosting['price'] ?? 0), $cs) ?></strong>
                </div>
                <?php endif ?>

                <?php foreach ($domains as $d):
                    if (!is_array($d)) continue;
                    $dn = (string)($d['domain'] ?? '');
                    if ($dn === '') continue;
                    $dFree = (bool)($d['free'] ?? false);
                    $dP = (float)($d['price'] ?? 0); ?>
                <div class="sum-line">
                    <span><?= htmlspecialchars($dn) ?></span>
                    <strong>
                        <?php if ($dFree): ?><span style="color:var(--theme)"><?= t('FREE','GRATUIT') ?></span>
                        <?php else: ?><?= cd_money($dP, $cs) ?><?php endif ?>
                    </strong>
                </div>
                <?php endforeach ?>

                <?php if (($totals['discount'] ?? 0) > 0): ?>
                <div class="sum-line discount">
                    <span><i class="fa-solid fa-tags"></i> <?= t('You save','Vous économisez') ?>
                        (<?= (int)($totals['savings_pct'] ?? 0) ?>%)</span>
                    <strong>− <?= cd_money((float)$totals['discount'], $cs) ?></strong>
                </div>
                <?php endif ?>

                <div class="sum-promo">
                    <input type="text" id="promoInput" placeholder="<?= t('Promo code','Code promo') ?>"
                        value="<?= htmlspecialchars((string)($cart['promo'] ?? '')) ?>">
                    <button id="promoBtn"><?= t('Apply','Appliquer') ?></button>
                </div>

                <div class="sum-total">
                    <span class="lbl"><?= t('Total','Total') ?></span>
                    <span class="val" id="totalNum"><?= cd_money((float)($totals['total'] ?? 0), $cs) ?></span>
                </div>
                <p class="sum-tax"><?= t('VAT included where applicable','TTC selon votre pays') ?></p>

                <a class="cdm-btn cdm-btn-accent cdm-btn-block cdm-btn-lg" href="<?= SITE_URL ?>/checkout.php">
                    <i class="fa-solid fa-lock"></i> <?= t('Proceed to checkout','Passer au paiement') ?>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                <div class="pay-strip">
                    <span class="pay-chip"><i class="fa-solid fa-mobile-screen"></i> MTN MoMo</span>
                    <span class="pay-chip"><i class="fa-solid fa-mobile-screen"></i> Orange</span>
                    <span class="pay-chip"><i class="fa-brands fa-cc-visa"></i> Visa</span>
                    <span class="pay-chip"><i class="fa-brands fa-paypal"></i> PayPal</span>
                </div>
                <div class="trust-row">
                    <span><i class="fa-solid fa-lock"></i> <?= t('Secure','Sécurisé') ?></span>
                    <span><i class="fa-solid fa-shield-halved"></i> SSL</span>
                    <span><i class="fa-solid fa-headset"></i> 24/7</span>
                    <span><i class="fa-solid fa-rotate-left"></i> 30j</span>
                </div>
            </div>
        </div>

        <p class="cdm-text-center cdm-muted cdm-mt-2" style="font-size:13px">
            <i class="fa-solid fa-circle-question" style="color:var(--theme2)"></i>
            <?= t('Need help?','Besoin d\'aide ?') ?>
            <a href="<?= SITE_URL ?>/submitticket.php"
                style="font-weight:600"><?= t('Contact us','Contactez-nous') ?></a>
        </p>
    </aside>
</div>

<script>
(function() {
    'use strict';
    var SITE = '<?= SITE_URL ?>';
    var API = SITE + '/cart-api.php';
    var CSRF = '<?= htmlspecialchars(cd_csrf(), ENT_QUOTES) ?>';
    var LANG = '<?= cd_lang() ?>';

    function t(en, fr) {
        return LANG === 'french' ? fr : en;
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function(r) {
            return r.json().catch(function() {
                return {
                    result: 'error'
                };
            });
        });
    }

    function notify(msg, kind) {
        if (window.CDM && CDM.toast) return CDM.toast(msg, kind);
        var n = document.createElement('div');
        n.style.cssText =
            'position:fixed;top:18px;right:18px;z-index:9999;padding:12px 18px;border-radius:10px;color:#fff;font-size:14px;box-shadow:0 10px 30px rgba(0,0,0,.2);background:' +
            (kind === 'err' ? '#dc2626' : '#236a25');
        n.textContent = msg;
        document.body.appendChild(n);
        setTimeout(function() {
            n.remove();
        }, 3000);
    }

    function api(action, body) {
        return postJson(API, Object.assign({
            action: action,
            _csrf: CSRF
        }, body || {}));
    }

    function applyState(d) {
        if (!d || d.result !== 'success') return;
        var totals = d.totals || {};
        var t1 = document.getElementById('totalNum');
        if (t1 && totals.total != null) {
            t1.textContent = (window.CDM && CDM.format) ? CDM.format(totals.total, totals.currency) : totals.total;
        }
        var dc = document.getElementById('domainCount');
        if (dc && d.cart && Array.isArray(d.cart.domains)) {
            var n = d.cart.domains.length;
            dc.textContent = n + ' ' + (n === 1 ? t('item', 'article') : t('items', 'articles'));
        }
        var b = document.querySelector('.cdm-cart-badge');
        if (b && d.count != null) b.textContent = d.count;
    }
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-act]');
        if (!btn || btn.tagName === 'SELECT') return;
        var act = btn.getAttribute('data-act');
        var doIt;
        if (act === 'remove_hosting') {
            e.preventDefault();
            doIt = function() {
                btn.disabled = true;
                api('remove_hosting').then(function(d) {
                    if (d.result === 'success') {
                        var c = document.getElementById('hostingCard');
                        if (c) {
                            c.style.transition = 'opacity .25s,transform .25s';
                            c.style.opacity = 0;
                            c.style.transform = 'translateY(-8px)';
                            setTimeout(function() {
                                c.remove();
                            }, 260);
                        }
                        applyState(d);
                        notify(t('Hosting removed', 'Hébergement supprimé'), 'ok');
                        if (d.count === 0) setTimeout(function() {
                            location.reload();
                        }, 700);
                    } else {
                        btn.disabled = false;
                        notify(d.message || 'Error', 'err');
                    }
                });
            };
            if (window.CDM && CDM.confirm) CDM.confirm({
                title: t('Remove hosting?', 'Supprimer ?'),
                okLabel: t('Remove', 'Supprimer'),
                dangerous: true
            }).then(function(y) {
                if (y) doIt();
            });
            else if (confirm(t('Remove your hosting plan?', 'Supprimer votre hébergement ?'))) doIt();
        } else if (act === 'remove_domain') {
            e.preventDefault();
            var domain = btn.getAttribute('data-domain');
            doIt = function() {
                btn.disabled = true;
                api('remove_domain', {
                    domain: domain
                }).then(function(d) {
                    if (d.result === 'success') {
                        var row = btn.closest('[data-domain]');
                        if (row) {
                            row.classList.add('removing');
                            setTimeout(function() {
                                row.remove();
                            }, 280);
                        }
                        applyState(d);
                        notify(t('Removed', 'Retiré') + ': ' + domain, 'ok');
                        if (d.count === 0) setTimeout(function() {
                            location.reload();
                        }, 700);
                    } else {
                        btn.disabled = false;
                        notify(d.message || 'Error', 'err');
                    }
                });
            };
            if (window.CDM && CDM.confirm) CDM.confirm({
                title: t('Remove this domain?', 'Supprimer ce domaine ?'),
                message: domain,
                okLabel: t('Remove', 'Supprimer'),
                dangerous: true
            }).then(function(y) {
                if (y) doIt();
            });
            else if (confirm(t('Remove ', 'Supprimer ') + domain + '?')) doIt();
        }
    });
    document.addEventListener('change', function(e) {
        var sel = e.target;
        if (!sel.matches || !sel.matches('[data-act]')) return;
        var act = sel.getAttribute('data-act'),
            body = {};
        if (act === 'set_hosting_years') body = {
            years: parseInt(sel.value, 10)
        };
        else if (act === 'set_domain_years') body = {
            years: parseInt(sel.value, 10),
            domain: sel.getAttribute('data-domain')
        };
        else if (act === 'set_main_domain') body = {
            domain: sel.value
        };
        else return;
        sel.disabled = true;
        api(act, body).then(function(d) {
            sel.disabled = false;
            if (d.result === 'success') {
                applyState(d);
                notify(t('Updated', 'Mis à jour'), 'ok');
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else notify(d.message || 'Error', 'err');
        });
    });
    var pBtn = document.getElementById('promoBtn');
    if (pBtn) pBtn.addEventListener('click', function() {
        var v = document.getElementById('promoInput').value.trim();
        pBtn.disabled = true;
        api('apply_promo', {
            promo: v
        }).then(function(d) {
            pBtn.disabled = false;
            if (d.result === 'success') {
                applyState(d);
                notify(v ? t('Promo applied', 'Code appliqué') : t('Promo cleared', 'Code retiré'),
                    'ok');
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else notify(d.message || t('Invalid code', 'Code invalide'), 'err');
        });
    });
})();
</script>

<?php endif ?>

<?php cdm_foot(); ?>