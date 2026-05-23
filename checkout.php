<?php
/**
 * CamDigit — Checkout (cart → WHMCS AddOrder)
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/checkout.php
 *
 * Step 4 of the funnel: collect client details (or use logged-in client),
 * choose a payment method, then push every cart item into a single AddOrder
 * call so WHMCS issues one invoice.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/cart.php';

if (cd_cart_is_empty()) {
    header('Location: ' . SITE_URL . '/cart.php');
    exit;
}

$cart   = cd_cart();
$totals = cd_cart_totals();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'order') {
    cd_csrf_check();
    if (!cd_rate_limit('checkout', 5, 600)) {
        $errors[] = t('Too many attempts. Please wait a few minutes.',
                      'Trop de tentatives. Veuillez patienter quelques minutes.');
    }

    $clientId = cd_client_id();
    if (!$errors && $clientId === 0) {
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
            if (empty($params[$f])) $errors[] = ucfirst($f) . ' ' . t('is required.','est requis.');
        }
        if (strlen($params['password2']) < 8) $errors[] = t('Password must be at least 8 characters.', 'Le mot de passe doit comporter au moins 8 caractères.');
        if (empty($_POST['terms'])) $errors[] = t('Please accept the terms.', 'Veuillez accepter les conditions.');

        if (!$errors) {
            try {
                $r = whmcs_api('AddClient', $params);
                if (($r['result'] ?? '') !== 'success') {
                    $msg = (string)($r['message'] ?? 'Account creation failed.');
                    if (stripos($msg, 'duplicate') !== false || stripos($msg, 'already') !== false) {
                        $errors[] = t('An account with this email already exists. Please log in first.',
                                      'Un compte existe déjà avec cet email. Veuillez vous connecter.');
                    } else $errors[] = $msg;
                } else {
                    $clientId = (int)$r['clientid'];
                    $_SESSION['uid'] = $clientId;
                }
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
    } elseif ($clientId > 0 && empty($_POST['terms'])) {
        $errors[] = t('Please accept the terms.', 'Veuillez accepter les conditions.');
    }

    if (!$errors && $clientId > 0) {
        $payment = in_array($_POST['paymentmethod'] ?? '', VALID_PAY_METHODS, true)
            ? $_POST['paymentmethod'] : 'banktransfer';

        $orderParams = [
            'clientid'      => $clientId,
            'paymentmethod' => $payment,
            'noemail'       => false,
        ];

        if ($cart['hosting']) {
            $orderParams['pid']          = [(int)$cart['hosting']['pid']];
            $orderParams['billingcycle'] = [(string)$cart['hosting']['billingcycle']];
            // attach main domain if present
            if ($cart['main_domain'] && isset($cart['domains'][$cart['main_domain']])) {
                $d = $cart['domains'][$cart['main_domain']];
                $orderParams['domain']     = [$d['domain']];
                $orderParams['domaintype'] = [$d['type']];
                $orderParams['regperiod']  = [(int)$d['years']];
                if (str_ends_with($d['domain'], '.cm')) {
                    $orderParams['nameserver1'] = CM_NS1;
                    $orderParams['nameserver2'] = CM_NS2;
                }
            }
        }

        // additional domains (or all domains if no hosting)
        $skip = $cart['hosting'] ? ($cart['main_domain'] ?? null) : null;
        $extraDomains = $extraTypes = $extraPeriods = [];
        foreach ($cart['domains'] as $d) {
            if ($d['domain'] === $skip) continue;
            $extraDomains[] = $d['domain'];
            $extraTypes[]   = $d['type'];
            $extraPeriods[] = (int)$d['years'];
        }
        if ($extraDomains) {
            if (isset($orderParams['domain'])) {
                $orderParams['domain']     = array_merge($orderParams['domain'],     $extraDomains);
                $orderParams['domaintype'] = array_merge($orderParams['domaintype'], $extraTypes);
                $orderParams['regperiod']  = array_merge($orderParams['regperiod'],  $extraPeriods);
            } else {
                $orderParams['domain']     = $extraDomains;
                $orderParams['domaintype'] = $extraTypes;
                $orderParams['regperiod']  = $extraPeriods;
            }
        }

        if (!empty($cart['promo'])) {
            $orderParams['promocode'] = (string)$cart['promo'];
        }

        try {
            $r = whmcs_api('AddOrder', $orderParams);
            if (($r['result'] ?? '') !== 'success') {
                $errors[] = (string)($r['message'] ?? 'Order failed.');
            } else {
                cd_cart_clear();
                $invoiceId = (int)($r['invoiceid'] ?? 0);
                header('Location: ' . SITE_URL . '/account-invoices.php?id=' . $invoiceId);
                exit;
            }
        } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    }
}

cd_render_head(
    t('Checkout', 'Paiement'),
    t('Almost <span style="color:var(--theme2,#ffa31a)">there</span>',
      'Presque <span style="color:var(--theme2,#ffa31a)">terminé</span>'),
    t('Confirm your details and choose a payment method.',
      'Confirmez vos coordonnées et choisissez un mode de paiement.')
);
?>

<div class="cd-progress" style="margin-top:-50px;background:#fff;padding:18px;border-radius:14px;box-shadow:0 4px 24px rgba(15,13,29,.06);position:relative;z-index:5">
    <div class="cd-prog-step done"><div class="cd-prog-step-icon"><i class="fa fa-check"></i></div><div class="cd-prog-step-label"><?= t('Choose','Choix') ?></div></div>
    <div class="cd-prog-line done"></div>
    <div class="cd-prog-step done"><div class="cd-prog-step-icon"><i class="fa fa-check"></i></div><div class="cd-prog-step-label"><?= t('Cart','Panier') ?></div></div>
    <div class="cd-prog-line done"></div>
    <div class="cd-prog-step active"><div class="cd-prog-step-icon"><i class="fa fa-user"></i></div><div class="cd-prog-step-label"><?= t('Details','Coordonnées') ?></div></div>
    <div class="cd-prog-line"></div>
    <div class="cd-prog-step"><div class="cd-prog-step-icon"><i class="fa fa-credit-card"></i></div><div class="cd-prog-step-label"><?= t('Payment','Paiement') ?></div></div>
</div>

<?php if ($errors): ?>
<div class="cd-alert cd-alert-error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
</div>
<?php endif ?>

<div class="cd-cart-grid">
    <div>
        <div class="cd-card">
            <form method="post" action="">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
                <input type="hidden" name="step" value="order">

                <?php if (cd_client_id() === 0): ?>
                <div class="cd-form-section"><i class="fa fa-user"></i><?= t('Your details', 'Vos coordonnées') ?></div>
                <p class="cd-muted" style="margin-top:-6px"><?= t('Already have an account?','Vous avez déjà un compte ?') ?>
                    <a href="<?= SITE_URL ?>/login.php?next=<?= rawurlencode($_SERVER['REQUEST_URI']) ?>"><?= t('Log in','Se connecter') ?></a>
                </p>

                <div class="cd-row">
                    <div><label class="cd-label"><?= t('First name','Prénom') ?></label>
                        <input class="cd-input" type="text" name="firstname" required></div>
                    <div><label class="cd-label"><?= t('Last name','Nom') ?></label>
                        <input class="cd-input" type="text" name="lastname" required></div>
                </div>
                <div class="cd-row">
                    <div><label class="cd-label">Email</label>
                        <input class="cd-input" type="email" name="email" required></div>
                    <div><label class="cd-label"><?= t('Phone','Téléphone') ?></label>
                        <input class="cd-input" type="tel" name="phone" required></div>
                </div>
                <div class="cd-row">
                    <div><label class="cd-label"><?= t('Address','Adresse') ?></label>
                        <input class="cd-input" type="text" name="address1" required></div>
                </div>
                <div class="cd-row">
                    <div><label class="cd-label"><?= t('City','Ville') ?></label>
                        <input class="cd-input" type="text" name="city" required></div>
                    <div><label class="cd-label"><?= t('State/Region','Région') ?></label>
                        <input class="cd-input" type="text" name="state"></div>
                    <div><label class="cd-label"><?= t('Postal code','Code postal') ?></label>
                        <input class="cd-input" type="text" name="postcode"></div>
                </div>
                <div class="cd-row">
                    <div><label class="cd-label"><?= t('Country','Pays') ?></label>
                        <select class="cd-input" name="country" required>
                            <?php foreach (cd_countries() as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $code === 'CM' ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach ?>
                        </select></div>
                    <div><label class="cd-label"><?= t('Password','Mot de passe') ?></label>
                        <input class="cd-input" type="password" name="password" minlength="8" required></div>
                </div>
                <?php else: ?>
                <div class="cd-logged-notice">
                    <i class="fa fa-check-circle"></i>
                    <?= t('Signed in — your saved details will be used for this order.',
                          'Connecté — vos coordonnées enregistrées seront utilisées.') ?>
                </div>
                <?php endif ?>

                <div class="cd-form-section" style="margin-top:24px"><i class="fa fa-credit-card"></i><?= t('Payment method', 'Mode de paiement') ?></div>

                <div class="cd-pay-options">
                    <?php
                    $payMethods = [
                        ['v' => 'mtn_momo',     'lbl' => 'MTN Mobile Money',                        'icon' => 'fa-solid fa-mobile-screen', 'desc' => t('Pay with your MTN MoMo wallet','Paiement via MTN MoMo')],
                        ['v' => 'orange_money', 'lbl' => 'Orange Money',                            'icon' => 'fa-solid fa-mobile-screen', 'desc' => t('Pay with Orange Money','Paiement Orange Money')],
                        ['v' => 'stripe',       'lbl' => t('Credit / Debit card','Carte bancaire'), 'icon' => 'fa-brands fa-cc-visa',     'desc' => t('Visa, Mastercard — secured by Stripe','Visa, Mastercard via Stripe')],
                        ['v' => 'paypal',       'lbl' => 'PayPal',                                  'icon' => 'fa-brands fa-paypal',      'desc' => t('Pay from your PayPal account','Paiement via PayPal')],
                        ['v' => 'banktransfer', 'lbl' => t('Bank transfer','Virement bancaire'),    'icon' => 'fa-solid fa-building-columns','desc' => t('Pay by bank transfer (manual confirmation)','Virement bancaire (confirmation manuelle)')],
                    ];
                    foreach ($payMethods as $i => $m): ?>
                    <label class="cd-pay-option<?= $i === 0 ? ' active' : '' ?>">
                        <input type="radio" name="paymentmethod" value="<?= $m['v'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        <i class="<?= $m['icon'] ?>"></i>
                        <div class="cd-pay-option-text">
                            <strong><?= htmlspecialchars($m['lbl']) ?></strong>
                            <span><?= $m['desc'] ?></span>
                        </div>
                        <i class="fa fa-circle-check check"></i>
                    </label>
                    <?php endforeach ?>
                </div>
                <style>
                .cd-pay-options{display:grid;gap:10px;margin-top:6px}
                .cd-pay-option{display:flex;align-items:center;gap:14px;padding:14px 16px;background:#fff;border:1.5px solid #e3e8f0;border-radius:10px;cursor:pointer;transition:all .15s;position:relative}
                .cd-pay-option:hover{border-color:var(--theme,#236a25)}
                .cd-pay-option.active{border-color:var(--theme,#236a25);background:#f0fdf4}
                .cd-pay-option input{position:absolute;opacity:0;pointer-events:none}
                .cd-pay-option>i:first-of-type{font-size:22px;color:var(--theme2,#ffa31a);width:30px;text-align:center;flex-shrink:0}
                .cd-pay-option-text{flex:1}
                .cd-pay-option-text strong{display:block;font-size:14px;color:#0f0d1d}
                .cd-pay-option-text span{font-size:12px;color:#6b7785}
                .cd-pay-option .check{color:var(--theme,#236a25);font-size:18px;opacity:0;transition:opacity .2s}
                .cd-pay-option.active .check{opacity:1}
                </style>
                <script>
                document.querySelectorAll('.cd-pay-option').forEach(o => {
                    o.addEventListener('click', () => {
                        document.querySelectorAll('.cd-pay-option').forEach(x => x.classList.remove('active'));
                        o.classList.add('active');
                        o.querySelector('input').checked = true;
                    });
                });
                </script>

                <div class="cd-terms" style="margin-top:22px">
                    <input type="checkbox" id="terms" name="terms" value="1" required>
                    <label for="terms">
                        <?= t('I have read and accept the','J\'ai lu et j\'accepte les') ?>
                        <a href="<?= SITE_URL ?>/cart-terms.php" target="_blank"><?= t('terms of service','conditions générales') ?></a>
                        <?= t('and','et la') ?>
                        <a href="<?= SITE_URL ?>/privacy.php" target="_blank"><?= t('privacy policy','politique de confidentialité') ?></a>.
                    </label>
                </div>

                <button class="cd-summary-cta" type="submit" style="max-width:none">
                    <?= t('Place order','Finaliser la commande') ?> <i class="fa fa-lock"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Order summary (sticky right) -->
    <div class="cd-summary">
        <div class="cd-summary-card">
            <div class="cd-summary-head">
                <span><i class="fa fa-receipt" style="margin-right:8px;color:var(--theme2,#ffa31a)"></i><?= t('Your order','Votre commande') ?></span>
                <a href="<?= SITE_URL ?>/cart.php" style="font-size:12px;font-weight:500;color:rgba(255,255,255,.8)">
                    <?= t('Edit','Modifier') ?>
                </a>
            </div>
            <div class="cd-summary-body">
                <?php if ($cart['hosting']): ?>
                <div class="cd-summary-line">
                    <span><?= htmlspecialchars((string)$cart['hosting']['name']) ?>
                        <small style="display:block;color:#9aa5b8;font-size:11px"><?= (int)$cart['hosting']['years'] ?>
                            <?= $cart['hosting']['years'] === 1 ? t('year','an') : t('years','ans') ?></small>
                    </span>
                    <strong><?= cd_money((float)$cart['hosting']['price']) ?></strong>
                </div>
                <?php endif ?>

                <?php foreach ($cart['domains'] as $d): ?>
                <div class="cd-summary-line">
                    <span><?= htmlspecialchars($d['domain']) ?>
                        <small style="display:block;color:#9aa5b8;font-size:11px"><?= (int)$d['years'] ?>
                            <?= $d['years'] === 1 ? t('year','an') : t('years','ans') ?></small>
                    </span>
                    <strong>
                        <?php if ($d['free']): ?>
                            <span style="color:var(--theme,#236a25)"><?= t('FREE','GRATUIT') ?></span>
                        <?php else: ?>
                            <?= cd_money((float)$d['price']) ?>
                        <?php endif ?>
                    </strong>
                </div>
                <?php endforeach ?>

                <?php if ($totals['discount'] > 0): ?>
                <div class="cd-summary-divider"></div>
                <div class="cd-summary-line discount">
                    <span><i class="fa fa-tags" style="margin-right:6px"></i><?= t('You save','Vous économisez') ?></span>
                    <strong>− <?= cd_money($totals['discount']) ?></strong>
                </div>
                <?php endif ?>

                <div class="cd-summary-divider"></div>
                <div class="cd-summary-total">
                    <span><strong><?= t('Total','Total') ?></strong></span>
                    <span class="cd-summary-total-num"><?= cd_money($totals['total']) ?></span>
                </div>
                <div class="cd-summary-tax"><?= t('VAT included where applicable','TTC selon votre pays') ?></div>

                <div class="cd-summary-trust">
                    <span><i class="fa fa-lock"></i><?= t('Secure checkout','Paiement sécurisé') ?></span>
                    <span><i class="fa fa-rotate-left"></i><?= t('30-day guarantee','Garantie 30 jours') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php cd_render_foot(); ?>
