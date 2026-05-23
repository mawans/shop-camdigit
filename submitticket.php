<?php
/**
 * CamDigit — Open a support ticket
 * Upload to: <whmcs_root>/submitticket.php
 *
 * Logged-in clients get a richer form with department picker and service tag.
 * Falls back to a guest-friendly version (still opens a WHMCS ticket).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';

$loggedIn = cd_client_id() > 0;
$errors   = [];
$success  = null;

// Pull departments (best-effort)
$departments = [];
try {
    $r = whmcs_api('GetSupportDepartments', []);
    $departments = $r['departments']['department'] ?? [];
} catch (RuntimeException) { /* non-fatal */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('ticket_' . ($loggedIn ? cd_client_id() : md5(cd_client_ip())), 6, 600)) {
        $errors[] = t('Too many tickets. Please wait a few minutes.',
                      'Trop de tickets. Veuillez patienter quelques minutes.');
    } else {
        $deptid   = max(1, (int)($_POST['deptid'] ?? 1));
        $subject  = cd_sanitize_text($_POST['subject'] ?? '', 200);
        $message  = trim((string)($_POST['message'] ?? ''));
        $message  = substr(strip_tags($message), 0, 8000);
        $priority = in_array($_POST['priority'] ?? '', ['Low','Medium','High'], true)
                  ? $_POST['priority'] : 'Medium';
        $serviceid = (int)($_POST['serviceid'] ?? 0);

        if (!$subject || !$message) {
            $errors[] = t('Subject and message are required.','Le sujet et le message sont obligatoires.');
        } else {
            $params = [
                'deptid'   => $deptid,
                'subject'  => $subject,
                'message'  => $message,
                'priority' => $priority,
            ];
            if ($loggedIn) {
                $params['clientid'] = cd_client_id();
                if ($serviceid > 0) $params['serviceid'] = $serviceid;
            } else {
                $name  = cd_sanitize_text($_POST['name']  ?? '', 100);
                $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
                if (!$name || !$email) {
                    $errors[] = t('Name and email are required.','Nom et email obligatoires.');
                } else {
                    $params['name'] = $name; $params['email'] = $email;
                }
            }
            if (!$errors) {
                try {
                    $r = whmcs_api('OpenTicket', $params);
                    if (($r['result'] ?? '') === 'success') {
                        $success = (string)($r['tid'] ?? '');
                    } else {
                        $errors[] = (string)($r['message'] ?? t('Ticket failed.','Échec.'));
                    }
                } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
            }
        }
    }
}

// Services list (for service tag dropdown)
$services = [];
if ($loggedIn) {
    try {
        $r = whmcs_api('GetClientsProducts', ['clientid' => cd_client_id(), 'limitnum' => 100]);
        $services = $r['products']['product'] ?? [];
    } catch (RuntimeException) { /* non-fatal */ }
}

cd_render_head(
    t('Open a ticket','Ouvrir un ticket'),
    t('Open a <span style="color:var(--theme2,#ffa31a)">ticket</span>',
      'Ouvrir un <span style="color:var(--theme2,#ffa31a)">ticket</span>'),
    t('Tell us what\'s going on — most tickets get a reply within a few hours.',
      'Décrivez votre problème — la plupart des tickets reçoivent une réponse en quelques heures.')
);

if ($loggedIn) cd_account_layout_start('tickets');
?>

<?php if ($success): ?>
<div class="cd-card" style="max-width:600px;margin:0 auto">
    <div class="cd-success">
        <div class="cd-success-icon"><i class="fa fa-circle-check"></i></div>
        <h2><?= t('Ticket opened','Ticket ouvert') ?></h2>
        <p><?= t('Reference','Référence') ?> <strong>#<?= htmlspecialchars($success) ?></strong>
            — <?= t('we\'ll be in touch shortly.','nous reviendrons vers vous rapidement.') ?></p>
        <div class="cd-success-actions">
            <?php if ($loggedIn): ?>
            <a class="cd-btn" href="<?= SITE_URL ?>/account-tickets.php"><i class="fa fa-list"></i> <?= t('My tickets','Mes tickets') ?></a>
            <?php endif ?>
            <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/"><i class="fa fa-house"></i> <?= t('Home','Accueil') ?></a>
        </div>
    </div>
</div>
<?php else: ?>

<div class="cd-card" style="max-width:760px;margin:0 auto">
    <?php if ($errors): ?>
    <div class="cd-alert cd-alert-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
    </div>
    <?php endif ?>

    <form method="post" action="">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">

        <?php if (!$loggedIn): ?>
        <div class="cd-logged-notice" style="background:#fffbeb;border-color:var(--theme2,#ffa31a);color:#7c5b00">
            <i class="fa fa-circle-info"></i>
            <?= t('You\'re not logged in. ','Vous n\'êtes pas connecté. ') ?>
            <a href="<?= SITE_URL ?>/login.php?next=<?= rawurlencode($_SERVER['REQUEST_URI']) ?>" style="font-weight:600;color:inherit;text-decoration:underline">
                <?= t('Log in','Se connecter') ?>
            </a>
            <?= t(' for better support.',' pour un meilleur suivi.') ?>
        </div>
        <div class="cd-row">
            <div><label class="cd-label"><?= t('Your name','Votre nom') ?></label>
                <input class="cd-input" type="text" name="name" required
                       value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>"></div>
            <div><label class="cd-label">Email</label>
                <input class="cd-input" type="email" name="email" required
                       value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>"></div>
        </div>
        <?php endif ?>

        <div class="cd-row">
            <div><label class="cd-label"><?= t('Department','Département') ?></label>
                <select class="cd-input" name="deptid">
                    <?php if ($departments): foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars((string)$d['name']) ?></option>
                    <?php endforeach; else: ?>
                    <option value="1"><?= t('General Support','Support général') ?></option>
                    <option value="2"><?= t('Billing','Facturation') ?></option>
                    <option value="3"><?= t('Sales','Ventes') ?></option>
                    <?php endif ?>
                </select></div>
            <div><label class="cd-label"><?= t('Priority','Priorité') ?></label>
                <select class="cd-input" name="priority">
                    <option value="Low"><?= t('Low','Basse') ?></option>
                    <option value="Medium" selected><?= t('Medium','Moyenne') ?></option>
                    <option value="High"><?= t('High','Haute') ?></option>
                </select></div>
        </div>

        <?php if ($services): ?>
        <label class="cd-label"><?= t('Related service (optional)','Service associé (optionnel)') ?></label>
        <select class="cd-input" name="serviceid" style="margin-bottom:14px">
            <option value="0">— <?= t('None','Aucun') ?> —</option>
            <?php foreach ($services as $sv): ?>
            <option value="<?= (int)$sv['id'] ?>">
                <?= htmlspecialchars((string)$sv['name']) ?>
                <?= !empty($sv['domain']) ? ' (' . htmlspecialchars((string)$sv['domain']) . ')' : '' ?>
            </option>
            <?php endforeach ?>
        </select>
        <?php endif ?>

        <label class="cd-label"><?= t('Subject','Sujet') ?></label>
        <input class="cd-input" type="text" name="subject" required
               value="<?= htmlspecialchars((string)($_POST['subject'] ?? '')) ?>"
               style="margin-bottom:14px">

        <label class="cd-label"><?= t('Message','Message') ?></label>
        <textarea class="cd-input" name="message" rows="8" required style="font-family:inherit;resize:vertical"><?= htmlspecialchars((string)($_POST['message'] ?? '')) ?></textarea>

        <button class="cd-btn" type="submit" style="margin-top:18px">
            <i class="fa fa-paper-plane"></i> <?= t('Submit ticket','Envoyer le ticket') ?>
        </button>
    </form>
</div>
<?php endif ?>

<?php if ($loggedIn) cd_account_layout_end(); ?>
<?php cd_render_foot(); ?>
