<?php
/**
 * CamDigit — Single domain manager
 * Upload to: <whmcs_root>/account-domain.php?id=NNN
 *
 * Lets a logged-in client update nameservers, registrar lock, and auto-renew
 * for a single domain. Pulls DNS-management modes for .cm where applicable.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';
cd_require_login();

$clientId = cd_client_id();
$domainId = (int)($_GET['id'] ?? 0);
if ($domainId <= 0) {
    header('Location: ' . SITE_URL . '/account-domains.php');
    exit;
}

$errors  = [];
$success = '';
$domain  = null;
$ns      = ['', '', '', '', ''];

// ── Load domain + verify ownership ───────────────────────────────────────────
try {
    $r = whmcs_api('GetClientsDomains', ['clientid' => $clientId, 'domainid' => $domainId]);
    $list = $r['domains']['domain'] ?? [];
    $domain = $list ? $list[0] : null;
    if (!$domain || (int)$domain['id'] !== $domainId) {
        $errors[] = t('Domain not found.', 'Domaine introuvable.');
        $domain = null;
    }
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

// ── Load current nameservers ────────────────────────────────────────────────
if ($domain) {
    try {
        $r = whmcs_api('DomainGetNameservers', ['domainid' => $domainId]);
        if (($r['result'] ?? '') === 'success') {
            foreach (['ns1','ns2','ns3','ns4','ns5'] as $i => $k) $ns[$i] = (string)($r[$k] ?? '');
        }
    } catch (RuntimeException) { /* non-fatal */ }
}

// ── POST actions ────────────────────────────────────────────────────────────
if ($domain && $_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'update_ns') {
        $params = ['domainid' => $domainId];
        $any = false;
        foreach (['ns1','ns2','ns3','ns4','ns5'] as $i => $k) {
            $val = strtolower(trim((string)($_POST[$k] ?? '')));
            $val = preg_replace('/[^a-z0-9\-.]/', '', (string)$val) ?? '';
            $params[$k] = $val;
            if ($val) $any = true;
            $ns[$i] = $val;
        }
        if (!$any) {
            $errors[] = t('At least one nameserver is required.', 'Au moins un nameserver est requis.');
        } else {
            try {
                $r = whmcs_api('DomainUpdateNameservers', $params);
                if (($r['result'] ?? '') === 'success') {
                    $success = t('Nameservers updated successfully.', 'Nameservers mis à jour.');
                } else {
                    $errors[] = (string)($r['message'] ?? t('Update failed.','Échec.'));
                }
            } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
    } elseif ($act === 'toggle_lock') {
        $lock = !empty($_POST['lock']) ? 'enable' : 'disable';
        try {
            $r = whmcs_api('DomainUpdateLockingStatus', ['domainid' => $domainId, 'lockstatus' => $lock]);
            if (($r['result'] ?? '') === 'success') {
                $success = t('Registrar lock updated.', 'Verrouillage registrar mis à jour.');
                $domain['idprotection'] = ($lock === 'enable');
            } else {
                $errors[] = (string)($r['message'] ?? t('Update failed.','Échec.'));
            }
        } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    } elseif ($act === 'toggle_autorenew') {
        try {
            $r = whmcs_api('UpdateClientDomain', [
                'domainid'   => $domainId,
                'donotrenew' => empty($_POST['autorenew']) ? '1' : '0',
            ]);
            if (($r['result'] ?? '') === 'success') {
                $success = t('Auto-renew updated.', 'Renouvellement automatique mis à jour.');
                $domain['donotrenew'] = empty($_POST['autorenew']);
            } else {
                $errors[] = (string)($r['message'] ?? t('Update failed.','Échec.'));
            }
        } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    }
}

$domainName = $domain ? (string)$domain['domainname'] : '';

cd_render_head(
    $domainName ? "$domainName — " . t('Domain','Domaine') : t('Domain','Domaine'),
    $domainName
        ? '<span style="color:var(--theme2,#ffa31a)">' . htmlspecialchars($domainName) . '</span>'
        : t('Domain','Domaine'),
    t('Manage nameservers, registrar lock, and renewal settings.',
      'Gérez les nameservers, le verrouillage et le renouvellement.')
);
?>

<?php cd_account_layout_start('domains'); ?>

<?php if ($success): ?>
<div class="cd-alert cd-alert-ok"><?= htmlspecialchars($success) ?></div>
<?php endif ?>
<?php if ($errors): ?>
<div class="cd-alert cd-alert-error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
</div>
<?php endif ?>

<?php if ($domain):
    $autoRenew = !($domain['donotrenew'] ?? false);
    $lock      = !empty($domain['idprotection']); // best-effort proxy until DomainGetLockingStatus call
    ?>
<div class="cd-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
        <div>
            <h2 style="margin:0"><?= htmlspecialchars($domainName) ?></h2>
            <p class="cd-muted" style="margin:6px 0 0">
                <i class="fa fa-calendar"></i> <?= t('Registered','Enregistré') ?>:
                <strong><?= htmlspecialchars((string)($domain['regdate'] ?? '—')) ?></strong>
                &nbsp;·&nbsp;
                <i class="fa fa-clock"></i> <?= t('Expires','Expire') ?>:
                <strong><?= htmlspecialchars((string)($domain['nextduedate'] ?? '—')) ?></strong>
            </p>
        </div>
        <span class="cd-pill <?= strtolower((string)$domain['status']) === 'active' ? 'cd-pill-ok' : 'cd-pill-warn' ?>"
              style="font-size:13px;padding:6px 14px"><?= htmlspecialchars((string)$domain['status']) ?></span>
    </div>
</div>

<!-- ── Nameservers ─────────────────────────────────────────────────────── -->
<div class="cd-card">
    <div class="cd-form-section" style="margin-top:0"><i class="fa fa-server"></i><?= t('Nameservers','Nameservers') ?></div>
    <p class="cd-muted" style="margin-top:-4px">
        <?= t('Point your domain to your hosting or another DNS provider. Leave fields blank for unused slots.',
              'Pointez votre domaine vers votre hébergement ou un autre fournisseur DNS. Laissez vide les champs inutilisés.') ?>
    </p>

    <form method="post" action="">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
        <input type="hidden" name="action" value="update_ns">

        <?php for ($i = 1; $i <= 5; $i++): ?>
        <label class="cd-label">Nameserver <?= $i ?><?= $i <= 2 ? ' *' : '' ?></label>
        <input class="cd-input" type="text" name="ns<?= $i ?>"
               placeholder="ns<?= $i ?>.example.com"
               value="<?= htmlspecialchars($ns[$i-1]) ?>"
               style="margin-bottom:12px">
        <?php endfor ?>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">
            <button class="cd-btn" type="submit">
                <i class="fa fa-floppy-disk"></i> <?= t('Save nameservers','Enregistrer') ?>
            </button>
            <button class="cd-btn cd-btn-secondary" type="button" id="useDefaults">
                <i class="fa fa-rotate"></i> <?= t('Use CamDigit defaults','Valeurs par défaut') ?>
            </button>
        </div>
    </form>
</div>

<!-- ── Auto-renew + Registrar lock cards ──────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px">

    <div class="cd-card">
        <div class="cd-form-section" style="margin-top:0"><i class="fa fa-rotate-right"></i><?= t('Auto-renew','Renouv. auto') ?></div>
        <p class="cd-muted" style="margin-top:-4px">
            <?= t('Automatically renew this domain before it expires.',
                  'Renouvelez automatiquement ce domaine avant son expiration.') ?>
        </p>
        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <input type="hidden" name="action" value="toggle_autorenew">
            <label class="cd-terms" style="margin:14px 0 18px">
                <input type="checkbox" name="autorenew" value="1" <?= $autoRenew ? 'checked' : '' ?>>
                <span><?= t('Enable automatic renewal','Activer le renouvellement automatique') ?></span>
            </label>
            <button class="cd-btn" type="submit"><i class="fa fa-check"></i> <?= t('Apply','Appliquer') ?></button>
        </form>
    </div>

    <div class="cd-card">
        <div class="cd-form-section" style="margin-top:0"><i class="fa fa-lock"></i><?= t('Registrar lock','Verrouillage') ?></div>
        <p class="cd-muted" style="margin-top:-4px">
            <?= t('Prevent unauthorized transfers of this domain.',
                  'Empêche les transferts non autorisés.') ?>
        </p>
        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <input type="hidden" name="action" value="toggle_lock">
            <label class="cd-terms" style="margin:14px 0 18px">
                <input type="checkbox" name="lock" value="1" <?= $lock ? 'checked' : '' ?>>
                <span><?= t('Lock this domain','Verrouiller ce domaine') ?></span>
            </label>
            <button class="cd-btn" type="submit"><i class="fa fa-check"></i> <?= t('Apply','Appliquer') ?></button>
        </form>
    </div>

</div>

<script>
document.getElementById('useDefaults')?.addEventListener('click', function(){
    const inputs = document.querySelectorAll('input[name^="ns"]');
    if (inputs[0]) inputs[0].value = <?= json_encode(CM_NS1) ?>;
    if (inputs[1]) inputs[1].value = <?= json_encode(CM_NS2) ?>;
    for (let i=2;i<inputs.length;i++) inputs[i].value='';
});
</script>

<?php endif ?>

<?php cd_account_layout_end(); ?>
<?php cd_render_foot(); ?>
