<?php
/**
 * CamDigit — Password reset request
 * Upload to: <whmcs_root>/forgot-password.php
 *
 * Triggers WHMCS' built-in password reset email via SendEmail (Password Reset
 * Validation template). Always responds the same regardless of whether the
 * email is on file, to avoid account enumeration.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

if (cd_client_id() > 0) {
    header('Location: ' . SITE_URL . '/account.php');
    exit;
}

$errors = [];
$sent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('reset_' . md5(cd_client_ip()), 5, 900)) {
        $errors[] = t('Too many attempts. Please wait a few minutes.',
                      'Trop de tentatives. Patientez quelques minutes.');
    } else {
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
        if (!$email) {
            $errors[] = t('Please enter a valid email.', 'Veuillez entrer un email valide.');
        } else {
            // Look up client by email, then trigger the password-reset email if it exists.
            // Always set $sent=true to avoid leaking which addresses are registered.
            try {
                $r = whmcs_api('GetClientsDetails', ['email' => $email]);
                if (($r['result'] ?? '') === 'success') {
                    @whmcs_api('SendEmail', [
                        'messagename' => 'Password Reset Validation',
                        'id'          => (int)($r['client']['id'] ?? 0),
                        'customtype'  => 'general',
                    ]);
                }
            } catch (RuntimeException) { /* silent */ }
            $sent = true;
        }
    }
}

cd_render_head(
    t('Reset password','Réinitialiser le mot de passe'),
    t('Forgot your <span style="color:var(--theme2,#ffa31a)">password</span>?',
      'Mot de passe <span style="color:var(--theme2,#ffa31a)">oublié</span> ?'),
    t('Enter your email and we\'ll send you a reset link.',
      'Entrez votre email et nous vous enverrons un lien de réinitialisation.')
);
?>

<div class="cd-card" style="max-width:480px;margin:0 auto">
    <?php if ($sent): ?>
    <div class="cd-success">
        <div class="cd-success-icon"><i class="fa fa-envelope-circle-check"></i></div>
        <h2><?= t('Check your inbox','Vérifiez votre boîte mail') ?></h2>
        <p><?= t('If an account exists with that email, you\'ll receive a reset link within a few minutes.',
                 'Si un compte existe avec cet email, vous recevrez un lien sous quelques minutes.') ?></p>
        <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/login.php">
            <i class="fa fa-arrow-left"></i> <?= t('Back to login','Retour à la connexion') ?>
        </a>
    </div>
    <?php else: ?>
    <?php if ($errors): ?>
    <div class="cd-alert cd-alert-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
    </div>
    <?php endif ?>
    <form method="post" action="">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
        <label class="cd-label">Email</label>
        <input class="cd-input" type="email" name="email" required autocomplete="email"
               placeholder="you@example.com"
               value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">
        <button class="cd-btn" type="submit" style="margin-top:18px;width:100%">
            <i class="fa fa-paper-plane"></i> <?= t('Send reset link','Envoyer le lien') ?>
        </button>
        <p style="text-align:center;margin-top:18px;font-size:14px;color:#6b7785">
            <a href="<?= SITE_URL ?>/login.php" style="font-weight:600"><?= t('Back to login','Retour à la connexion') ?></a>
            &nbsp;·&nbsp;
            <a href="<?= SITE_URL ?>/register.php" style="font-weight:600"><?= t('Create account','Créer un compte') ?></a>
        </p>
    </form>
    <?php endif ?>
</div>

<?php cd_render_foot(); ?>
