<?php
/**
 * CamDigit — Edit profile (personal details)
 * Upload to: <whmcs_root>/account-profile.php
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';
cd_require_login();

$clientId = cd_client_id();
$errors   = [];
$success  = false;
$client   = [];

try {
    $r = whmcs_api('GetClientsDetails', ['clientid' => $clientId]);
    if (($r['result'] ?? '') === 'success') $client = $r['client'] ?? [];
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('profile_update_' . $clientId, 10, 600)) {
        $errors[] = t('Too many updates. Please wait a few minutes.',
                      'Trop de modifications. Veuillez patienter quelques minutes.');
    } else {
        $update = [
            'clientid'     => $clientId,
            'firstname'    => cd_sanitize_text($_POST['firstname']   ?? '', 100),
            'lastname'     => cd_sanitize_text($_POST['lastname']    ?? '', 100),
            'companyname'  => cd_sanitize_text($_POST['companyname'] ?? '', 150),
            'email'        => filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '',
            'address1'     => cd_sanitize_text($_POST['address1']    ?? '', 200),
            'address2'     => cd_sanitize_text($_POST['address2']    ?? '', 200),
            'city'         => cd_sanitize_text($_POST['city']        ?? '', 100),
            'state'        => cd_sanitize_text($_POST['state']       ?? '', 100),
            'postcode'     => cd_sanitize_text($_POST['postcode']    ?? '', 20),
            'country'      => cd_sanitize_country($_POST['country']  ?? ''),
            'phonenumber'  => cd_sanitize_phone($_POST['phone']      ?? ''),
        ];
        foreach (['firstname','lastname','email','address1','city','country','phonenumber'] as $f) {
            if (empty($update[$f])) $errors[] = ucfirst($f) . ' ' . t('is required.','est requis.');
        }
        if (!$errors) {
            try {
                $r = whmcs_api('UpdateClient', $update);
                if (($r['result'] ?? '') === 'success') {
                    $success = true;
                    $client  = array_merge($client, $update);
                } else {
                    $errors[] = (string)($r['message'] ?? t('Update failed.','Échec de la mise à jour.'));
                }
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
    }
}

function cd_v(array $client, string $key, string $alt = ''): string
{
    return htmlspecialchars((string)($client[$key] ?? ($_POST[$alt ?: $key] ?? '')));
}

cd_render_head(
    t('Profile', 'Profil'),
    t('My <span style="color:var(--theme2,#ffa31a)">Profile</span>',
      'Mon <span style="color:var(--theme2,#ffa31a)">Profil</span>'),
    t('Keep your personal details up to date for invoices and domain contacts.',
      'Gardez vos coordonnées à jour pour vos factures et contacts de domaine.')
);
?>

<?php cd_account_layout_start('profile'); ?>

<div class="cd-card">
    <?php if ($success): ?>
        <div class="cd-alert cd-alert-ok"><?= t('Profile saved successfully.', 'Profil enregistré avec succès.') ?></div>
    <?php endif ?>
    <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>

    <form method="post" action="">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">

        <div class="cd-form-section"><i class="fa fa-user"></i><?= t('Personal','Personnel') ?></div>
        <div class="cd-row">
            <div><label class="cd-label"><?= t('First name','Prénom') ?></label>
                <input class="cd-input" type="text" name="firstname" required value="<?= cd_v($client,'firstname') ?>"></div>
            <div><label class="cd-label"><?= t('Last name','Nom') ?></label>
                <input class="cd-input" type="text" name="lastname" required value="<?= cd_v($client,'lastname') ?>"></div>
        </div>
        <div class="cd-row">
            <div><label class="cd-label">Email</label>
                <input class="cd-input" type="email" name="email" required value="<?= cd_v($client,'email') ?>"></div>
            <div><label class="cd-label"><?= t('Phone','Téléphone') ?></label>
                <input class="cd-input" type="tel" name="phone" required value="<?= cd_v($client,'phonenumber','phone') ?>"></div>
        </div>
        <div><label class="cd-label"><?= t('Company (optional)','Société (optionnel)') ?></label>
            <input class="cd-input" type="text" name="companyname" value="<?= cd_v($client,'companyname') ?>"></div>

        <div class="cd-form-section" style="margin-top:24px"><i class="fa fa-location-dot"></i><?= t('Address','Adresse') ?></div>
        <div><label class="cd-label"><?= t('Street','Rue') ?></label>
            <input class="cd-input" type="text" name="address1" required value="<?= cd_v($client,'address1') ?>" style="margin-bottom:12px"></div>
        <div><label class="cd-label"><?= t('Address line 2 (optional)','Complément (optionnel)') ?></label>
            <input class="cd-input" type="text" name="address2" value="<?= cd_v($client,'address2') ?>"></div>
        <div class="cd-row" style="margin-top:14px">
            <div><label class="cd-label"><?= t('City','Ville') ?></label>
                <input class="cd-input" type="text" name="city" required value="<?= cd_v($client,'city') ?>"></div>
            <div><label class="cd-label"><?= t('State/Region','Région') ?></label>
                <input class="cd-input" type="text" name="state" value="<?= cd_v($client,'state') ?>"></div>
            <div><label class="cd-label"><?= t('Postal code','Code postal') ?></label>
                <input class="cd-input" type="text" name="postcode" value="<?= cd_v($client,'postcode') ?>"></div>
        </div>
        <div>
            <label class="cd-label"><?= t('Country','Pays') ?></label>
            <select class="cd-input" name="country" required>
                <?php $cur = (string)($client['country'] ?? 'CM'); foreach (cd_countries() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $code === $cur ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <button class="cd-btn" type="submit" style="margin-top:24px">
            <i class="fa fa-floppy-disk"></i> <?= t('Save changes','Enregistrer les modifications') ?>
        </button>
    </form>
</div>

<?php cd_account_layout_end(); ?>
<?php cd_render_foot(); ?>
