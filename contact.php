<?php
/**
 * CamDigit — Contact page
 * Upload to: <whmcs_root>/contact.php
 *
 * Public contact form that posts straight into WHMCS OpenTicket so it lands
 * in the support team's queue. Falls back to a guest ticket if not logged in.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('contact_' . md5(cd_client_ip()), 4, 600)) {
        $errors[] = t('Too many submissions. Please wait a few minutes.',
                      'Trop d\'envois. Veuillez patienter quelques minutes.');
    } else {
        $name    = cd_sanitize_text($_POST['name']    ?? '', 100);
        $email   = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
        $subject = cd_sanitize_text($_POST['subject'] ?? '', 200);
        $message = trim((string)($_POST['message'] ?? ''));
        $message = substr(strip_tags($message), 0, 5000);

        if (!$name || !$email || !$subject || !$message) {
            $errors[] = t('Please complete all fields.','Veuillez remplir tous les champs.');
        } else {
            try {
                $params = [
                    'deptid'  => 1,
                    'subject' => $subject,
                    'message' => "Sender: $name <$email>\n\n" . $message,
                    'name'    => $name,
                    'email'   => $email,
                    'priority'=> 'Medium',
                ];
                if (cd_client_id() > 0) {
                    $params['clientid'] = cd_client_id();
                    unset($params['name'], $params['email']);
                }
                $r = whmcs_api('OpenTicket', $params);
                if (($r['result'] ?? '') === 'success') $success = true;
                else $errors[] = (string)($r['message'] ?? t('Could not send message.','Échec de l\'envoi.'));
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
    }
}

cd_render_head(
    t('Contact','Contact'),
    t('Get in <span style="color:var(--theme2,#ffa31a)">touch</span>',
      '<span style="color:var(--theme2,#ffa31a)">Contactez</span>-nous'),
    t('Our Yaoundé team replies within a few hours, 7 days a week.',
      'Notre équipe à Yaoundé répond en quelques heures, 7 jours sur 7.')
);
?>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:24px;align-items:flex-start;margin-top:-50px;position:relative;z-index:5">

    <div>
        <div class="cd-card">
            <h2 style="margin-top:0"><?= t('Talk to us','Parlez-nous') ?></h2>
            <p class="cd-muted"><?= t('Pick whichever channel works best for you — we answer in English and French.',
                                       'Choisissez le canal qui vous convient — nous répondons en anglais et en français.') ?></p>

            <div class="cd-divider"></div>
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
                <div class="ficon" style="width:42px;height:42px;border-radius:10px;background:rgba(35,106,37,.1);color:var(--theme,#236a25);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa fa-phone"></i></div>
                <div><div class="cd-muted" style="font-size:12px"><?= t('Phone','Téléphone') ?></div>
                    <a href="tel:+237696770074" style="font-weight:600">(+237) 696 77 00 74</a></div>
            </div>
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
                <div class="ficon" style="width:42px;height:42px;border-radius:10px;background:rgba(255,163,26,.12);color:var(--theme2,#ffa31a);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa fa-envelope"></i></div>
                <div><div class="cd-muted" style="font-size:12px">Email</div>
                    <a href="mailto:contact@camdigit.com" style="font-weight:600">contact@camdigit.com</a></div>
            </div>
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
                <div class="ficon" style="width:42px;height:42px;border-radius:10px;background:rgba(35,106,37,.1);color:var(--theme,#236a25);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa fa-location-dot"></i></div>
                <div><div class="cd-muted" style="font-size:12px"><?= t('Office','Bureau') ?></div>
                    <strong>Yaoundé, Cameroun</strong></div>
            </div>
            <div style="display:flex;align-items:center;gap:14px">
                <div class="ficon" style="width:42px;height:42px;border-radius:10px;background:rgba(255,163,26,.12);color:var(--theme2,#ffa31a);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa fa-clock"></i></div>
                <div><div class="cd-muted" style="font-size:12px"><?= t('Hours','Horaires') ?></div>
                    <strong><?= t('24/7 online · 8h–18h on phone','24/7 en ligne · 8h–18h téléphone') ?></strong></div>
            </div>
        </div>

        <div class="cd-card">
            <h3 style="margin-top:0"><i class="fa fa-life-ring" style="color:var(--theme2,#ffa31a);margin-right:6px"></i><?= t('Need account help?','Besoin d\'aide compte ?') ?></h3>
            <p class="cd-muted"><?= t('Open a ticket from your dashboard so we can see your services.',
                                       'Ouvrez un ticket depuis votre tableau de bord pour un support contextuel.') ?></p>
            <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/submitticket.php">
                <i class="fa fa-ticket"></i> <?= t('Open a ticket','Ouvrir un ticket') ?>
            </a>
        </div>
    </div>

    <div class="cd-card">
        <?php if ($success): ?>
        <div class="cd-success">
            <div class="cd-success-icon"><i class="fa fa-circle-check"></i></div>
            <h2><?= t('Message sent','Message envoyé') ?></h2>
            <p><?= t('Thanks! We\'ll reply within a few hours.','Merci ! Nous répondrons en quelques heures.') ?></p>
            <a class="cd-btn" href="<?= SITE_URL ?>/"><i class="fa fa-house"></i> <?= t('Back home','Accueil') ?></a>
        </div>
        <?php else: ?>
        <h2 style="margin-top:0"><?= t('Send a message','Envoyez un message') ?></h2>
        <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
        <?php endif ?>
        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <div class="cd-row">
                <div><label class="cd-label"><?= t('Your name','Votre nom') ?></label>
                    <input class="cd-input" type="text" name="name" required
                           value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>"></div>
                <div><label class="cd-label">Email</label>
                    <input class="cd-input" type="email" name="email" required
                           value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>"></div>
            </div>
            <label class="cd-label"><?= t('Subject','Sujet') ?></label>
            <input class="cd-input" type="text" name="subject" required
                   value="<?= htmlspecialchars((string)($_POST['subject'] ?? '')) ?>"
                   style="margin-bottom:14px">

            <label class="cd-label"><?= t('Message','Message') ?></label>
            <textarea class="cd-input" name="message" rows="6" required
                      style="font-family:inherit;resize:vertical"><?= htmlspecialchars((string)($_POST['message'] ?? '')) ?></textarea>

            <button class="cd-btn" type="submit" style="margin-top:18px">
                <i class="fa fa-paper-plane"></i> <?= t('Send message','Envoyer le message') ?>
            </button>
        </form>
        <?php endif ?>
    </div>
</div>

<style>
@media(max-width:900px){div[style*="grid-template-columns:1fr 1.4fr"]{grid-template-columns:1fr!important}}
</style>

<?php cd_render_foot(); ?>
