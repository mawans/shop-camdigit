<?php
/**
 * CamDigit Main — Login
 * Validates credentials via WHMCS ValidateLogin API, sets $_SESSION['uid'].
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/layout.php';

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '/account.php');
if (!preg_match('#^/[A-Za-z0-9_\-./?=&%]*$#', $next)) $next = '/account.php';

if (cd_client_id() > 0) { header('Location: ' . $next); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('login_' . md5(cd_client_ip()), 8, 600)) {
        $errors[] = t('Too many attempts. Please wait a few minutes.', 'Trop de tentatives. Veuillez patienter quelques minutes.');
    } else {
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
        $pass  = (string)($_POST['password'] ?? '');
        if (!$email || !$pass) {
            $errors[] = t('Please enter your email and password.', 'Veuillez entrer votre email et mot de passe.');
        } else {
            try {
                $r = whmcs_api('ValidateLogin', ['email' => $email, 'password2' => $pass]);
                if (($r['result'] ?? '') === 'success' && !empty($r['userid'])) {
                    $_SESSION['uid']         = (int)$r['userid'];
                    $_SESSION['login_email'] = $email;
                    header('Location: ' . $next); exit;
                }
                $errors[] = t('Invalid email or password.', 'Email ou mot de passe incorrect.');
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
    }
}

cdm_head([
    'title'         => t('Log in','Connexion'),
    'hero_title'    => t('Welcome <span class="accent">back</span>', 'Bon <span class="accent">retour</span>'),
    'hero_subtitle' => t('Log in to manage your services, domains and invoices.',
                         'Connectez-vous pour gérer vos services, domaines et factures.'),
    'breadcrumb'    => t('Log in','Connexion'),
]);
?>

<div class="cdm-auth cdm-fade-up" style="margin-top:-40px;position:relative;z-index:5">
    <!-- Brand side -->
    <div class="cdm-auth-side">
        <h2><?= t('Your digital home in Cameroon', 'Votre espace numérique au Cameroun') ?></h2>
        <p><?= t('Manage all your services, domains and invoices from one secure dashboard.',
                 'Gérez tous vos services, domaines et factures depuis un tableau de bord sécurisé.') ?></p>
        <ul>
            <li><i class="fa-solid fa-check"></i><span><?= t('Domains from 5,000 XAF / yr', 'Domaines dès 5 000 XAF/an') ?></span></li>
            <li><i class="fa-solid fa-check"></i><span><?= t('Managed hosting — 99.9% uptime', 'Hébergement géré — 99,9% de disponibilité') ?></span></li>
            <li><i class="fa-solid fa-check"></i><span><?= t('24/7 bilingual support', 'Support bilingue 24h/24') ?></span></li>
            <li><i class="fa-solid fa-check"></i><span><?= t('SSL & daily backups included', 'SSL et sauvegardes quotidiennes inclus') ?></span></li>
        </ul>
    </div>

    <!-- Form side -->
    <div class="cdm-auth-form">
        <h1><?= t('Sign in','Connexion') ?></h1>
        <p><?= t('Welcome back — enter your details to continue.', 'Heureux de vous revoir — entrez vos identifiants.') ?></p>

        <?php if ($errors): ?>
            <div class="cdm-alert cdm-alert-err">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?></div>
            </div>
        <?php endif ?>

        <form method="post" action="" novalidate>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <input type="hidden" name="next"  value="<?= htmlspecialchars($next) ?>">

            <div class="cdm-field">
                <label class="cdm-label" for="loginEmail">Email</label>
                <div class="cdm-input-group">
                    <span class="cdm-input-prefix"><i class="fa-solid fa-envelope"></i></span>
                    <input id="loginEmail" class="cdm-input" type="email" name="email" autocomplete="email" required
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">
                </div>
            </div>

            <div class="cdm-field">
                <label class="cdm-label" for="loginPwd"><?= t('Password','Mot de passe') ?></label>
                <div class="cdm-input-group">
                    <span class="cdm-input-prefix"><i class="fa-solid fa-lock"></i></span>
                    <input id="loginPwd" class="cdm-input" type="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="cdm-input-suffix" data-cdm-pw-toggle="loginPwd" aria-label="<?= t('Show password','Afficher') ?>">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="cdm-flex-between cdm-mb-2" style="font-size:13px">
                <label class="cdm-checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span><?= t('Keep me signed in','Rester connecté') ?></span>
                </label>
                <a href="<?= SITE_URL ?>/forgot-password.php" style="color:var(--theme2);font-weight:600">
                    <?= t('Forgot password?','Mot de passe oublié ?') ?>
                </a>
            </div>

            <button class="cdm-btn cdm-btn-block cdm-btn-lg" type="submit">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                <?= t('Log in','Se connecter') ?>
            </button>
        </form>

        <p class="cdm-text-center cdm-muted cdm-mt-3" style="font-size:14px">
            <?= t('No account yet?','Pas encore de compte ?') ?>
            <a href="<?= SITE_URL ?>/register.php?next=<?= rawurlencode($next) ?>" style="font-weight:600">
                <?= t('Create one free','Créer un compte gratuit') ?>
            </a>
        </p>
    </div>
</div>

<?php cdm_foot(); ?>
