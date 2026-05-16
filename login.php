<?php
/**
 * CamDigit Login (custom front-end)
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/login.php   (will shadow WHMCS's own clientarea.php
 *                                       login form once links are updated)
 *
 * Posts credentials server-side to WHMCS ValidateLogin. On success, sets
 * $_SESSION['uid'] so the rest of the custom pages see the user as logged in.
 * Then redirects to ?next= or /account.php.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

$next   = (string)($_GET['next'] ?? $_POST['next'] ?? '/account.php');
// Only allow same-origin relative paths
if (!preg_match('#^/[A-Za-z0-9_\-./?=&%]*$#', $next)) $next = '/account.php';

if (cd_client_id() > 0) {
    header('Location: ' . $next);
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('login_' . md5(cd_client_ip()), 8, 600)) {
        $errors[] = 'Too many login attempts. Please wait a few minutes.';
    } else {
        $email    = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
        $password = (string)($_POST['password'] ?? '');

        if (!$email || !$password) {
            $errors[] = t('Please enter your email and password.', 'Veuillez entrer votre email et mot de passe.');
        } else {
            try {
                $r = whmcs_api('ValidateLogin', [
                    'email'    => $email,
                    'password2'=> $password,
                ]);
                if (($r['result'] ?? '') === 'success' && !empty($r['userid'])) {
                    $_SESSION['uid']         = (int)$r['userid'];
                    $_SESSION['login_email'] = $email;
                    if (!empty($r['passwordhash'])) {
                        $_SESSION['adminid'] = $_SESSION['adminid'] ?? 0;
                    }
                    header('Location: ' . $next);
                    exit;
                }
                $errors[] = t('Invalid email or password.', 'Email ou mot de passe incorrect.');
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

cd_render_head(
    t('Log in', 'Connexion'),
    t('Welcome <span style="color:var(--theme2,#ffa31a)">back</span>',
      'Bon <span style="color:var(--theme2,#ffa31a)">retour</span>'),
    t('Log in to manage your services, domains and invoices.',
      'Connectez-vous pour gérer vos services, domaines et factures.')
);
?>

<div class="cd-auth-wrap" style="max-width:820px;margin:0 auto">
    <!-- Left: branding panel -->
    <div class="cd-auth-side">
        <h2><?= t('Your digital home in Cameroon', 'Votre espace numérique au Cameroun') ?></h2>
        <p><?= t('Manage all your services, domains and invoices from one secure dashboard.',
            'Gérez tous vos services, domaines et factures depuis un tableau de bord sécurisé.') ?></p>
        <div class="cd-auth-feature">
            <i class="fa fa-globe-africa"></i>
            <span><?= t('.CM domains from XAF 5&thinsp;000/yr', 'Domaines .CM dès 5&thinsp;000 XAF/an') ?></span>
        </div>
        <div class="cd-auth-feature">
            <i class="fa fa-server"></i>
            <span><?= t('Managed hosting — 99.9% uptime', 'Hébergement géré — 99,9% de disponibilité') ?></span>
        </div>
        <div class="cd-auth-feature">
            <i class="fa fa-headset"></i>
            <span><?= t('24/7 bilingual support', 'Support bilingue 24h/24') ?></span>
        </div>
        <div class="cd-auth-feature">
            <i class="fa fa-shield-alt"></i>
            <span><?= t('SSL &amp; daily backups included', 'SSL et sauvegardes quotidiennes inclus') ?></span>
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
            <?= t('Log in to your account', 'Connexion à votre compte') ?>
        </h3>

        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <input type="hidden" name="next"  value="<?= htmlspecialchars($next) ?>">

            <label class="cd-label">Email</label>
            <input class="cd-input" type="email" name="email" autocomplete="email" required
                   value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">

            <label class="cd-label" style="margin-top:14px"><?= t('Password', 'Mot de passe') ?></label>
            <input class="cd-input" type="password" name="password" autocomplete="current-password" required>

            <div style="text-align:right;margin-top:6px;margin-bottom:4px;font-size:13px">
                <a href="<?= SITE_URL ?>/index.php?rp=/password/reset/begin" style="color:var(--theme2,#ffa31a)">
                    <?= t('Forgot password?', 'Mot de passe oublié ?') ?>
                </a>
            </div>

            <button class="cd-btn" type="submit" style="margin-top:14px;width:100%">
                <i class="fa fa-sign-in-alt"></i> <?= t('Log in', 'Se connecter') ?>
            </button>
        </form>

        <p style="margin-top:20px;font-size:14px;color:#6b7280;text-align:center">
            <?= t('No account yet?', 'Pas encore de compte ?') ?>
            <a href="<?= SITE_URL ?>/register.php?next=<?= rawurlencode($next) ?>" style="font-weight:600">
                <?= t('Create one free', 'Créer un compte gratuit') ?>
            </a>
        </p>
    </div>
</div>

<?php cd_render_foot(); ?>
