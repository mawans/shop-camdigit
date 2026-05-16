<?php
/**
 * CamDigit Hosting / Product Order Page
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/order-hosting.php
 *
 * Lists products from a WHMCS product group (default: shared hosting) and lets
 * the user purchase one. Optionally attaches a new domain registration.
 *
 *   ?gid=N        → product group id (defaults to PRODUCT_GROUP_DEFAULT)
 *   ?pid=N        → pre-selected product id (skip listing, jump to checkout)
 *
 * All API calls happen server-side via whmcs_api() — no credentials in browser.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

// Default product group id — change to your shared-hosting group id
const PRODUCT_GROUP_DEFAULT = 1;

$gid    = (int) ($_GET['gid'] ?? $_POST['gid'] ?? PRODUCT_GROUP_DEFAULT);
$pid    = (int) ($_GET['pid'] ?? $_POST['pid'] ?? 0);
$step   = (string) ($_POST['step'] ?? '');
$errors = [];

// ── Fetch product list for the group ────────────────────────────────────────
$products = [];
try {
    $r = whmcs_api('GetProducts', ['gid' => $gid]);
    if (($r['result'] ?? '') === 'success') {
        $products = $r['products']['product'] ?? [];
    }
} catch (RuntimeException $e) {
    $errors[] = $e->getMessage();
}

// Build a quick lookup: pid → product
$byPid = [];
foreach ($products as $p) $byPid[(int)$p['pid']] = $p;
$selected = $pid && isset($byPid[$pid]) ? $byPid[$pid] : null;

// ── Step: submit order ──────────────────────────────────────────────────────
if ($step === 'order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('order_hosting', 5, 600)) {
        $errors[] = 'Too many attempts. Please wait a few minutes.';
    } else {
        $pid = (int) $_POST['pid'];
        if (!isset($byPid[$pid])) $errors[] = 'Invalid product.';

        $clientId = cd_client_id();
        if ($clientId === 0) {
            // Create the client
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
            foreach (['firstname','lastname','email','address1','city','country'] as $f) {
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
            $billing = (string)($_POST['billingcycle'] ?? 'monthly');
            $payment = in_array($_POST['paymentmethod'] ?? '', VALID_PAY_METHODS, true)
                ? $_POST['paymentmethod'] : 'banktransfer';

            $orderParams = [
                'clientid'      => $clientId,
                'pid'           => [$pid],
                'billingcycle'  => [$billing],
                'paymentmethod' => $payment,
                'noemail'       => false,
            ];

            // Optional attached domain
            $sld    = cd_sanitize_sld($_POST['sld'] ?? '');
            $tld    = strtolower(preg_replace('/[^a-z0-9.]/', '', (string)($_POST['tld'] ?? '')) ?? '');
            $useDom = (string)($_POST['domainoption'] ?? 'none');

            if ($useDom === 'register' && $sld !== '' && $tld !== '') {
                if ($tld[0] !== '.') $tld = '.' . $tld;
                $orderParams['domain']     = [$sld . $tld];
                $orderParams['domaintype'] = ['register'];
                $orderParams['regperiod']  = [1];
                if (str_ends_with($tld, '.cm')) {
                    $orderParams['nameserver1'] = CM_NS1;
                    $orderParams['nameserver2'] = CM_NS2;
                }
            } elseif ($useDom === 'existing' && $sld !== '' && $tld !== '') {
                if ($tld[0] !== '.') $tld = '.' . $tld;
                $orderParams['domain']     = [$sld . $tld];
                $orderParams['domaintype'] = ['owndomain'];
            }

            try {
                $r = whmcs_api('AddOrder', $orderParams);
                if (($r['result'] ?? '') !== 'success') {
                    $errors[] = (string)($r['message'] ?? 'Order failed.');
                } else {
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
    t('Hosting Plans', "Plans d'Hébergement"),
    $selected
        ? htmlspecialchars((string)$selected['name'])
        : t('Powerful <span style="color:var(--theme2,#ffa31a)">hosting</span> plans',
            'Plans d\'<span style="color:var(--theme2,#ffa31a)">hébergement</span> puissants'),
    $selected
        ? t('Configure your plan and complete checkout in minutes.',
            'Configurez votre plan et passez commande en quelques minutes.')
        : t('Fast SSD storage, free SSL, 24/7 support — built for African businesses.',
            'Stockage SSD rapide, SSL gratuit, support 24/7 — conçu pour les entreprises africaines.')
);
?>

<?php if ($errors): ?>
    <div class="cd-alert cd-alert-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
    </div>
<?php endif ?>

<?php if (!$selected): ?>
    <div class="cd-card">
        <div class="cd-section-title">
            <h2><?= t('Choose a hosting plan', "Choisissez un plan d'hébergement") ?></h2>
            <p><?= t('Every plan includes free DNS management, automatic backups, and a 30-day money-back guarantee.',
                    'Chaque plan inclut la gestion DNS gratuite, des sauvegardes automatiques et une garantie de remboursement de 30 jours.') ?></p>
        </div>
        <?php if (!$products): ?>
            <p class="cd-muted" style="text-align:center"><?= t('No products are available right now. Please try again later.', 'Aucun produit disponible pour le moment.') ?></p>
        <?php else: ?>
            <div class="cd-product-grid">
                <?php foreach ($products as $i => $p):
                    $price = $p['pricing'][array_key_first($p['pricing'] ?? [])] ?? null;
                    $monthly = is_array($price) ? ($price['monthly'] ?? '') : '';
                    $featured = ($i === (int)floor(count($products) / 2));
                    ?>
                    <div class="cd-product-card<?= $featured ? ' cd-product-featured' : '' ?>">
                        <?php if ($featured): ?>
                            <div style="position:absolute;top:14px;right:14px"><span class="cd-pill cd-pill-warn"><?= t('Popular','Populaire') ?></span></div>
                        <?php endif ?>
                        <h3><?= htmlspecialchars((string)$p['name']) ?></h3>
                        <div class="cd-product-desc"><?= nl2br(htmlspecialchars((string)($p['description'] ?? ''))) ?></div>
                        <?php if ($monthly !== ''): ?>
                            <div class="cd-product-price">
                                <?= htmlspecialchars($monthly) ?><small>/<?= t('month','mois') ?></small>
                            </div>
                        <?php endif ?>
                        <a class="cd-btn" href="?gid=<?= $gid ?>&pid=<?= (int)$p['pid'] ?>">
                            <?= t('Select plan', 'Choisir ce plan') ?> <i class="fa fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
<?php else: ?>
    <div class="cd-card">
        <p class="cd-muted" style="margin-top:0"><?= nl2br(htmlspecialchars((string)($selected['description'] ?? ''))) ?></p>
        <div class="cd-divider"></div>

        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <input type="hidden" name="step"  value="order">
            <input type="hidden" name="gid"   value="<?= $gid ?>">
            <input type="hidden" name="pid"   value="<?= (int)$selected['pid'] ?>">

            <h3 style="margin-top:24px"><?= t('Billing cycle','Cycle de facturation') ?></h3>
            <select class="cd-input" name="billingcycle">
                <?php foreach (($selected['pricing'] ?? []) as $curCode => $prices):
                    foreach (['monthly','quarterly','semiannually','annually','biennially','triennially'] as $cy):
                        $v = $prices[$cy] ?? null;
                        if ($v !== null && (float)$v >= 0): ?>
                            <option value="<?= $cy ?>"><?= ucfirst($cy) ?> — <?= htmlspecialchars($curCode) ?> <?= htmlspecialchars((string)$v) ?></option>
                <?php       endif;
                    endforeach;
                endforeach ?>
            </select>

            <h3 style="margin-top:24px"><?= t('Domain','Domaine') ?></h3>
            <label><input type="radio" name="domainoption" value="none" checked> <?= t('I\'ll add a domain later','J\'ajouterai un domaine plus tard') ?></label><br>
            <label><input type="radio" name="domainoption" value="register"> <?= t('Register a new domain','Enregistrer un nouveau domaine') ?></label><br>
            <label><input type="radio" name="domainoption" value="existing"> <?= t('Use a domain I already own','Utiliser un domaine que je possède déjà') ?></label>

            <div class="cd-row" style="margin-top:10px">
                <div style="flex:2"><input class="cd-input" type="text" name="sld" placeholder="<?= t('domain name','nom de domaine') ?>"></div>
                <div style="flex:1">
                    <select class="cd-input" name="tld">
                        <?php foreach (['.cm','.com','.net','.org','.africa','.io'] as $tt): ?>
                            <option value="<?= $tt ?>"><?= $tt ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <?php if (cd_client_id() === 0): ?>
                <h3 style="margin-top:24px"><?= t('Your details','Vos coordonnées') ?></h3>
                <p style="font-size:14px;color:#6b7280">
                    <?= t('Already have an account?','Vous avez déjà un compte?') ?>
                    <a href="<?= SITE_URL ?>/login.php?next=<?= rawurlencode($_SERVER['REQUEST_URI']) ?>"><?= t('Log in','Connectez-vous') ?></a>
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
            <?php endif ?>

            <h3 style="margin-top:24px"><?= t('Payment','Paiement') ?></h3>
            <select class="cd-input" name="paymentmethod">
                <option value="banktransfer"><?= t('Bank transfer','Virement bancaire') ?></option>
                <option value="mtn_momo">MTN Mobile Money</option>
                <option value="orange_money">Orange Money</option>
                <option value="paypal">PayPal</option>
                <option value="stripe"><?= t('Credit card','Carte de crédit') ?></option>
            </select>

            <button class="cd-btn" type="submit" style="margin-top:20px">
                <?= t('Place order','Passer la commande') ?> →
            </button>
        </form>
    </div>
<?php endif ?>

<?php cd_render_foot(); ?>
