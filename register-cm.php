<?php
/**
 * CamDigit .CM Domain Registration — self-contained single file
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/register-cm.php
 *
 * WHOIS, AddClient, and AddOrder are all handled here.
 * CSRF token is baked into the page HTML — no async fetch required.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';   // provides constants, session, helpers

// ── Detect WHMCS logged-in client ────────────────────────────────────────────
$loggedInClientId = cd_client_id();

// ── CSRF for this page (own key so it doesn't collide with the proxy CSRF) ───
if (empty($_SESSION['rcm_csrf'])) {
    $_SESSION['rcm_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['rcm_csrf'];

// ── AJAX handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $body   = json_decode((string) file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? '';
    $now    = time();

    // Validate CSRF
    if (!hash_equals($csrf, (string) ($body['_csrf'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token. Please refresh the page.']);
        exit;
    }

    // Rate-limit: 12 requests per 5 min per IP
    $ip    = cd_client_ip();
    $rlKey = 'rcm_rl_' . md5($ip);
    $hits  = array_filter((array) ($_SESSION[$rlKey] ?? []), fn ($t) => $now - $t < 300);
    if (count($hits) >= 12) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please wait a few minutes.']);
        exit;
    }
    $hits[] = $now;
    $_SESSION[$rlKey] = array_values($hits);

    // ── action=check-suggest : check multiple TLDs for other-extension grid ──
    if ($action === 'check-suggest') {
        $sld  = cd_sanitize_sld((string) ($body['sld'] ?? ''));
        $tlds = array_slice(array_unique((array) ($body['tlds'] ?? [])), 0, 8);
        if (!$sld) {
            echo json_encode(['result' => 'error', 'message' => 'Invalid domain name']);
            exit;
        }
        $results = [];
        foreach ($tlds as $rawTld) {
            $tld    = '.' . ltrim(strtolower(preg_replace('/[^a-z0-9.]/', '', (string) $rawTld)), '.');
            if (strlen($tld) < 2 || strlen($tld) > 20) continue;
            $domain = $sld . $tld;
            if ($tld === '.cm') {
                $status    = cd_check_cm_whois($domain);
                $results[] = ['domain' => $domain, 'tld' => $tld, 'status' => $status];
            } else {
                try {
                    $r         = whmcs_api('DomainWhois', ['domain' => $domain]);
                    $status    = strtolower((string) ($r['status'] ?? 'unknown'));
                    $results[] = ['domain' => $domain, 'tld' => $tld, 'status' => $status];
                } catch (RuntimeException) {
                    $results[] = ['domain' => $domain, 'tld' => $tld, 'status' => 'unknown'];
                }
            }
        }
        echo json_encode(['result' => 'success', 'domains' => $results]);
        exit;
    }

    // ── action=check : direct WHOIS against whois.nic.cm ─────────────────────
    if ($action === 'check') {
        $sld = strtolower(trim((string) ($body['sld'] ?? '')));
        if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $sld)) {
            echo json_encode(['error' => 'Invalid domain name. Use letters, numbers and hyphens only.']);
            exit;
        }
        $domain = $sld . '.cm';

        $fp = @fsockopen('whois.nic.cm', 43, $errno, $errstr, 10);
        if (!$fp) {
            error_log("[register-cm] whois.nic.cm unreachable ($errstr / $errno) for $domain");
            $_SESSION['rcm_domain']    = $domain;
            $_SESSION['rcm_domain_ts'] = $now;
            echo json_encode(['domain' => $domain, 'status' => 'available', '_fallback' => true]);
            exit;
        }

        fwrite($fp, $domain . "\r\n");
        $raw = '';
        stream_set_timeout($fp, 8);
        while (!feof($fp)) {
            $chunk = fread($fp, 4096);
            if ($chunk === false || $chunk === '') break;
            $raw .= $chunk;
        }
        fclose($fp);

        if (preg_match('/no object found/i', $raw)) {
            $_SESSION['rcm_domain']    = $domain;
            $_SESSION['rcm_domain_ts'] = $now;
            echo json_encode(['domain' => $domain, 'status' => 'available']);
        } elseif (preg_match('/Registry Domain ID/i', $raw)) {
            unset($_SESSION['rcm_domain'], $_SESSION['rcm_domain_ts']);
            echo json_encode(['domain' => $domain, 'status' => 'taken']);
        } else {
            error_log("[register-cm] Ambiguous WHOIS for $domain: " . substr($raw, 0, 300));
            $_SESSION['rcm_domain']    = $domain;
            $_SESSION['rcm_domain_ts'] = $now;
            echo json_encode(['domain' => $domain, 'status' => 'available', '_unclear' => true]);
        }
        exit;
    }

    // ── action=order : AddClient + AddOrder in one shot ───────────────────────
    if ($action === 'order') {
        $domain   = (string) ($_SESSION['rcm_domain']    ?? '');
        $domainTs = (int)    ($_SESSION['rcm_domain_ts'] ?? 0);

        if (!$domain || !str_ends_with($domain, '.cm')) {
            echo json_encode(['error' => 'No domain selected. Please search for a domain first.']);
            exit;
        }
        if ($now - $domainTs > 1800) {
            unset($_SESSION['rcm_domain'], $_SESSION['rcm_domain_ts']);
            echo json_encode(['error' => 'Domain reservation expired (30 min). Please search again.']);
            exit;
        }

        $pay = (string) ($body['paymentmethod'] ?? 'banktransfer');
        if (!in_array($pay, VALID_PAY_METHODS, true)) {
            $pay = 'banktransfer';
        }

        if ($loggedInClientId > 0) {
            $clientId = $loggedInClientId;
        } else {
            $fname   = trim((string) ($body['firstname']  ?? ''));
            $lname   = trim((string) ($body['lastname']   ?? ''));
            $email   = filter_var(trim((string) ($body['email'] ?? '')), FILTER_VALIDATE_EMAIL);
            $phone   = trim(preg_replace('/[^\d+\-\s()]/', '', (string) ($body['phone']   ?? '')));
            $addr    = trim((string) ($body['address']    ?? ''));
            $city    = trim((string) ($body['city']       ?? ''));
            $state   = trim((string) ($body['state']      ?? ''));
            $post    = trim((string) ($body['postcode']   ?? ''));
            $country = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($body['country'] ?? '')), 0, 2));
            $pass    = (string) ($body['password'] ?? '');

            $errs = [];
            if (!$fname)                $errs[] = 'First name is required.';
            if (!$lname)                $errs[] = 'Last name is required.';
            if (!$email)                $errs[] = 'A valid email address is required.';
            if (!$addr)                 $errs[] = 'Address is required.';
            if (!$city)                 $errs[] = 'City is required.';
            if (strlen($country) !== 2) $errs[] = 'Please select a country.';
            if (strlen($pass) < 8)      $errs[] = 'Password must be at least 8 characters.';
            if ($errs) { echo json_encode(['error' => implode(' ', $errs)]); exit; }

            $clientRes = whmcs_api('AddClient', [
                'firstname'   => $fname,
                'lastname'    => $lname,
                'email'       => $email,
                'password2'   => $pass,
                'phonenumber' => $phone ?: '+237600000000',
                'address1'    => $addr,
                'city'        => $city,
                'state'       => $state,
                'postcode'    => $post ?: '00000',
                'country'     => $country,
                'currency'    => DEFAULT_CURRENCY_ID,
            ]);

            if (($clientRes['result'] ?? '') === 'success') {
                $clientId = (int) ($clientRes['clientid'] ?? 0);
            } else {
                $errMsg = $clientRes['message'] ?? '';
                if (preg_match('/duplicate|already exist|already registered/i', $errMsg)) {
                    $findRes  = whmcs_api('GetClients', ['search' => (string) $email, 'limitstart' => 0, 'limitnum' => 1]);
                    $clientId = (int) (($findRes['clients']['client'][0]['id'] ?? 0));
                    if (!$clientId) {
                        echo json_encode(['error' => 'An account with this email already exists. Please <a href="' . SITE_URL . '/login.php">log in</a> to complete the order.']);
                        exit;
                    }
                } else {
                    echo json_encode(['error' => $errMsg ?: 'Account creation failed. Please try again.']);
                    exit;
                }
            }
        }

        $orderRes = whmcs_api('AddOrder', [
            'clientid'      => $clientId,
            'paymentmethod' => $pay,
            'domain[0]'     => $domain,
            'domaintype[0]' => 'register',
            'regperiod[0]'  => 1,
            'nameserver1'   => CM_NS1,
            'nameserver2'   => CM_NS2,
        ]);

        if (($orderRes['result'] ?? '') !== 'success') {
            echo json_encode(['error' => $orderRes['message'] ?? 'Order placement failed. Please contact support.']);
            exit;
        }

        unset($_SESSION['rcm_domain'], $_SESSION['rcm_domain_ts']);

        $invoiceId = (int) ($orderRes['invoiceid'] ?? 0);
        echo json_encode([
            'success'    => true,
            'domain'     => $domain,
            'orderid'    => $orderRes['orderid'] ?? 0,
            'invoiceid'  => $invoiceId,
            'clientid'   => $clientId,
            'invoiceUrl' => SITE_URL . '/account-invoices.php?id=' . $invoiceId,
            'loggedIn'   => $loggedInClientId > 0,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action.']);
    exit;
}

// ── Pre-fill ?sld= ────────────────────────────────────────────────────────────
$preFillSld = '';
if (!empty($_GET['sld'])) {
    $raw = strtolower(trim((string) $_GET['sld']));
    $raw = preg_replace('/\.cm$/i', '', $raw);
    if (preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $raw)) {
        $preFillSld = $raw;
    }
}

// Fetch logged-in client details
$clientInfo = null;
if ($loggedInClientId > 0) {
    $ciRes = whmcs_api('GetClientsDetails', ['clientid' => $loggedInClientId, 'stats' => false]);
    if (($ciRes['result'] ?? '') === 'success') {
        $clientInfo = $ciRes;
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
            <button type="submit" class="cd-btn" id="coSearchBtn">
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
            <div><h4><?= t('Secure','Sécurisé') ?></h4><p>SSL &amp; DNSSEC</p></div>
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
    <p class="cd-muted" id="coStep2Sub"><?= t('We\'ll create your account and place the order in one step.',
        'Nous créerons votre compte et passerons la commande en une seule étape.') ?></p>

    <div class="cd-order-bar">
        <i class="fa fa-globe"></i>
        <strong><?= t('Domain:','Domaine:') ?></strong>
        <span class="cd-order-bar-domain" id="coOrderDomain"></span>
        <button type="button" class="cd-change-btn" id="coChangeBtn"><?= t('Change','Modifier') ?></button>
    </div>

    <!-- Logged-in notice (hidden initially; shown by JS when LOGGED_IN) -->
    <div class="cd-logged-notice hidden" id="coLoggedInNotice">
        <i class="fa fa-user-check"></i>
        <?php if ($clientInfo): ?>
            <?= t('Logged in as','Connecté en tant que') ?>
            <strong><?= htmlspecialchars($clientInfo['firstname'] . ' ' . $clientInfo['lastname']) ?></strong>
            — <?= t('your details are pre-filled.','vos détails sont pré-remplis.') ?>
        <?php else: ?>
            <?= t('You are logged in. Your account details will be used.', 'Vous êtes connecté. Vos coordonnées seront utilisées.') ?>
        <?php endif ?>
    </div>

    <!-- Client info block (hidden initially) -->
    <?php if ($clientInfo): ?>
    <div class="cd-client-info hidden" id="coClientInfo">
        <div class="cd-client-info-row">
            <span class="cd-client-info-label"><?= t('Name','Nom') ?></span>
            <span><?= htmlspecialchars($clientInfo['firstname'] . ' ' . $clientInfo['lastname']) ?></span>
        </div>
        <div class="cd-client-info-row">
            <span class="cd-client-info-label"><?= t('Email','Email') ?></span>
            <span><?= htmlspecialchars($clientInfo['email']) ?></span>
        </div>
        <div class="cd-client-info-row">
            <span class="cd-client-info-label"><?= t('Country','Pays') ?></span>
            <span><?= htmlspecialchars($clientInfo['country'] ?? '—') ?></span>
        </div>
    </div>
    <?php else: ?>
    <div class="hidden" id="coClientInfo"></div>
    <?php endif ?>

    <form id="coDetailsForm" novalidate autocomplete="on">

        <!-- Guest-only contact fields -->
        <div id="coGuestContact">
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
        </div><!-- #coGuestContact -->

        <p class="cd-form-section"><i class="fa fa-credit-card"></i> <?= t('Payment Method','Mode de paiement') ?></p>
        <label class="cd-label" for="coPayment"><?= t('Pay with','Payer avec') ?> <span style="color:#dc2626">*</span></label>
        <select id="coPayment" class="cd-input" required style="margin-bottom:16px">
            <option value="banktransfer" selected><?= t('Bank Transfer','Virement bancaire') ?></option>
            <option value="mtn_momo">MTN Mobile Money</option>
            <option value="orange_money">Orange Money</option>
            <option value="paypal">PayPal</option>
            <option value="stripe"><?= t('Credit / Debit Card','Carte de crédit / débit') ?></option>
        </select>

        <!-- Guest-only password -->
        <div id="coGuestPassword">
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
            <div class="cd-pw-strength" aria-live="polite">
                <div class="cd-pw-bar" id="coPwBar"></div>
                <span class="cd-pw-label" id="coPwLabel"></span>
            </div>
        </div><!-- #coGuestPassword -->

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
        <p><?= t(
            'Your domain has been registered. Check your email for account details and an invoice.',
            'Votre domaine a été enregistré. Consultez votre email pour les détails du compte et la facture.') ?></p>
        <div class="cd-success-details" id="coSuccessDetails"></div>
        <div class="cd-success-actions">
            <a id="coInvoiceLink" href="<?= SITE_URL ?>/account.php" class="cd-btn">
                <i class="fa fa-file-invoice-dollar"></i> <?= t('View Invoice','Voir la facture') ?>
            </a>
            <a href="<?= SITE_URL ?>/register-cm.php" class="cd-btn cd-btn-secondary">
                <?= t('Register another .cm','Enregistrer un autre .cm') ?>
            </a>
        </div>
    </div>
</div>

</div><!-- #coPage -->

<script>
'use strict';

const CSRF     = <?= json_encode($csrf) ?>;
const SELF     = <?= json_encode(SITE_URL . '/register-cm.php') ?>;
const PREFILL  = <?= json_encode($preFillSld) ?>;
const LOGGED_IN = <?= json_encode($loggedInClientId > 0) ?>;

const $ = id => document.getElementById(id);
const step1 = $('coStep1'), step2 = $('coStep2'), step3 = $('coStep3');
const alertBox = $('coAlert');
const state = { sld: '', domain: '' };

document.addEventListener('DOMContentLoaded', () => {
    bindEvents();
    if (LOGGED_IN) {
        $('coGuestContact').classList.add('hidden');
        $('coGuestPassword').classList.add('hidden');
        $('coLoggedInNotice').classList.remove('hidden');
        $('coClientInfo').classList.remove('hidden');
        $('coStep2Sub').textContent = <?= json_encode(t(
            'Your details are shown below — select a payment method and place your order.',
            'Vos coordonnées sont affichées ci-dessous — sélectionnez un mode de paiement.')) ?>;
    }
    if (PREFILL) { $('coSld').value = PREFILL; doSearch(); }
});

function bindEvents() {
    $('coSearchForm').addEventListener('submit', e => { e.preventDefault(); doSearch(); });
    $('coSld').addEventListener('input', function() {
        this.value = this.value.replace(/\.cm$/i, '').replace(/\s/g, '');
    });
    $('coRegisterBtn').addEventListener('click', () => goToStep(2));
    $('coChangeBtn').addEventListener('click', () => goToStep(1));
    $('coBackBtn').addEventListener('click', () => goToStep(1));
    $('coDetailsForm').addEventListener('submit', e => { e.preventDefault(); doSubmit(); });
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
        const s = +el.dataset.step;
        el.classList.toggle('active', s === n);
        el.classList.toggle('done', s < n);
    });
    $('coLine1').classList.toggle('done', n > 1);
    $('coLine2').classList.toggle('done', n > 2);
    $('coPage').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function doSearch() {
    const sld = $('coSld').value.trim().toLowerCase().replace(/\.cm$/i, '');
    if (!sld || !/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/.test(sld)) {
        showAlert('Please enter a valid domain name (2–63 chars, letters/numbers/hyphens, no leading/trailing hyphens).', 'error');
        return;
    }
    hideAlert();
    setLoading('coSearchBtn', true);
    hideAvail();
    try {
        const res = await post('check', { sld });
        if (res.error) { showAlert(res.error, 'error'); return; }
        state.sld = sld;
        state.domain = res.domain;
        renderAvail(res);
    } catch (e) {
        showAlert(e.message || 'Domain lookup failed. Please try again.', 'error');
    } finally {
        setLoading('coSearchBtn', false);
    }
}

function renderAvail(res) {
    const avail = $('coAvail');
    const isAvail = res.status === 'available';
    $('coAvailDomain').textContent = res.domain;
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
    grid.innerHTML = '<div class="cd-tld-loading"><span class="cd-spin-sm"></span> Checking&hellip;</div>';

    try {
        const res = await post('check-suggest', { sld, tlds: SUGGEST_TLDS });
        if (res.result !== 'success' || !Array.isArray(res.domains)) { wrap.classList.add('hidden'); return; }
        grid.innerHTML = res.domains.map(d => renderTldItem(d)).join('');
        if (!grid.innerHTML.trim()) wrap.classList.add('hidden');
    } catch {
        wrap.classList.add('hidden');
    }
}

function renderTldItem(d) {
    const isAvail = d.status === 'available';
    const cls     = isAvail ? 'available' : 'taken';
    const badge   = isAvail ? 'Available' : 'Taken';
    const sldPart = d.domain.split('.')[0];
    const tldPart = d.tld.replace(/^\./, '');
    const btnHtml = isAvail
        ? `<a href="${SELF.replace('register-cm.php','order-domain.php')}?sld=${encodeURIComponent(sldPart)}&tld=${encodeURIComponent('.' + tldPart)}" class="cd-btn">Register <i class="fa fa-arrow-right"></i></a>`
        : '';
    return `<div class="cd-tld-item ${cls}">
        <div class="cd-tld-item-header">
            <span class="cd-tld-ext">${esc(d.tld)}</span>
            <span class="cd-tld-item-badge">${esc(badge)}</span>
        </div>
        <div class="cd-tld-item-domain">${esc(d.domain)}</div>
        ${btnHtml}
    </div>`;
}

function hideAvail() {
    const el = $('coAvail');
    el.style.display = 'none';
    el.className = 'cd-avail';
    $('coRegisterBtn').classList.add('hidden');
    const sugg = $('coSuggestions');
    if (sugg) sugg.classList.add('hidden');
}

async function doSubmit() {
    hideAlert();
    const errs = validateForm();
    if (errs.length) { showAlert(errs.join('<br>'), 'error'); return; }
    setLoading('coSubmitBtn', true);
    try {
        const orderData = { paymentmethod: $('coPayment').value };
        if (!LOGGED_IN) {
            Object.assign(orderData, {
                firstname: $('coFirstname').value.trim(),
                lastname:  $('coLastname').value.trim(),
                email:     $('coEmail').value.trim(),
                phone:     $('coPhone').value.trim(),
                address:   $('coAddress').value.trim(),
                city:      $('coCity').value.trim(),
                state:     $('coState').value.trim(),
                postcode:  $('coPostcode').value.trim(),
                country:   $('coCountry').value,
                password:  $('coPassword').value,
            });
        }
        const res = await post('order', orderData);
        if (res.error) { showAlert(res.error, 'error'); return; }
        renderSuccess(res);
        goToStep(3);
    } catch (e) {
        showAlert(e.message || 'An unexpected error occurred. Please try again.', 'error');
    } finally {
        setLoading('coSubmitBtn', false);
    }
}

function validateForm() {
    const errs = [];
    if (!LOGGED_IN) {
        const v = id => $(id).value.trim();
        if (!v('coFirstname')) errs.push('First name is required.');
        if (!v('coLastname'))  errs.push('Last name is required.');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v('coEmail'))) errs.push('A valid email address is required.');
        if (!v('coAddress'))   errs.push('Address is required.');
        if (!v('coCity'))      errs.push('City is required.');
        if (!$('coCountry').value) errs.push('Please select a country.');
        const pw = $('coPassword').value, pw2 = $('coPassword2').value;
        if (pw.length < 8) errs.push('Password must be at least 8 characters.');
        if (pw !== pw2)    errs.push('Passwords do not match.');
    }
    if (!$('coTerms').checked) errs.push('Please accept the Terms & Conditions.');
    return errs;
}

function renderSuccess(res) {
    const rows = [
        ['Domain', res.domain || '—'],
        ['Order #', res.orderid || '—'],
        ['Invoice #', res.invoiceid || '—'],
    ];
    if (!LOGGED_IN) rows.push(['Email', $('coEmail').value.trim()]);
    $('coSuccessDetails').innerHTML = rows
        .map(([k, v]) => `<div class="cd-detail-row"><span>${esc(k)}</span><strong>${esc(String(v))}</strong></div>`)
        .join('');
    if (res.invoiceUrl) {
        $('coInvoiceLink').href = res.invoiceUrl;
        if (res.loggedIn) { setTimeout(() => { window.location.href = res.invoiceUrl; }, 1500); }
    }
}

async function post(action, data) {
    const res = await fetch(SELF + '?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ _csrf: CSRF, ...data }),
    });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch { throw new Error('Unexpected server response. Please try again.'); }
    return json;
}

function updateStrength(pw) {
    const bar = $('coPwBar'), label = $('coPwLabel');
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

function setLoading(id, on) {
    const b = $(id);
    if (!b) return;
    b.disabled = on;
    b.classList.toggle('loading', on);
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
</script>

<?php cd_render_foot(); ?>
