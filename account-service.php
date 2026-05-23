<?php
/**
 * CamDigit — Single service detail
 * Upload to: <whmcs_root>/account-service.php?id=NNN
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';
cd_require_login();

$clientId  = cd_client_id();
$serviceId = (int)($_GET['id'] ?? 0);
if ($serviceId <= 0) {
    header('Location: ' . SITE_URL . '/account-services.php');
    exit;
}

$errors  = [];
$success = '';
$service = null;

try {
    $r = whmcs_api('GetClientsProducts', ['clientid' => $clientId, 'serviceid' => $serviceId]);
    $list = $r['products']['product'] ?? [];
    $service = $list ? $list[0] : null;
    if (!$service || (int)$service['id'] !== $serviceId) {
        $errors[] = t('Service not found.','Service introuvable.');
        $service = null;
    }
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

if ($service && $_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'request_cancel') {
        $type   = (string)($_POST['cancel_type'] ?? 'End of Billing Period');
        $reason = cd_sanitize_text($_POST['reason'] ?? '', 500);
        try {
            $r = whmcs_api('AddCancelRequest', [
                'serviceid' => $serviceId,
                'type'      => $type === 'Immediate' ? 'Immediate' : 'End of Billing Period',
                'reason'    => $reason ?: 'Client requested cancellation',
            ]);
            if (($r['result'] ?? '') === 'success') {
                $success = t('Cancellation request submitted.','Demande d\'annulation envoyée.');
            } else {
                $errors[] = (string)($r['message'] ?? t('Cancellation failed.','Échec.'));
            }
        } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    }
}

cd_render_head(
    t('Service','Service'),
    $service
        ? '<span style="color:var(--theme2,#ffa31a)">' . htmlspecialchars((string)$service['name']) . '</span>'
        : t('Service','Service'),
    $service ? htmlspecialchars((string)($service['domain'] ?? '')) : null
);
?>

<?php cd_account_layout_start('services'); ?>

<?php if ($success): ?>
<div class="cd-alert cd-alert-ok"><?= htmlspecialchars($success) ?></div>
<?php endif ?>
<?php if ($errors): ?>
<div class="cd-alert cd-alert-error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
</div>
<?php endif ?>

<?php if ($service): $status = (string)$service['status']; $s = strtolower($status); ?>
<div class="cd-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
        <div>
            <h2 style="margin:0"><?= htmlspecialchars((string)$service['name']) ?></h2>
            <p class="cd-muted" style="margin:6px 0 0">
                <?php if (!empty($service['domain'])): ?>
                <i class="fa fa-globe"></i> <strong><?= htmlspecialchars((string)$service['domain']) ?></strong>
                <?php endif ?>
            </p>
        </div>
        <span class="cd-pill <?= in_array($s,['active','registered'],true)?'cd-pill-ok':(in_array($s,['pending','pending registration'],true)?'cd-pill-warn':'cd-pill-err') ?>"
              style="font-size:13px;padding:6px 14px"><?= htmlspecialchars($status) ?></span>
    </div>

    <div class="cd-divider"></div>

    <div class="cd-client-info">
        <div class="cd-client-info-row"><div class="cd-client-info-label"><?= t('Reg. date','Inscription') ?></div>
            <div><?= htmlspecialchars((string)($service['regdate'] ?? '—')) ?></div></div>
        <div class="cd-client-info-row"><div class="cd-client-info-label"><?= t('Next due','Échéance') ?></div>
            <div><?= htmlspecialchars((string)($service['nextduedate'] ?? '—')) ?></div></div>
        <div class="cd-client-info-row"><div class="cd-client-info-label"><?= t('Billing cycle','Cycle') ?></div>
            <div><?= htmlspecialchars((string)($service['billingcycle'] ?? '—')) ?></div></div>
        <div class="cd-client-info-row"><div class="cd-client-info-label"><?= t('Amount','Montant') ?></div>
            <div><strong style="color:var(--theme,#236a25)"><?= htmlspecialchars((string)($service['firstpaymentamount'] ?? $service['recurringamount'] ?? '—')) ?></strong></div></div>
        <?php if (!empty($service['username'])): ?>
        <div class="cd-client-info-row"><div class="cd-client-info-label">cPanel</div>
            <div><code style="background:#f3f7fb;padding:2px 8px;border-radius:4px"><?= htmlspecialchars((string)$service['username']) ?></code></div></div>
        <?php endif ?>
        <?php if (!empty($service['dedicatedip'])): ?>
        <div class="cd-client-info-row"><div class="cd-client-info-label">IP</div>
            <div><code><?= htmlspecialchars((string)$service['dedicatedip']) ?></code></div></div>
        <?php endif ?>
    </div>

    <?php if (!empty($service['serverhostname'])): ?>
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:18px">
        <a class="cd-btn" target="_blank" rel="noopener" href="https://<?= htmlspecialchars((string)$service['serverhostname']) ?>:2083">
            <i class="fa fa-arrow-up-right-from-square"></i> <?= t('Open cPanel','Ouvrir cPanel') ?>
        </a>
        <a class="cd-btn cd-btn-secondary" target="_blank" rel="noopener" href="https://<?= htmlspecialchars((string)$service['serverhostname']) ?>:2096">
            <i class="fa fa-envelope"></i> Webmail
        </a>
    </div>
    <?php endif ?>
</div>

<?php if (!in_array($s, ['cancelled','terminated','expired'], true)): ?>
<div class="cd-card" style="border:1px solid #fee2e2">
    <div class="cd-form-section" style="margin-top:0;color:#dc2626"><i class="fa fa-triangle-exclamation"></i><?= t('Cancel this service','Annuler ce service') ?></div>
    <p class="cd-muted" style="margin-top:-4px">
        <?= t('Submit a cancellation request. We\'ll review it within one business day.',
              'Envoyez une demande d\'annulation. Nous y répondrons sous un jour ouvrable.') ?>
    </p>
    <form method="post" action="" onsubmit="return confirm('<?= t('Are you sure?','Êtes-vous sûr ?') ?>')">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
        <input type="hidden" name="action" value="request_cancel">
        <div class="cd-row">
            <div><label class="cd-label"><?= t('Type','Type') ?></label>
                <select class="cd-input" name="cancel_type">
                    <option value="End of Billing Period"><?= t('End of billing period','En fin de période') ?></option>
                    <option value="Immediate"><?= t('Immediate','Immédiat') ?></option>
                </select></div>
            <div style="flex:2"><label class="cd-label"><?= t('Reason (optional)','Motif (optionnel)') ?></label>
                <input class="cd-input" type="text" name="reason" placeholder="<?= t('Tell us why','Dites-nous pourquoi') ?>"></div>
        </div>
        <button class="cd-btn" type="submit" style="margin-top:14px;background:#dc2626">
            <i class="fa fa-ban"></i> <?= t('Request cancellation','Demander l\'annulation') ?>
        </button>
    </form>
</div>
<?php endif ?>
<?php endif ?>

<?php cd_account_layout_end(); ?>
<?php cd_render_foot(); ?>
