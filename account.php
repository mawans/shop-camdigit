<?php
/**
 * CamDigit Main — Account Dashboard
 * Pulls real data from WHMCS via API: client details, products, domains, invoices.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/layout.php';

cd_require_login();
$cid = cd_client_id();

// ── Pull data from WHMCS ────────────────────────────────────────────────────
$client = ['firstname' => '', 'lastname' => '', 'email' => '', 'credit' => 0, 'currency_code' => 'XAF'];
$services = [];
$domains  = [];
$invoices = ['unpaid' => [], 'recent' => []];
$err = null;

try {
    $r = whmcs_api('GetClientsDetails', ['clientid' => $cid, 'stats' => true]);
    $client = $r['client'] ?? $client;

    $sp = whmcs_api('GetClientsProducts', ['clientid' => $cid]);
    $services = $sp['products']['product'] ?? [];

    $dn = whmcs_api('GetClientsDomains', ['clientid' => $cid]);
    $domains = $dn['domains']['domain'] ?? [];

    $iv = whmcs_api('GetInvoices', ['userid' => $cid, 'limitnum' => 25]);
    $allInv = $iv['invoices']['invoice'] ?? [];
    foreach ($allInv as $i) {
        if (in_array(strtolower((string)($i['status'] ?? '')), ['unpaid','overdue'], true)) {
            $invoices['unpaid'][] = $i;
        } else {
            $invoices['recent'][] = $i;
        }
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
}

$countActive = 0; $countSuspended = 0;
foreach ($services as $s) {
    $st = strtolower((string)($s['status'] ?? ''));
    if ($st === 'active')          $countActive++;
    elseif ($st === 'suspended')   $countSuspended++;
}
$countDomains = count($domains);
$countUnpaid  = count($invoices['unpaid']);

function acc_pill(string $status): string
{
    $st = strtolower($status);
    $map = [
        'active'    => ['ok',     'Active'],
        'pending'   => ['warn',   'Pending'],
        'suspended' => ['err',    'Suspended'],
        'terminated'=> ['neutral','Terminated'],
        'cancelled' => ['neutral','Cancelled'],
        'expired'   => ['err',    'Expired'],
        'paid'      => ['ok',     'Paid'],
        'unpaid'    => ['warn',   'Unpaid'],
        'overdue'   => ['err',    'Overdue'],
        'refunded'  => ['neutral','Refunded'],
    ];
    [$cls, $lbl] = $map[$st] ?? ['neutral', ucfirst($status ?: '—')];
    return '<span class="cdm-pill cdm-pill-' . $cls . '">' . htmlspecialchars($lbl) . '</span>';
}

$first = htmlspecialchars(trim((string)$client['firstname']) ?: t('there','vous'));

cdm_head([
    'title'         => t('Dashboard','Tableau de bord'),
    'hero_title'    => t('Hello, <span class="accent">' . $first . '</span>',
                         'Bonjour, <span class="accent">' . $first . '</span>'),
    'hero_subtitle' => t('Manage your services, domains and invoices from one place.',
                         'Gérez vos services, domaines et factures depuis un seul endroit.'),
    'breadcrumb'    => t('Dashboard','Tableau de bord'),
]);
?>

<div class="cdm-app">

<?php cdm_account_sidebar('dashboard'); ?>

<div>
    <?php if ($err): ?>
        <div class="cdm-alert cdm-alert-err">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><strong><?= t('Could not load account data','Impossible de charger les données') ?></strong><br><?= htmlspecialchars($err) ?></div>
        </div>
    <?php endif ?>

    <!-- Stats -->
    <div class="cdm-stats">
        <div class="cdm-stat">
            <div class="cdm-stat-icon green"><i class="fa-solid fa-cubes"></i></div>
            <div>
                <div class="cdm-stat-label"><?= t('Active services','Services actifs') ?></div>
                <div class="cdm-stat-value"><?= $countActive ?><?php if ($countSuspended): ?> <small style="font-size:13px;color:var(--err);font-weight:600">+<?= $countSuspended ?> <?= t('susp.','susp.') ?></small><?php endif ?></div>
            </div>
        </div>
        <div class="cdm-stat">
            <div class="cdm-stat-icon orange"><i class="fa-solid fa-globe"></i></div>
            <div>
                <div class="cdm-stat-label"><?= t('Domains','Domaines') ?></div>
                <div class="cdm-stat-value"><?= $countDomains ?></div>
            </div>
        </div>
        <div class="cdm-stat">
            <div class="cdm-stat-icon red"><i class="fa-solid fa-file-invoice"></i></div>
            <div>
                <div class="cdm-stat-label"><?= t('Unpaid invoices','Factures impayées') ?></div>
                <div class="cdm-stat-value"><?= $countUnpaid ?></div>
            </div>
        </div>
        <div class="cdm-stat">
            <div class="cdm-stat-icon blue"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <div class="cdm-stat-label"><?= t('Account credit','Crédit') ?></div>
                <div class="cdm-stat-value" style="font-size:18px"><?= htmlspecialchars((string)($client['currency_code'] ?? '')) ?> <?= number_format((float)($client['credit'] ?? 0), 2, ',', ' ') ?></div>
            </div>
        </div>
    </div>

    <!-- Unpaid invoices -->
    <?php if ($invoices['unpaid']): ?>
    <div class="cdm-card">
        <div class="cdm-card-head" style="background:linear-gradient(135deg,#dc2626,#991b1b)">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> <?= t('Action required','Action requise') ?></h3>
            <span class="meta"><?= $countUnpaid ?> <?= $countUnpaid === 1 ? t('invoice','facture') : t('invoices','factures') ?></span>
        </div>
        <div class="cdm-card-body">
            <p class="cdm-muted cdm-mb-2"><?= t('You have unpaid invoices. Settle them to keep your services active.',
                                                 'Vous avez des factures impayées. Réglez-les pour garder vos services actifs.') ?></p>
            <div class="cdm-table-wrap">
                <table class="cdm-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= t('Due','Échéance') ?></th>
                            <th><?= t('Total','Total') ?></th>
                            <th><?= t('Status','Statut') ?></th>
                            <th class="right"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices['unpaid'] as $i): ?>
                        <tr>
                            <td data-label="#">#<?= htmlspecialchars((string)$i['id']) ?></td>
                            <td data-label="<?= t('Due','Échéance') ?>"><?= htmlspecialchars((string)($i['duedate'] ?? '')) ?></td>
                            <td data-label="<?= t('Total','Total') ?>"><strong><?= number_format((float)($i['total'] ?? 0), 2, ',', ' ') ?> <?= htmlspecialchars((string)($i['currencycode'] ?? '')) ?></strong></td>
                            <td data-label="<?= t('Status','Statut') ?>"><?= acc_pill((string)($i['status'] ?? '')) ?></td>
                            <td class="right">
                                <a href="<?= SITE_URL ?>/account-invoices.php?id=<?= (int)$i['id'] ?>" class="cdm-btn cdm-btn-accent cdm-btn-sm">
                                    <i class="fa-solid fa-credit-card"></i> <?= t('Pay','Payer') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif ?>

    <!-- Two-column: services / domains -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px" class="dash-cols">
    <style>@media(max-width:767px){.dash-cols{grid-template-columns:1fr !important}}</style>

        <div class="cdm-card">
            <div class="cdm-card-head">
                <h3><i class="fa-solid fa-cubes"></i> <?= t('My Services','Mes Services') ?></h3>
                <a href="<?= SITE_URL ?>/account-services.php" style="color:rgba(255,255,255,.9);font-size:13px;font-weight:600">
                    <?= t('View all','Voir tout') ?> <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="cdm-card-body" style="padding:0">
            <?php if (empty($services)): ?>
                <div class="cdm-empty" style="box-shadow:none;border:0;padding:32px 24px">
                    <div class="cdm-empty-icon" style="width:64px;height:64px;font-size:24px"><i class="fa-solid fa-cubes"></i></div>
                    <h2 style="font-size:17px"><?= t('No services yet','Aucun service') ?></h2>
                    <p style="font-size:14px"><?= t('Pick a hosting plan to launch your site.','Choisissez un hébergement pour lancer votre site.') ?></p>
                    <a class="cdm-btn cdm-btn-sm" href="<?= SITE_URL ?>/order-hosting.php"><?= t('Browse hosting','Voir l\'hébergement') ?></a>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($services, 0, 4) as $s): ?>
                <div style="padding:14px 22px;border-bottom:1px solid var(--line-soft);display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <div style="min-width:0;flex:1">
                        <div style="font-weight:600;font-size:14px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars((string)($s['name'] ?? $s['groupname'] ?? '—')) ?></div>
                        <div style="font-size:12px;color:var(--ink-mute);margin-top:2px"><?= htmlspecialchars((string)($s['domain'] ?? '')) ?></div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
                        <?= acc_pill((string)($s['status'] ?? '')) ?>
                        <a href="<?= SITE_URL ?>/account-service.php?id=<?= (int)($s['id'] ?? 0) ?>" class="cdm-btn cdm-btn-ghost cdm-btn-sm"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach ?>
            <?php endif ?>
            </div>
        </div>

        <div class="cdm-card">
            <div class="cdm-card-head">
                <h3><i class="fa-solid fa-globe"></i> <?= t('My Domains','Mes Domaines') ?></h3>
                <a href="<?= SITE_URL ?>/account-domains.php" style="color:rgba(255,255,255,.9);font-size:13px;font-weight:600">
                    <?= t('View all','Voir tout') ?> <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="cdm-card-body" style="padding:0">
            <?php if (empty($domains)): ?>
                <div class="cdm-empty" style="box-shadow:none;border:0;padding:32px 24px">
                    <div class="cdm-empty-icon" style="width:64px;height:64px;font-size:24px"><i class="fa-solid fa-globe"></i></div>
                    <h2 style="font-size:17px"><?= t('No domains yet','Aucun domaine') ?></h2>
                    <p style="font-size:14px"><?= t('Register your perfect name.','Réservez votre nom de marque.') ?></p>
                    <a class="cdm-btn cdm-btn-sm" href="<?= SITE_URL ?>/order-domain.php"><?= t('Find a domain','Chercher un domaine') ?></a>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($domains, 0, 4) as $d): ?>
                <div style="padding:14px 22px;border-bottom:1px solid var(--line-soft);display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <div style="min-width:0;flex:1">
                        <div style="font-weight:600;font-size:14px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars((string)($d['domainname'] ?? '—')) ?></div>
                        <div style="font-size:12px;color:var(--ink-mute);margin-top:2px"><?= t('Expires','Expire') ?>: <?= htmlspecialchars((string)($d['expirydate'] ?? '—')) ?></div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
                        <?= acc_pill((string)($d['status'] ?? '')) ?>
                        <a href="<?= SITE_URL ?>/account-domain.php?id=<?= (int)($d['id'] ?? 0) ?>" class="cdm-btn cdm-btn-ghost cdm-btn-sm"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach ?>
            <?php endif ?>
            </div>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="cdm-card cdm-mt-3" style="background:linear-gradient(135deg,#f3f7fb,#e8eef6);border:0;box-shadow:none">
        <div class="cdm-card-body">
            <h3 style="margin:0 0 14px;font-size:16px"><?= t('Quick actions','Actions rapides') ?></h3>
            <div class="cdm-flex cdm-wrap cdm-gap-1">
                <a class="cdm-btn cdm-btn-accent" href="<?= SITE_URL ?>/order-hosting.php"><i class="fa-solid fa-server"></i> <?= t('Order hosting','Commander hébergement') ?></a>
                <a class="cdm-btn" href="<?= SITE_URL ?>/order-domain.php"><i class="fa-solid fa-globe"></i> <?= t('Register a domain','Enregistrer un domaine') ?></a>
                <a class="cdm-btn cdm-btn-ghost" href="<?= SITE_URL ?>/submitticket.php"><i class="fa-solid fa-life-ring"></i> <?= t('Open a ticket','Ouvrir un ticket') ?></a>
                <a class="cdm-btn cdm-btn-ghost" href="<?= SITE_URL ?>/account-profile.php"><i class="fa-solid fa-id-card"></i> <?= t('Update profile','Modifier le profil') ?></a>
            </div>
        </div>
    </div>
</div>

</div>

<?php cdm_foot(); ?>
