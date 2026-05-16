<?php
/**
 * CamDigit Client Area Dashboard
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/account.php
 *
 * Shows a logged-in client's summary: profile + recent products, domains and
 * unpaid invoices. All data pulled server-side from WHMCS API.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

cd_require_login();
$clientId = cd_client_id();

$client     = [];
$products   = [];
$domains    = [];
$invoices   = [];
$errors     = [];

try {
    $r = whmcs_api('GetClientsDetails', ['clientid' => $clientId, 'stats' => true]);
    if (($r['result'] ?? '') === 'success') {
        $client = $r['client'] ?? [];
        $stats  = $r['stats'] ?? [];
    }
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

try {
    $r = whmcs_api('GetClientsProducts', ['clientid' => $clientId, 'limitnum' => 5]);
    $products = $r['products']['product'] ?? [];
} catch (RuntimeException $e) { /* non-fatal */ }

try {
    $r = whmcs_api('GetClientsDomains', ['clientid' => $clientId, 'limitnum' => 5]);
    $domains = $r['domains']['domain'] ?? [];
} catch (RuntimeException $e) { /* non-fatal */ }

try {
    $r = whmcs_api('GetInvoices', ['userid' => $clientId, 'status' => 'Unpaid', 'limitnum' => 10]);
    $invoices = $r['invoices']['invoice'] ?? [];
} catch (RuntimeException $e) { /* non-fatal */ }

$firstName = (string)($client['firstname'] ?? '');
$lastName  = (string)($client['lastname']  ?? '');
$dueCount  = isset($stats['numdueinvoices']) ? (int)$stats['numdueinvoices'] : 0;

cd_render_head(
    t('My Account', 'Mon Compte'),
    t('Welcome back, <span style="color:var(--theme2,#ffa31a)">', 'Bon retour, <span style="color:var(--theme2,#ffa31a)">')
        . htmlspecialchars(trim($firstName . ' ' . $lastName) ?: t('Client','Client')) . '</span>',
    t('Manage your services, domains, and invoices.',
      'Gérez vos services, domaines et factures.')
);
?>

<?php if ($errors): ?>
    <div class="cd-alert cd-alert-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
    </div>
<?php endif ?>

<!-- Stat boxes -->
<div class="cd-stats-row">
    <div class="cd-stat-box">
        <i class="fa fa-cubes"></i>
        <div class="cd-stat-num"><?= count($products) ?></div>
        <div class="cd-stat-lbl"><?= t('Active Services','Services actifs') ?></div>
    </div>
    <div class="cd-stat-box">
        <i class="fa fa-globe-africa"></i>
        <div class="cd-stat-num"><?= count($domains) ?></div>
        <div class="cd-stat-lbl"><?= t('Registered Domains','Domaines enregistrés') ?></div>
    </div>
    <div class="cd-stat-box">
        <i class="fa fa-file-invoice-dollar"></i>
        <div class="cd-stat-num"><?= $dueCount ?></div>
        <div class="cd-stat-lbl"><?= t('Unpaid Invoices','Factures impayées') ?></div>
    </div>
</div>

<div class="cd-card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:18px">
        <div>
            <p class="cd-muted" style="margin:0">
                <i class="fa fa-envelope"></i> <?= htmlspecialchars((string)($client['email'] ?? '')) ?>
                &nbsp;·&nbsp; <?= t('Client ID','ID Client') ?>: <strong>#<?= (int)$clientId ?></strong>
            </p>
        </div>
        <div>
            <?php if ($dueCount > 0): ?>
                <span class="cd-pill cd-pill-warn"><i class="fa fa-exclamation-circle"></i> <?= $dueCount ?> <?= t('unpaid','impayée(s)') ?></span>
            <?php else: ?>
                <span class="cd-pill cd-pill-ok"><i class="fa fa-check"></i> <?= t('All invoices paid','Tout est payé') ?></span>
            <?php endif ?>
        </div>
    </div>

    <div class="cd-divider"></div>

    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:18px">
        <a class="cd-btn" href="<?= SITE_URL ?>/order-domain.php"><i class="fa fa-globe"></i> <?= t('Register a domain','Enregistrer un domaine') ?></a>
        <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/order-hosting.php"><i class="fa fa-server"></i> <?= t('Buy hosting','Acheter hébergement') ?></a>
        <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/account-services.php"><i class="fa fa-cubes"></i> <?= t('My services','Mes services') ?></a>
        <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/account-domains.php"><i class="fa fa-globe-africa"></i> <?= t('My domains','Mes domaines') ?></a>
        <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/account-invoices.php"><i class="fa fa-file-invoice"></i> <?= t('My invoices','Mes factures') ?></a>
    </div>
</div>

<?php
// Helper: map WHMCS status string to a pill class
function cd_status_pill(string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['paid','active','registered','answered','closed'], true)) return 'cd-pill-ok';
    if (in_array($s, ['unpaid','pending','pending registration','overdue','customer-reply'], true)) return 'cd-pill-warn';
    if (in_array($s, ['cancelled','suspended','terminated','expired','fraud'], true)) return 'cd-pill-err';
    return 'cd-pill-neutral';
}
?>

<?php if ($invoices): ?>
<div class="cd-card">
    <h2><i class="fa fa-file-invoice" style="color:var(--theme2,#ffa31a);margin-right:8px"></i><?= t('Unpaid invoices','Factures impayées') ?></h2>
    <table>
        <thead><tr>
            <th>#</th><th><?= t('Due date','Échéance') ?></th>
            <th><?= t('Total','Total') ?></th><th></th>
        </tr></thead>
        <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong>#<?= (int)$inv['id'] ?></strong></td>
                    <td><?= htmlspecialchars((string)($inv['duedate'] ?? '')) ?></td>
                    <td><strong style="color:var(--theme,#236a25)"><?= htmlspecialchars((string)($inv['total'] ?? '')) ?></strong></td>
                    <td style="text-align:right">
                        <a class="cd-btn cd-btn-ghost" style="padding:7px 16px;font-size:12px"
                           href="<?= SITE_URL ?>/account-invoices.php?id=<?= (int)$inv['id'] ?>">
                            <?= t('View & Pay','Voir & Payer') ?> <i class="fa fa-arrow-right"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px">
    <div class="cd-card">
        <h2><i class="fa fa-cubes" style="color:var(--theme2,#ffa31a);margin-right:8px"></i><?= t('Recent services','Services récents') ?></h2>
        <?php if (!$products): ?>
            <p class="cd-muted"><?= t('No active services yet.','Aucun service actif.') ?></p>
            <a class="cd-btn" href="<?= SITE_URL ?>/order-hosting.php"><?= t('Browse hosting','Voir l\'hébergement') ?></a>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0">
                <?php foreach ($products as $p): ?>
                    <li style="padding:14px 0;border-bottom:1px solid #f0f4f8">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                            <div>
                                <strong><?= htmlspecialchars((string)$p['name']) ?></strong><br>
                                <span class="cd-muted" style="font-size:13px"><?= htmlspecialchars((string)($p['domain'] ?? '')) ?></span>
                            </div>
                            <span class="cd-pill <?= cd_status_pill((string)$p['status']) ?>"><?= htmlspecialchars((string)$p['status']) ?></span>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
            <a href="<?= SITE_URL ?>/account-services.php" style="display:inline-block;margin-top:14px;font-weight:600"><?= t('View all','Voir tout') ?> →</a>
        <?php endif ?>
    </div>
    <div class="cd-card">
        <h2><i class="fa fa-globe-africa" style="color:var(--theme2,#ffa31a);margin-right:8px"></i><?= t('Recent domains','Domaines récents') ?></h2>
        <?php if (!$domains): ?>
            <p class="cd-muted"><?= t('No domains registered yet.','Aucun domaine enregistré.') ?></p>
            <a class="cd-btn" href="<?= SITE_URL ?>/order-domain.php"><?= t('Register a domain','Enregistrer un domaine') ?></a>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0">
                <?php foreach ($domains as $d): ?>
                    <li style="padding:14px 0;border-bottom:1px solid #f0f4f8">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                            <div>
                                <strong><?= htmlspecialchars((string)$d['domainname']) ?></strong><br>
                                <span class="cd-muted" style="font-size:13px"><?= t('Renews','Renouvellement') ?>: <?= htmlspecialchars((string)($d['nextduedate'] ?? '')) ?></span>
                            </div>
                            <span class="cd-pill <?= cd_status_pill((string)$d['status']) ?>"><?= htmlspecialchars((string)$d['status']) ?></span>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
            <a href="<?= SITE_URL ?>/account-domains.php" style="display:inline-block;margin-top:14px;font-weight:600"><?= t('View all','Voir tout') ?> →</a>
        <?php endif ?>
    </div>
</div>

<?php cd_render_foot(); ?>
