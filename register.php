<?php
/**
 * CamDigit Account Registration (custom front-end)
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/register.php
 *
 * Creates a WHMCS client via AddClient API, sets the session, then redirects
 * to ?next= or /account.php.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '/account.php');
if (!preg_match('#^/[A-Za-z0-9_\-./?=&%]*$#', $next)) $next = '/account.php';

if (cd_client_id() > 0) {
    header('Location: ' . $next);
    exit;
}

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('register_' . md5(cd_client_ip()), 5, 600)) {
        $errors[] = 'Too many attempts. Please wait a few minutes.';
    } else {
        $values = [
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
            if (empty($values[$f])) $errors[] = ucfirst($f) . ' is required.';
        }
        if (strlen($values['password2']) < 8) $errors[] = 'Password must be at least 8 characters.';

        if (!$errors) {
            try {
                $r = whmcs_api('AddClient', $values);
                if (($r['result'] ?? '') !== 'success') {
                    $msg = (string)($r['message'] ?? 'Registration failed.');
                    if (stripos($msg, 'duplicate') !== false || stripos($msg, 'already') !== false) {
                        $errors[] = t('An account with this email already exists. Please log in.',
                                       'Un compte avec cet email existe déjà. Veuillez vous connecter.');
                    } else $errors[] = $msg;
                } else {
                    $_SESSION['uid']         = (int)$r['clientid'];
                    $_SESSION['login_email'] = $values['email'];
                    header('Location: ' . $next);
                    exit;
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

cd_render_head(
    t('Create your account', 'Créer votre compte'),
    t('Get <span style="color:var(--theme2,#ffa31a)">started</span> in minutes',
      'Démarrez en <span style="color:var(--theme2,#ffa31a)">quelques minutes</span>'),
    t('Create your CamDigit account to register domains, buy hosting, and manage everything in one place.',
      'Créez votre compte CamDigit pour enregistrer des domaines, acheter de l\'hébergement et tout gérer en un seul endroit.')
);
?>

<div class="cd-auth-wrap" style="max-width:960px;margin:0 auto">
    <!-- Left: branding panel -->
    <div class="cd-auth-side">
        <h2><?= t('Join CamDigit today', 'Rejoignez CamDigit aujourd\'hui') ?></h2>
        <p><?= t('Everything you need to build a digital presence in Cameroon — domains, hosting, and more.',
            'Tout ce qu\'il vous faut pour une présence numérique au Cameroun — domaines, hébergement et plus.') ?></p>
        <div class="cd-auth-feature">
            <i class="fa fa-globe-africa"></i>
            <span><?= t('Register .cm, .com, .africa domains', 'Enregistrez des domaines .cm, .com, .africa') ?></span>
        </div>
        <div class="cd-auth-feature">
            <i class="fa fa-server"></i>
            <span><?= t('Managed cPanel hosting — fast SSD', 'Hébergement cPanel géré — SSD rapide') ?></span>
        </div>
        <div class="cd-auth-feature">
            <i class="fa fa-mobile-alt"></i>
            <span>MTN &amp; Orange Money <?= t('accepted', 'acceptés') ?></span>
        </div>
        <div class="cd-auth-feature">
            <i class="fa fa-headset"></i>
            <span><?= t('24/7 support in French &amp; English', 'Support 24h/24 en français et anglais') ?></span>
        </div>
    </div>
    <!-- Right: form panel -->
    <div class="cd-auth-form">
        <?php if ($errors): ?>
            <div class="cd-alert cd-alert-error" style="margin-bottom:18px">
                <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
            </div>
        <?php endif ?>

        <h3 style="font-size:20px;font-weight:700;color:#0f0d1d;margin-bottom:18px">
            <?= t('Create your free account', 'Créez votre compte gratuit') ?>
        </h3>

        <form method="post" action="" id="regForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <input type="hidden" name="next"  value="<?= htmlspecialchars($next) ?>">

            <div class="cd-row">
                <div><label class="cd-label"><?= t('First name','Prénom') ?></label>
                    <input class="cd-input" type="text" name="firstname" required autocomplete="given-name"
                           value="<?= htmlspecialchars((string)($values['firstname'] ?? '')) ?>"></div>
                <div><label class="cd-label"><?= t('Last name','Nom') ?></label>
                    <input class="cd-input" type="text" name="lastname" required autocomplete="family-name"
                           value="<?= htmlspecialchars((string)($values['lastname'] ?? '')) ?>"></div>
            </div>
            <div class="cd-row">
                <div><label class="cd-label">Email</label>
                    <input class="cd-input" type="email" name="email" required autocomplete="email"
                           value="<?= htmlspecialchars((string)($values['email'] ?? '')) ?>"></div>
                <div><label class="cd-label"><?= t('Phone','Téléphone') ?></label>
                    <input class="cd-input" type="tel" name="phone" required autocomplete="tel"
                           placeholder="+237 6XX XX XX XX"
                           value="<?= htmlspecialchars((string)($values['phonenumber'] ?? '')) ?>"></div>
            </div>
            <label class="cd-label"><?= t('Address','Adresse') ?></label>
            <input class="cd-input" type="text" name="address1" required autocomplete="street-address"
                   style="margin-bottom:14px"
                   value="<?= htmlspecialchars((string)($values['address1'] ?? '')) ?>">
            <div class="cd-row">
                <div><label class="cd-label"><?= t('City','Ville') ?></label>
                    <input class="cd-input" type="text" name="city" required autocomplete="address-level2"
                           value="<?= htmlspecialchars((string)($values['city'] ?? '')) ?>"></div>
                <div><label class="cd-label"><?= t('State/Region','Région') ?></label>
                    <input class="cd-input" type="text" name="state" autocomplete="address-level1"
                           value="<?= htmlspecialchars((string)($values['state'] ?? '')) ?>"></div>
            </div>
            <div class="cd-row">
                <div><label class="cd-label"><?= t('Country','Pays') ?></label>
                    <select class="cd-input" name="country" required autocomplete="country">
                        <?php foreach (cd_countries() as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $code === ($values['country'] ?? 'CM') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach ?>
                    </select></div>
                <div><label class="cd-label"><?= t('Postal code','Code postal') ?></label>
                    <input class="cd-input" type="text" name="postcode" autocomplete="postal-code"
                           value="<?= htmlspecialchars((string)($values['postcode'] ?? '')) ?>"></div>
            </div>

            <div class="cd-divider"></div>
            <label class="cd-label"><?= t('Password','Mot de passe') ?> <span style="color:#dc2626">*</span></label>
            <div class="cd-pw-wrap">
                <input class="cd-input" type="password" id="regPw" name="password" minlength="8" required
                    autocomplete="new-password">
                <button type="button" class="cd-eye-btn" id="regEyeBtn" aria-label="Toggle password">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
            <div class="cd-pw-strength" aria-live="polite">
                <div class="cd-pw-bar" id="regPwBar"></div>
                <span class="cd-pw-label" id="regPwLabel"></span>
            </div>

            <button class="cd-btn" type="submit" style="width:100%">
                <i class="fa fa-user-plus"></i> <?= t('Create account','Créer le compte') ?>
            </button>
        </form>

        <p style="margin-top:18px;font-size:14px;color:#6b7280;text-align:center">
            <?= t('Already have an account?','Vous avez déjà un compte ?') ?>
            <a href="<?= SITE_URL ?>/login.php?next=<?= rawurlencode($next) ?>" style="font-weight:600">
                <?= t('Log in','Se connecter') ?>
            </a>
        </p>
    </div>
</div>

<script>
(function() {
    const pw = document.getElementById('regPw');
    const bar = document.getElementById('regPwBar');
    const lbl = document.getElementById('regPwLabel');
    const eye = document.getElementById('regEyeBtn');
    if (!pw) return;
    pw.addEventListener('input', function() {
        let s = 0, v = this.value;
        if (v.length >= 8) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/[0-9]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        bar.className = 'cd-pw-bar';
        if (!v) { lbl.textContent = ''; return; }
        if (s <= 1) { bar.classList.add('weak');   lbl.textContent = 'Weak'; }
        else if (s <= 2) { bar.classList.add('fair'); lbl.textContent = 'Fair'; }
        else { bar.classList.add('strong'); lbl.textContent = 'Strong'; }
    });
    eye.addEventListener('click', function() {
        const t = pw.type === 'text';
        pw.type = t ? 'password' : 'text';
        this.querySelector('i').className = t ? 'fa fa-eye' : 'fa fa-eye-slash';
    });
})();
</script>

<?php cd_render_foot(); ?>
