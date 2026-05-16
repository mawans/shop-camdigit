<?php
/**
 * CamDigit .CM Domain Registration Page
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/order-cm.php
 * Companion: api-proxy.php (same directory)
 *
 * Three-step flow:
 *   Step 1 — Domain availability search
 *   Step 2 — Customer & account details
 *   Step 3 — Success / error confirmation
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

$proxyUrl = SITE_URL . '/api-proxy.php';

// Pre-fill from ?sld= query string (passed from whois.tpl or domain-pricing.tpl)
$preFillSld = '';
if (!empty($_GET['sld'])) {
    $raw = strtolower(trim((string)$_GET['sld']));
    $raw = preg_replace('/\.cm$/i', '', $raw);
    if (preg_match('/^[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]?$/', $raw)) {
        $preFillSld = $raw;
    }
}

cd_render_head(
    t('.CM Domain Registration', 'Enregistrement de Domaine .CM'),
    t('Register your <span style="color:var(--theme2,#ffa31a)">.cm</span> domain',
      'Enregistrez votre domaine <span style="color:var(--theme2,#ffa31a)">.cm</span>'),
    t('Get your Cameroonian domain name in minutes — fully managed through ' . COMPANY_NAME . '.',
      'Obtenez votre nom de domaine camerounais en quelques minutes — géré par ' . COMPANY_NAME . '.')
);
?>

<div id="coPage">

<!-- ── Progress ───────────────────────────────────────────────────────────── -->
<div class="cd-progress" id="coProgress" role="list">
    <div class="cd-prog-step active" data-step="1" role="listitem">
        <div class="cd-prog-step-icon"><i class="fa fa-search"></i></div>
        <span class="cd-prog-step-label"><?= t('Search','Recherche') ?></span>
    </div>
    <div class="cd-prog-line" id="coLine1"></div>
    <div class="cd-prog-step" data-step="2" role="listitem">
        <div class="cd-prog-step-icon"><i class="fa fa-user"></i></div>
        <span class="cd-prog-step-label"><?= t('Details','Coordonnées') ?></span>
    </div>
    <div class="cd-prog-line" id="coLine2"></div>
    <div class="cd-prog-step" data-step="3" role="listitem">
        <div class="cd-prog-step-icon"><i class="fa fa-check"></i></div>
        <span class="cd-prog-step-label"><?= t('Confirm','Confirmation') ?></span>
    </div>
</div>

<!-- Global alert -->
<div class="cd-alert cd-alert-error" id="coAlert" role="alert" style="display:none"></div>

<!-- ════ STEP 1 — Domain Search ════════════════════════════════════════════ -->
<div class="cd-card" id="coStep1">
    <h2 style="margin-top:0"><?= t('Find your .cm domain','Trouvez votre domaine .cm') ?></h2>
    <p class="cd-muted"><?= t('Enter just the name part — we\'ll add <strong>.cm</strong> automatically.',
        'Entrez uniquement le nom — nous ajoutons <strong>.cm</strong> automatiquement.') ?></p>

    <form id="coSearchForm" novalidate autocomplete="off" style="margin-top:18px">
        <div class="cd-search-bar" style="max-width:100%">
            <div class="cd-sld-wrap">
                <input type="text" id="coSld" class="cd-input"
                    placeholder="<?= t('yourbrand','votremarque') ?>"
                    maxlength="63" autocapitalize="none" spellcheck="false"
                    aria-label="<?= t('Domain name without .cm','Nom de domaine sans .cm') ?>"
                    value="<?= htmlspecialchars($preFillSld) ?>" required
                    style="border-radius:50px 0 0 50px">
                <span class="cd-sld-tld">.cm</span>
            </div>
            <button type="submit" class="cd-btn" id="coSearchBtn"
                aria-label="<?= t('Check availability','Vérifier la disponibilité') ?>">
                <span class="cd-btn-label"><i class="fa fa-search"></i> <?= t('Check','Vérifier') ?></span>
                <span class="cd-spinner"><span class="cd-spin-icon"></span></span>
            </button>
        </div>
        <p class="cd-muted" style="font-size:12px;margin-top:8px">
            <?= t('Letters, numbers and hyphens only — no leading or trailing hyphens.',
                'Lettres, chiffres et tirets uniquement — pas de tirets au début ni à la fin.') ?>
        </p>
    </form>

    <div class="cd-avail" id="coAvail" style="display:none">
        <div class="cd-avail-inner">
            <div class="cd-avail-icon" id="coAvailIcon"></div>
            <div class="cd-avail-info">
                <p class="cd-avail-domain" id="coAvailDomain"></p>
                <p class="cd-avail-status" id="coAvailStatus"></p>
            </div>
            <button type="button" class="cd-btn hidden" id="coRegisterBtn">
                <?= t('Register','Enregistrer') ?> <i class="fa fa-arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- TLD suggestions loaded after availability check -->
    <div class="cd-suggestions-wrap hidden" id="coSuggestions">
        <h4><i class="fa fa-globe" style="color:var(--theme2,#ffa31a)"></i>
            <?= t('Other extensions also available', 'Autres extensions aussi disponibles') ?>
        </h4>
        <div class="cd-tld-grid" id="coTldGrid">
            <div class="cd-tld-loading"><span class="cd-spin-sm"></span>
                <?= t('Checking…', 'Vérification…') ?>
            </div>
        </div>
    </div>

    <div class="cd-feature-strip">
        <div class="cd-feature-item">
            <i class="fa fa-shield-alt"></i>
            <div><h4><?= t('Secure','Sécurisé') ?></h4><p><?= t('SSL & DNSSEC','SSL & DNSSEC') ?></p></div>
        </div>
        <div class="cd-feature-item">
            <i class="fa fa-bolt"></i>
            <div><h4><?= t('Instant','Instantané') ?></h4><p><?= t('Live in minutes','En ligne en minutes') ?></p></div>
        </div>
        <div class="cd-feature-item">
            <i class="fa fa-server"></i>
            <div><h4><?= t('Managed DNS','DNS géré') ?></h4><p><?= t('Free DNS control','Contrôle DNS gratuit') ?></p></div>
        </div>
        <div class="cd-feature-item">
            <i class="fa fa-headset"></i>
            <div><h4><?= t('24/7 Support','Support 24/7') ?></h4><p><?= t('Always here','Toujours disponible') ?></p></div>
        </div>
    </div>
</div>

<!-- ════ STEP 2 — Customer Details ═════════════════════════════════════════ -->
<div class="cd-card hidden" id="coStep2">
    <h2 style="margin-top:0"><?= t('Your details','Vos coordonnées') ?></h2>
    <p class="cd-muted"><?= t('We\'ll create your account and place the order in one step.',
        'Nous créerons votre compte et passerons la commande en une seule étape.') ?></p>

    <div class="cd-order-bar">
        <i class="fa fa-globe"></i>
        <strong><?= t('Domain:','Domaine:') ?></strong>
        <span class="cd-order-bar-domain" id="coOrderDomain"></span>
        <button type="button" class="cd-change-btn" id="coChangeBtn"><?= t('Change','Modifier') ?></button>
    </div>

    <form id="coDetailsForm" novalidate autocomplete="on">

        <div class="cd-row">
            <div>
                <label class="cd-label" for="coFirstname"><?= t('First Name','Prénom') ?> <span style="color:#dc2626">*</span></label>
                <input type="text" id="coFirstname" class="cd-input" required autocomplete="given-name">
            </div>
            <div>
                <label class="cd-label" for="coLastname"><?= t('Last Name','Nom') ?> <span style="color:#dc2626">*</span></label>
                <input type="text" id="coLastname" class="cd-input" required autocomplete="family-name">
            </div>
        </div>

        <div class="cd-row">
            <div>
                <label class="cd-label" for="coEmail"><?= t('Email','Email') ?> <span style="color:#dc2626">*</span></label>
                <input type="email" id="coEmail" class="cd-input" required autocomplete="email">
            </div>
            <div>
                <label class="cd-label" for="coPhone"><?= t('Phone','Téléphone') ?></label>
                <input type="tel" id="coPhone" class="cd-input" autocomplete="tel" placeholder="+237 6XX XX XX XX">
            </div>
        </div>

        <label class="cd-label" for="coAddress"><?= t('Address','Adresse') ?> <span style="color:#dc2626">*</span></label>
        <input type="text" id="coAddress" class="cd-input" required autocomplete="street-address" style="margin-bottom:16px">

        <div class="cd-row">
            <div>
                <label class="cd-label" for="coCity"><?= t('City','Ville') ?> <span style="color:#dc2626">*</span></label>
                <input type="text" id="coCity" class="cd-input" required autocomplete="address-level2">
            </div>
            <div>
                <label class="cd-label" for="coState"><?= t('State / Region','Région') ?></label>
                <input type="text" id="coState" class="cd-input" autocomplete="address-level1">
            </div>
        </div>

        <div class="cd-row">
            <div>
                <label class="cd-label" for="coPostcode"><?= t('Postcode','Code postal') ?></label>
                <input type="text" id="coPostcode" class="cd-input" autocomplete="postal-code">
            </div>
            <div>
                <label class="cd-label" for="coCountry"><?= t('Country','Pays') ?> <span style="color:#dc2626">*</span></label>
                <select id="coCountry" class="cd-input" required autocomplete="country">
                    <option value=""><?= t('Select country…','Sélectionnez un pays…') ?></option>
                    <?php foreach (cd_countries() as $code => $name): ?>
                        <option value="<?= $code ?>" <?= $code === 'CM' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div class="cd-divider"></div>
        <p class="cd-form-section"><i class="fa fa-credit-card"></i> <?= t('Payment Method','Mode de paiement') ?></p>

        <label class="cd-label" for="coPayment"><?= t('Pay with','Payer avec') ?> <span style="color:#dc2626">*</span></label>
        <select id="coPayment" class="cd-input" required style="margin-bottom:16px">
            <option value="banktransfer" selected><?= t('Bank Transfer','Virement bancaire') ?></option>
            <option value="mtn_momo">MTN Mobile Money</option>
            <option value="orange_money">Orange Money</option>
            <option value="paypal">PayPal</option>
            <option value="stripe"><?= t('Credit / Debit Card','Carte de crédit / débit') ?></option>
        </select>

        <div class="cd-divider"></div>
        <p class="cd-form-section"><i class="fa fa-lock"></i> <?= t('Create your password','Créez votre mot de passe') ?></p>

        <div class="cd-row">
            <div>
                <label class="cd-label" for="coPassword"><?= t('Password','Mot de passe') ?> <span style="color:#dc2626">*</span></label>
                <div class="cd-pw-wrap">
                    <input type="password" id="coPassword" class="cd-input" required
                        autocomplete="new-password" minlength="8">
                    <button type="button" class="cd-eye-btn" id="coEyeBtn"
                        aria-label="<?= t('Toggle visibility','Afficher/masquer') ?>">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>
            <div>
                <label class="cd-label" for="coPassword2"><?= t('Confirm Password','Confirmer') ?> <span style="color:#dc2626">*</span></label>
                <input type="password" id="coPassword2" class="cd-input" required
                    autocomplete="new-password" minlength="8">
            </div>
        </div>
        <div class="cd-pw-strength" id="coPwStrength" aria-live="polite">
            <div class="cd-pw-bar" id="coPwBar"></div>
            <span class="cd-pw-label" id="coPwLabel"></span>
        </div>

        <label class="cd-terms">
            <input type="checkbox" id="coTerms" required>
            <span><?= t('I agree to the','J\'accepte les') ?>
                <a href="<?= SITE_URL ?>/contact.php" target="_blank" rel="noopener">
                    <?= t('Terms &amp; Conditions','Conditions générales') ?>
                </a>
                <?= t('and authorise ' . COMPANY_NAME . ' to register this domain on my behalf.',
                    'et autorise ' . COMPANY_NAME . ' à enregistrer ce domaine en mon nom.') ?>
            </span>
        </label>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:8px">
            <button type="button" class="cd-btn cd-btn-secondary" id="coBackBtn">
                <i class="fa fa-arrow-left"></i> <?= t('Back','Retour') ?>
            </button>
            <button type="submit" class="cd-btn" id="coSubmitBtn" style="min-width:200px">
                <span class="cd-btn-label"><?= t('Place Order','Passer la commande') ?></span>
                <span class="cd-spinner"><span class="cd-spin-icon"></span></span>
            </button>
        </div>

    </form>
</div>

<!-- ════ STEP 3 — Confirmation ══════════════════════════════════════════════ -->
<div class="cd-card hidden" id="coStep3">
    <div class="cd-success">
        <div class="cd-success-icon"><i class="fa fa-check-circle"></i></div>
        <h2><?= t('Order Confirmed!','Commande confirmée !') ?></h2>
        <p id="coSuccessMsg"><?= t(
            'Your domain has been registered. Check your email for account details and an invoice.',
            'Votre domaine a été enregistré. Consultez votre email pour les détails du compte et la facture.') ?></p>
        <div class="cd-success-details" id="coSuccessDetails"></div>
        <div class="cd-success-actions">
            <a href="<?= SITE_URL ?>/account.php" class="cd-btn">
                <i class="fa fa-user"></i> <?= t('My Account','Mon compte') ?>
            </a>
            <a href="<?= SITE_URL ?>/order-cm.php" class="cd-btn cd-btn-secondary">
                <?= t('Register another .cm','Enregistrer un autre .cm') ?>
            </a>
        </div>
    </div>
</div>

</div><!-- #coPage -->

<script>
/* ═══════════════════════════════════════════════════════════════════════════
   CamDigit .CM Order Flow — vanilla JS (no jQuery dependency)
   ═══════════════════════════════════════════════════════════════════════════ */
'use strict';

const PROXY = '<?= htmlspecialchars($proxyUrl) ?>';

const state = { csrf: '', sld: '', domain: '' };

const $ = id => document.getElementById(id);
const step1 = $('coStep1');
const step2 = $('coStep2');
const step3 = $('coStep3');
const alertBox = $('coAlert');
const searchForm = $('coSearchForm');
const detailsForm = $('coDetailsForm');

document.addEventListener('DOMContentLoaded', async () => {
    try { await fetchCsrf(); } catch { return; }
    bindEvents();
    const preFill = <?= json_encode($preFillSld) ?>;
    if (preFill) { $('coSld').value = preFill; doSearch(); }
});

async function fetchCsrf() {
    try {
        const res = await apiProxy({ action: '__csrf__', _csrf: '' });
        if (res.csrf_token) { state.csrf = res.csrf_token; }
        else { throw new Error('No token returned'); }
    } catch {
        showAlert('Could not initialise session. Please refresh the page.', 'error');
        throw new Error('CSRF init failed');
    }
}

function bindEvents() {
    searchForm.addEventListener('submit', e => { e.preventDefault(); doSearch(); });

    $('coSld').addEventListener('input', function() {
        this.value = this.value.replace(/\.cm$/i, '').replace(/\s/g, '');
    });

    $('coRegisterBtn').addEventListener('click', () => goToStep(2));
    $('coChangeBtn').addEventListener('click', () => goToStep(1));
    $('coBackBtn').addEventListener('click', () => goToStep(1));
    detailsForm.addEventListener('submit', e => { e.preventDefault(); doSubmit(); });

    $('coEyeBtn').addEventListener('click', function() {
        const pw = $('coPassword');
        const isText = pw.type === 'text';
        pw.type = isText ? 'password' : 'text';
        this.querySelector('i').className = isText ? 'fa fa-eye' : 'fa fa-eye-slash';
    });

    $('coPassword').addEventListener('input', function() { updateStrength(this.value); });
}

function goToStep(n) {
    hideAlert();
    [step1, step2, step3].forEach(el => el.classList.add('hidden'));
    if (n === 1) step1.classList.remove('hidden');
    if (n === 2) { step2.classList.remove('hidden'); $('coOrderDomain').textContent = state.domain; }
    if (n === 3) step3.classList.remove('hidden');

    document.querySelectorAll('[data-step]').forEach(el => {
        const s = parseInt(el.dataset.step, 10);
        el.classList.toggle('active', s === n);
        el.classList.toggle('done', s < n);
    });

    if ($('coLine1')) $('coLine1').classList.toggle('done', n > 1);
    if ($('coLine2')) $('coLine2').classList.toggle('done', n > 2);

    const page = $('coPage');
    if (page) page.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function doSearch() {
    const sld = $('coSld').value.trim().toLowerCase().replace(/\.cm$/i, '');
    if (!sld || !/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/.test(sld)) {
        showAlert('Please enter a valid domain name (letters, numbers and hyphens, 2–63 characters, no leading/trailing hyphens).', 'error');
        return;
    }
    hideAlert();
    setBtnLoading('coSearchBtn', true);
    hideAvail();
    try {
        const res = await apiProxy({ action: 'DomainWhois', _csrf: state.csrf, domain: sld + '.cm' });
        if (res.result !== 'success') { showAlert(res.message || 'Domain lookup failed. Please try again.', 'error'); return; }
        state.sld = sld;
        state.domain = sld + '.cm';
        renderAvail(res);
    } catch (err) {
        showAlert(err.message || 'Could not check domain. Please try again.', 'error');
    } finally {
        setBtnLoading('coSearchBtn', false);
    }
}

function renderAvail(res) {
    const avail = $('coAvail');
    const isAvail = (res.status || '').toLowerCase() === 'available';
    $('coAvailDomain').textContent = state.domain;
    avail.className = 'cd-avail ' + (isAvail ? 'available' : 'taken');
    avail.style.display = 'block';
    if (isAvail) {
        $('coAvailIcon').innerHTML = '<i class="fa fa-check"></i>';
        $('coAvailStatus').textContent = 'Great news — this domain is available!';
        $('coRegisterBtn').classList.remove('hidden');
    } else {
        $('coAvailIcon').innerHTML = '<i class="fa fa-times"></i>';
        $('coAvailStatus').textContent = 'Sorry, this domain is already registered.';
        $('coRegisterBtn').classList.add('hidden');
    }
    loadTldSuggestions(state.sld);
}

const SUGGEST_TLDS = ['.com', '.org', '.net', '.biz', '.info', '.africa'];

async function loadTldSuggestions(sld) {
    const wrap = $('coSuggestions');
    const grid = $('coTldGrid');
    if (!wrap || !grid || !sld) return;

    wrap.classList.remove('hidden');
    grid.innerHTML = '<div class="cd-tld-loading"><span class="cd-spin-sm"></span> <?= t('Checking…','Vérification…') ?></div>';

    try {
        const res = await apiProxy({ action: 'CheckMultiAvailability', _csrf: state.csrf, sld, tlds: SUGGEST_TLDS });
        if (res.result !== 'success' || !Array.isArray(res.domains)) { wrap.classList.add('hidden'); return; }
        grid.innerHTML = res.domains.map(d => renderTldItem(d)).join('');
        if (!grid.innerHTML.trim()) wrap.classList.add('hidden');
    } catch {
        wrap.classList.add('hidden');
    }
}

function renderTldItem(d) {
    const isAvail  = d.status === 'available';
    const cls      = isAvail ? 'available' : 'taken';
    const badge    = isAvail ? 'Available' : 'Taken';
    const btnHtml  = isAvail
        ? `<a href="<?= SITE_URL ?>/order-domain.php?sld=${encodeURIComponent(d.domain.split('.')[0])}&tld=${encodeURIComponent('.' + d.tld.replace(/^\./,''))}" class="cd-btn">${'Register'} <i class="fa fa-arrow-right"></i></a>`
        : '';
    return `<div class="cd-tld-item ${cls}">
        <div class="cd-tld-item-top">
            <span class="cd-tld-item-name">${esc(d.domain)}</span>
            <span class="cd-tld-item-badge">${esc(badge)}</span>
        </div>
        ${btnHtml}
    </div>`;
}

function hideAvail() {
    const avail = $('coAvail');
    avail.style.display = 'none';
    avail.className = 'cd-avail';
    $('coRegisterBtn').classList.add('hidden');
    const sugg = $('coSuggestions');
    if (sugg) sugg.classList.add('hidden');
}

async function doSubmit() {
    hideAlert();
    const errs = validateForm();
    if (errs.length) { showAlert(errs.join('<br>'), 'error'); return; }
    setBtnLoading('coSubmitBtn', true);
    try {
        const clientRes = await apiProxy({
            action: 'AddClient', _csrf: state.csrf,
            firstname: $('coFirstname').value.trim(), lastname: $('coLastname').value.trim(),
            email: $('coEmail').value.trim(), password2: $('coPassword').value,
            phonenumber: $('coPhone').value.trim(), address1: $('coAddress').value.trim(),
            city: $('coCity').value.trim(), state: $('coState').value.trim(),
            postcode: $('coPostcode').value.trim(), country: $('coCountry').value,
            paymentmethod: $('coPayment').value,
        });
        if (clientRes.result !== 'success') {
            const msg = clientRes.message || 'Account creation failed.';
            if (/duplicate|already/i.test(msg)) {
                showAlert('An account with this email already exists. Please <a href="<?= SITE_URL ?>/login.php">log in</a> to place your order.', 'error');
            } else { showAlert(msg, 'error'); }
            return;
        }
        const orderRes = await apiProxy({
            action: 'AddOrder', _csrf: state.csrf,
            clientid: clientRes.clientid, paymentmethod: $('coPayment').value,
        });
        if (orderRes.result !== 'success') { showAlert(orderRes.message || 'Order failed. Please contact support.', 'error'); return; }
        renderSuccess(clientRes, orderRes);
        goToStep(3);
    } catch (err) {
        showAlert(err.message || 'An unexpected error occurred. Please try again.', 'error');
    } finally {
        setBtnLoading('coSubmitBtn', false);
    }
}

function validateForm() {
    const errs = [];
    const v = id => $(id).value.trim();
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    if (!v('coFirstname'))           errs.push('First name is required.');
    if (!v('coLastname'))            errs.push('Last name is required.');
    if (!emailRe.test(v('coEmail'))) errs.push('A valid email address is required.');
    if (!v('coAddress'))             errs.push('Address is required.');
    if (!v('coCity'))                errs.push('City is required.');
    if (!$('coCountry').value)       errs.push('Please select a country.');
    const pw = $('coPassword').value;
    if (!pw || pw.length < 8) errs.push('Password must be at least 8 characters.');
    if (pw !== $('coPassword2').value) errs.push('Passwords do not match.');
    if (!$('coTerms').checked) errs.push('Please accept the Terms & Conditions.');
    return errs;
}

function renderSuccess(clientRes, orderRes) {
    const rows = [
        ['Domain', state.domain], ['Order #', orderRes.orderid || '—'],
        ['Invoice #', orderRes.invoiceid || '—'], ['Email', $('coEmail').value.trim()],
    ];
    $('coSuccessDetails').innerHTML = rows.map(([k, v]) =>
        `<div class="cd-detail-row"><span>${esc(k)}</span><strong>${esc(String(v))}</strong></div>`
    ).join('');
}

async function apiProxy(params) {
    const res = await fetch(PROXY, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(params), credentials: 'same-origin',
    });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch { throw new Error('Unexpected server response. Please try again.'); }
    if (!res.ok && res.status !== 200) { throw new Error(json.message || `Server error (${res.status})`); }
    return json;
}

function updateStrength(pw) {
    const bar = $('coPwBar');
    const label = $('coPwLabel');
    let score = 0;
    if (pw.length >= 8)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    bar.className = 'cd-pw-bar';
    if (!pw) { label.textContent = ''; return; }
    if (score <= 1)      { bar.classList.add('weak');   label.textContent = 'Weak'; }
    else if (score <= 2) { bar.classList.add('fair');   label.textContent = 'Fair'; }
    else                 { bar.classList.add('strong'); label.textContent = 'Strong'; }
}

function showAlert(html, type) {
    alertBox.className = 'cd-alert ' + (type === 'error' ? 'cd-alert-error' : 'cd-alert-ok');
    alertBox.innerHTML = `<i class="fa fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ` + html;
    alertBox.style.display = 'flex';
}

function hideAlert() { alertBox.style.display = 'none'; }

function setBtnLoading(id, on) {
    const btn = $(id);
    if (!btn) return;
    btn.disabled = on;
    btn.classList.toggle('loading', on);
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
</script>

<?php cd_render_foot(); ?>
