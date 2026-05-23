<?php
/**
 * CamDigit — Change account password
 * Upload to: <whmcs_root>/account-password.php
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';
cd_require_login();

$clientId = cd_client_id();
$errors   = [];
$success  = false;
$email    = '';

try {
    $r = whmcs_api('GetClientsDetails', ['clientid' => $clientId]);
    if (($r['result'] ?? '') === 'success') $email = (string)($r['client']['email'] ?? '');
} catch (RuntimeException) { /* non-fatal */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('pwchange_' . $clientId, 6, 600)) {
        $errors[] = t('Too many attempts. Please wait a few minutes.',
                      'Trop de tentatives. Veuillez patienter quelques minutes.');
    } else {
        $current = (string)($_POST['current']  ?? '');
        $new     = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm']  ?? '');

        if (!$current || !$new) {
            $errors[] = t('Please fill all password fields.','Veuillez remplir tous les champs.');
        }
        if ($new !== $confirm) {
            $errors[] = t('New passwords do not match.','Les nouveaux mots de passe ne correspondent pas.');
        }
        if (strlen($new) < 8) {
            $errors[] = t('Password must be at least 8 characters.','Le mot de passe doit faire au moins 8 caractères.');
        }

        // verify current password by calling ValidateLogin
        if (!$errors && $email) {
            try {
                $r = whmcs_api('ValidateLogin', ['email' => $email, 'password2' => $current]);
                if (($r['result'] ?? '') !== 'success') {
                    $errors[] = t('Current password is incorrect.','Mot de passe actuel incorrect.');
                }
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }

        if (!$errors) {
            try {
                $r = whmcs_api('UpdateClient', ['clientid' => $clientId, 'password2' => $new]);
                if (($r['result'] ?? '') === 'success') {
                    $success = true;
                } else {
                    $errors[] = (string)($r['message'] ?? t('Update failed.','Échec de la mise à jour.'));
                }
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
    }
}

cd_render_head(
    t('Change password','Modifier le mot de passe'),
    t('Account <span style="color:var(--theme2,#ffa31a)">security</span>',
      'Sécurité du <span style="color:var(--theme2,#ffa31a)">compte</span>'),
    t('Keep your account safe — pick a strong, unique password.',
      'Protégez votre compte — choisissez un mot de passe fort et unique.')
);
?>

<?php cd_account_layout_start('password'); ?>

<div class="cd-card" style="max-width:560px">
    <?php if ($success): ?>
        <div class="cd-alert cd-alert-ok"><?= t('Password updated. Use the new one next time you log in.','Mot de passe mis à jour. Utilisez-le à la prochaine connexion.') ?></div>
    <?php endif ?>
    <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>

    <form method="post" action="">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">

        <label class="cd-label"><?= t('Current password','Mot de passe actuel') ?></label>
        <input class="cd-input" type="password" name="current" required autocomplete="current-password" style="margin-bottom:14px">

        <label class="cd-label"><?= t('New password','Nouveau mot de passe') ?></label>
        <div class="cd-pw-wrap">
            <input class="cd-input" type="password" id="newPw" name="password" minlength="8" required autocomplete="new-password">
            <button type="button" class="cd-eye-btn" id="newEye" aria-label="toggle"><i class="fa fa-eye"></i></button>
        </div>
        <div class="cd-pw-strength" aria-live="polite">
            <div class="cd-pw-bar" id="newBar"></div>
            <span class="cd-pw-label" id="newLbl"></span>
        </div>

        <label class="cd-label"><?= t('Confirm new password','Confirmez le mot de passe') ?></label>
        <input class="cd-input" type="password" name="confirm" minlength="8" required autocomplete="new-password">

        <button class="cd-btn" type="submit" style="margin-top:22px;width:100%">
            <i class="fa fa-key"></i> <?= t('Update password','Modifier le mot de passe') ?>
        </button>

        <p class="cd-muted" style="font-size:13px;margin-top:14px;text-align:center">
            <i class="fa fa-shield-halved" style="color:var(--theme,#236a25);margin-right:5px"></i>
            <?= t('Use 12+ characters with a mix of letters, numbers, and symbols.',
                  '12 caractères ou plus avec lettres, chiffres et symboles.') ?>
        </p>
    </form>
</div>

<?php cd_account_layout_end(); ?>

<script>
(function(){
    const pw = document.getElementById('newPw');
    const bar = document.getElementById('newBar');
    const lbl = document.getElementById('newLbl');
    const eye = document.getElementById('newEye');
    if (!pw) return;
    pw.addEventListener('input', function(){
        let s=0,v=this.value;
        if (v.length>=8) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/[0-9]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        bar.className='cd-pw-bar';
        if (!v){lbl.textContent='';return;}
        if (s<=1){bar.classList.add('weak');lbl.textContent='Weak';}
        else if (s<=2){bar.classList.add('fair');lbl.textContent='Fair';}
        else{bar.classList.add('strong');lbl.textContent='Strong';}
    });
    eye.addEventListener('click', function(){
        const t=pw.type==='text';
        pw.type=t?'password':'text';
        this.querySelector('i').className=t?'fa fa-eye':'fa fa-eye-slash';
    });
})();
</script>

<?php cd_render_foot(); ?>
