<?php
/**
 * CamDigit Domain Search & Registration (any TLD)
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/order-domain.php
 *
 * Server-side flow:
 *   Step 1 — search domain (form GET ?sld=...&tld=...)
 *             • .cm  → direct WHOIS via lib helper
 *             • other → WHMCS DomainWhois API
 *   Step 2 — if available, render contact/account form
 *   Step 3 — POST submits → AddClient (or use logged-in client) + AddOrder
 *            → redirect to invoice page (custom or WHMCS-hosted)
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

// ── Inputs ──────────────────────────────────────────────────────────────────
$sld = cd_sanitize_sld($_GET['sld'] ?? $_POST['sld'] ?? '');
$tld = strtolower(preg_replace('/[^a-z0-9.]/', '', (string)($_GET['tld'] ?? $_POST['tld'] ?? '.cm')) ?? '.cm');
if ($tld === '' || $tld[0] !== '.') $tld = '.' . $tld;

$step      = (string)($_POST['step'] ?? '');
$errors    = [];
$available = null;
$priceText = '';

// ── Look up pricing for the chosen TLD (best-effort) ────────────────────────
function cd_tld_price(string $tld): string
{
    try {
        $r = whmcs_api('GetTLDPricing', []);
    } catch (RuntimeException) { return ''; }
    $pricing = $r['pricing'] ?? [];
    $key = ltrim($tld, '.');
    if (!isset($pricing[$key])) return '';
    $reg = $pricing[$key]['register'] ?? [];
    if (!$reg) return '';
    $first = reset($reg);
    $cur   = $r['currency'] ?? 'USD';
    return $first ? ($cur . ' ' . $first) : '';
}

// ── Step 1: search ──────────────────────────────────────────────────────────
if ($sld !== '' && $step === '') {
    $domain = $sld . $tld;
    if (str_ends_with($tld, '.cm')) {
        $available = cd_check_cm_whois($domain) === 'available';
        if ($available) {
            $_SESSION['cm_cart_domain'] = $domain;
            $_SESSION['cm_cart_set_at'] = time();
        }
    } else {
        try {
            $r = whmcs_api('DomainWhois', ['domain' => $domain]);
            $available = strtolower((string)($r['status'] ?? '')) === 'available';
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
    $priceText = cd_tld_price($tld);
}

// ── Step 2: submit order ────────────────────────────────────────────────────
$orderSuccess = null;
if ($step === 'order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('order_domain', 5, 600)) {
        $errors[] = 'Too many attempts. Please wait a few minutes.';
    } else {
        $domain = $sld . $tld;

        $clientId = cd_client_id();
        if ($clientId === 0) {
            // Need to create a client first
            $params = [
                'firstname'   => cd_sanitize_text($_POST['firstname'] ?? '', 100),
                'lastname'    => cd_sanitize_text($_POST['lastname']  ?? '', 100),
                'email'       => filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '',
                'address1'    => cd_sanitize_text($_POST['address1'] ?? '', 200),
                'city'        => cd_sanitize_text($_POST['city']     ?? '', 100),
                'state'       => cd_sanitize_text($_POST['state']    ?? '', 100),
                'postcode'    => cd_sanitize_text($_POST['postcode'] ?? '', 20),
                'country'     => cd_sanitize_country($_POST['country'] ?? ''),
                'phonenumber' => cd_sanitize_phone($_POST['phone']    ?? ''),
                'password2'   => (string)($_POST['password'] ?? ''),
                'currency'    => DEFAULT_CURRENCY_ID,
                'clientip'    => cd_client_ip(),
                'noemail'     => false,
            ];
            foreach (['firstname','lastname','email','address1','city','country','phonenumber'] as $f) {
                if (empty($params[$f])) $errors[] = ucfirst($f) . ' is required.';
            }
            if (strlen($params['password2']) < 8) $errors[] = 'Password must be at least 8 characters.';

            if (!$errors) {
                try {
                    $r = whmcs_api('AddClient', $params);
                    if (($r['result'] ?? '') !== 'success') {
                        $msg = (string)($r['message'] ?? 'Account creation failed.');
                        if (stripos($msg, 'duplicate') !== false || stripos($msg, 'already') !== false) {
                            $errors[] = 'An account with this email already exists. Please log in first.';
                        } else $errors[] = $msg;
                    } else {
                        $clientId = (int)$r['clientid'];
                        $_SESSION['uid'] = $clientId;
                    }
                } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
            }
        }

        if (!$errors && $clientId > 0) {
            $payment = in_array($_POST['paymentmethod'] ?? '', VALID_PAY_METHODS, true)
                ? $_POST['paymentmethod'] : 'banktransfer';

            $orderParams = [
                'clientid'        => $clientId,
                'domain'          => [$domain],
                'domaintype'      => ['register'],
                'regperiod'       => [(int)($_POST['regperiod'] ?? 1)],
                'dnsmanagement'   => [true],
                'emailforwarding' => [false],
                'idprotection'    => [false],
                'paymentmethod'   => $payment,
                'noemail'         => false,
            ];
            if (str_ends_with($tld, '.cm')) {
                $orderParams['nameserver1'] = CM_NS1;
                $orderParams['nameserver2'] = CM_NS2;
            }
            try {
                $r = whmcs_api('AddOrder', $orderParams);
                if (($r['result'] ?? '') !== 'success') {
                    $errors[] = (string)($r['message'] ?? 'Order failed.');
                } else {
                    unset($_SESSION['cm_cart_domain'], $_SESSION['cm_cart_set_at']);
                    $invoiceId = (int)($r['invoiceid'] ?? 0);
                    header('Location: ' . SITE_URL . '/account-invoices.php?id=' . $invoiceId);
                    exit;
                }
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
    }
}

// ── Render ──────────────────────────────────────────────────────────────────
cd_render_head(
    t('Domain Registration', 'Enregistrement de Domaine'),
    t('Find your perfect <span style="color:var(--theme2,#ffa31a)">domain</span>',
      'Trouvez votre <span style="color:var(--theme2,#ffa31a)">domaine</span> idéal'),
    t('Secure your digital identity in seconds. .CM, .com, .net, .africa and more.',
      'Sécurisez votre identité numérique en quelques secondes.')
);
?>


<div class="cd-card" style="margin-top:-90px;position:relative;z-index:5">
    <form method="get" action="">
        <div class="cd-search-bar">
            <input class="cd-input" type="text" name="sld" value="<?= htmlspecialchars($sld) ?>"
                placeholder="<?= t('yourbrand','votremarque') ?>" autocomplete="off" required>
            <select class="cd-input" name="tld">
                <?php foreach (['.cm', '.com', '.net', '.org', '.africa', '.io'] as $t): ?>
                <option value="<?= $t ?>" <?= $t === $tld ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach ?>
            </select>
            <button class="cd-btn" type="submit"><i class="fa fa-search"></i> <?= t('Search', 'Rechercher') ?></button>
        </div>
    </form>
</div>

<?php if ($sld !== '' && !$errors): ?>
<!-- TLD suggestions — loaded via AJAX after page render -->
<div class="cd-card" id="odSuggestions" style="display:none">
    <div class="cd-suggestions-wrap" style="margin-top:0;border-top:none;padding-top:0">
        <h4><i class="fa fa-globe" style="color:var(--theme2,#ffa31a)"></i>
            <?= t('Other extensions for', 'Autres extensions pour') ?>
            <span style="color:var(--theme,#236a25)"><?= htmlspecialchars($sld) ?></span>
        </h4>
        <div class="cd-tld-grid" id="odTldGrid">
            <div class="cd-tld-loading"><span class="cd-spin-sm"></span>
                <?= t('Checking availability…', 'Vérification de disponibilité…') ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const SLD = <?= json_encode($sld) ?>;
    const PROXY = <?= json_encode(SITE_URL . '/api-proxy.php') ?>;
    const CSRF = <?= json_encode(cd_csrf()) ?>;
    const LANG = <?= json_encode(cd_lang()) ?>;
    const SITE = <?= json_encode(SITE_URL) ?>;
    const SEARCHED = <?= json_encode($tld) ?>;

    const ALL_TLDS = ['.cm', '.com', '.org', '.net', '.biz', '.info', '.africa'];
    const suggest = ALL_TLDS.filter(t => t !== SEARCHED);

    async function loadSuggestions() {
        const wrap = document.getElementById('odSuggestions');
        const grid = document.getElementById('odTldGrid');
        if (!wrap || !grid || !SLD) return;
        wrap.style.display = '';

        try {
            const res = await fetch(PROXY, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'CheckMultiAvailability',
                    _csrf: CSRF,
                    sld: SLD,
                    tlds: suggest
                }),
            });
            const data = await res.json();
            if (data.result !== 'success' || !Array.isArray(data.domains)) {
                grid.innerHTML = '';
                return;
            }
            grid.innerHTML = data.domains.map(d => renderTldItem(d)).join('');
            if (!grid.innerHTML.trim()) wrap.style.display = 'none';
        } catch (e) {
            wrap.style.display = 'none';
        }
    }

    function renderTldItem(d) {
        const isAvail = d.status === 'available';
        const cls = isAvail ? 'available' : 'taken';
        const badge = isAvail ?
            (LANG === 'french' ? 'Disponible' : 'Available') :
            (LANG === 'french' ? 'Pris' : 'Pris');
        const btnHtml = isAvail ?
            `<a href="${SITE}/order-domain.php?sld=${encodeURIComponent(SLD)}&tld=${encodeURIComponent(d.tld)}" class="cd-btn">${LANG === 'french' ? 'Commander' : 'Register'} <i class="fa fa-arrow-right"></i></a>` :
            '';
        return `<div class="cd-tld-item ${cls}">
            <div class="cd-tld-item-header">
                <span class="cd-tld-ext">${esc(d.tld)}</span>
                <span class="cd-tld-item-badge">${esc(badge)}</span>
            </div>
            <div class="cd-tld-item-domain">${esc(d.domain)}</div>
            ${btnHtml}
        </div>`;
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', loadSuggestions);
}());
</script>
<?php endif ?>

<?php if ($errors): ?>
<div class="cd-alert cd-alert-error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
</div>
<?php endif ?>

<?php if ($available === null && !$errors): ?>
<div class="cd-feature-strip" style="margin-top:8px">
    <div class="cd-feature-item"><i class="fa fa-globe-africa"></i><span>.cm</span></div>
    <div class="cd-feature-item"><i class="fa fa-globe"></i><span>.com</span></div>
    <div class="cd-feature-item"><i class="fa fa-network-wired"></i><span>.net</span></div>
    <div class="cd-feature-item"><i class="fa fa-leaf"></i><span>.africa</span></div>
    <div class="cd-feature-item"><i class="fa fa-lock"></i><span><?= t('DNSSEC', 'DNSSEC') ?></span></div>
    <div class="cd-feature-item"><i class="fa fa-headset"></i><span><?= t('24/7 Support', 'Support 24h') ?></span></div>
</div>
<?php endif ?>

<?php if ($available === true): ?>
<div class="cd-card">
    <div class="cd-alert cd-alert-ok">
        <strong><?= htmlspecialchars($sld . $tld) ?></strong>
        — <?= t('available', 'disponible') ?>
        <?php if (is_string($priceText) && $priceText !== ''): ?> · <?= htmlspecialchars($priceText) ?>/yr<?php endif ?>
    </div>

    <h2 style="margin-top:0"><?= t('Complete your order', 'Finalisez votre commande') ?></h2>
    <form method="post" action="">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
        <input type="hidden" name="step" value="order">
        <input type="hidden" name="sld" value="<?= htmlspecialchars($sld) ?>">
        <input type="hidden" name="tld" value="<?= htmlspecialchars($tld) ?>">

        <?php if (cd_client_id() === 0): ?>
        <p style="color:#6b7280;font-size:14px"><?= t('Already have an account?', 'Vous avez déjà un compte?') ?>
            <a
                href="<?= SITE_URL ?>/login.php?next=<?= rawurlencode($_SERVER['REQUEST_URI']) ?>"><?= t('Log in', 'Connectez-vous') ?></a>
        </p>

        <div class="cd-row">
            <div><label class="cd-label"><?= t('First name', 'Prénom') ?></label>
                <input class="cd-input" type="text" name="firstname" required>
            </div>
            <div><label class="cd-label"><?= t('Last name', 'Nom') ?></label>
                <input class="cd-input" type="text" name="lastname" required>
            </div>
        </div>
        <div class="cd-row">
            <div><label class="cd-label">Email</label>
                <input class="cd-input" type="email" name="email" required>
            </div>
            <div><label class="cd-label"><?= t('Phone', 'Téléphone') ?></label>
                <input class="cd-input" type="tel" name="phone" required>
            </div>
        </div>
        <div class="cd-row">
            <div><label class="cd-label"><?= t('Address', 'Adresse') ?></label>
                <input class="cd-input" type="text" name="address1" required>
            </div>
        </div>
        <div class="cd-row">
            <div><label class="cd-label"><?= t('City', 'Ville') ?></label>
                <input class="cd-input" type="text" name="city" required>
            </div>
            <div><label class="cd-label"><?= t('State/Region', 'Région') ?></label>
                <input class="cd-input" type="text" name="state">
            </div>
            <div><label class="cd-label"><?= t('Postal code', 'Code postal') ?></label>
                <input class="cd-input" type="text" name="postcode">
            </div>
        </div>
        <div class="cd-row">
            <div><label class="cd-label"><?= t('Country', 'Pays') ?></label>
                <select class="cd-input" name="country" required>
                    <?php foreach (cd_countries() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $code === 'CM' ? 'selected' : '' ?>><?= htmlspecialchars($name) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div><label class="cd-label"><?= t('Password', 'Mot de passe') ?></label>
                <input class="cd-input" type="password" name="password" minlength="8" required>
            </div>
        </div>
        <?php endif ?>

        <div class="cd-row">
            <div><label class="cd-label"><?= t('Registration period', 'Durée d\'enregistrement') ?></label>
                <select class="cd-input" name="regperiod">
                    <?php foreach ([1,2,3,5] as $y): ?>
                    <option value="<?= $y ?>"><?= $y ?> <?= t('year(s)','an(s)') ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div><label class="cd-label"><?= t('Payment method', 'Mode de paiement') ?></label>
                <select class="cd-input" name="paymentmethod">
                    <option value="banktransfer"><?= t('Bank transfer', 'Virement bancaire') ?></option>
                    <option value="mtn_momo">MTN Mobile Money</option>
                    <option value="orange_money">Orange Money</option>
                    <option value="paypal">PayPal</option>
                    <option value="stripe"><?= t('Credit card', 'Carte de crédit') ?></option>
                </select>
            </div>
        </div>

        <button class="cd-btn" type="submit" style="margin-top:14px">
            <?= t('Place order', 'Passer la commande') ?> →
        </button>
    </form>
</div>
<?php elseif ($available === false): ?>
<div class="cd-card">
    <div class="cd-alert cd-alert-error">
        <strong><?= htmlspecialchars($sld . $tld) ?></strong>
        — <?= t('is already registered.', 'est déjà enregistré.') ?>
    </div>
    <p><?= t('Try another name or extension.', 'Essayez un autre nom ou extension.') ?></p>
</div>
<?php endif ?>


<?php cd_render_foot(); ?>