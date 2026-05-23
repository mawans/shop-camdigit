<?php
/**
 * CamDigit — Domains List
 * Upload to: <whmcs_root>/account-domains.php
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';
cd_require_login();

$clientId = cd_client_id();
$errors   = [];
$domains  = [];

try {
    $r = whmcs_api('GetClientsDomains', ['clientid' => $clientId, 'limitnum' => 200]);
    $domains = $r['domains']['domain'] ?? [];
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

cd_render_head(
    t('My Domains', 'Mes Domaines'),
    t('My <span style="color:var(--theme2,#ffa31a)">Domains</span>',
      'Mes <span style="color:var(--theme2,#ffa31a)">Domaines</span>'),
    t('Manage DNS, contacts, and renewals for all your domains.',
      'Gérez DNS, contacts et renouvellements pour tous vos domaines.')
);

function cd_domain_pill(string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['active','registered'], true)) return 'cd-pill-ok';
    if (in_array($s, ['pending','pending registration','pending transfer'], true)) return 'cd-pill-warn';
    if (in_array($s, ['cancelled','expired','transferred away'], true)) return 'cd-pill-err';
    return 'cd-pill-neutral';
}
?>

<?php cd_account_layout_start('domains'); ?>

<div class="cd-card">
    <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (!$domains): ?>
        <div style="text-align:center;padding:50px 20px">
            <i class="fa fa-globe-africa" style="font-size:48px;color:#cbd2dd;margin-bottom:16px"></i>
            <h3><?= t('No domains registered yet', 'Aucun domaine enregistré') ?></h3>
            <p class="cd-muted"><?= t('Find and register your perfect domain name in seconds.', 'Trouvez et enregistrez votre nom de domaine idéal en quelques secondes.') ?></p>
            <a class="cd-btn" href="<?= SITE_URL ?>/order-domain.php"><i class="fa fa-search"></i> <?= t('Register a domain', 'Enregistrer un domaine') ?></a>
        </div>
    <?php else: ?>
        <table>
            <thead><tr>
                <th><?= t('Domain','Domaine') ?></th>
                <th><?= t('Registered','Enregistré le') ?></th>
                <th><?= t('Expires','Expire le') ?></th>
                <th><?= t('Auto-renew','Renouv. auto') ?></th>
                <th><?= t('Status','Statut') ?></th>
                <th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($domains as $d):
                    $autoRenew = !($d['donotrenew'] ?? false);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string)$d['domainname']) ?></strong></td>
                        <td><?= htmlspecialchars((string)($d['regdate'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($d['nextduedate'] ?? '')) ?></td>
                        <td><span class="cd-pill <?= $autoRenew ? 'cd-pill-ok' : 'cd-pill-neutral' ?>"><?= $autoRenew ? t('On','Activé') : t('Off','Désactivé') ?></span></td>
                        <td><span class="cd-pill <?= cd_domain_pill((string)$d['status']) ?>"><?= htmlspecialchars((string)$d['status']) ?></span></td>
                        <td style="text-align:right">
                            <a class="cd-btn cd-btn-ghost" style="padding:6px 14px;font-size:12px"
                               href="<?= SITE_URL ?>/account-domain.php?id=<?= (int)$d['id'] ?>">
                                <?= t('Manage','Gérer') ?> <i class="fa fa-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?php cd_account_layout_end(); ?>
<?php cd_render_foot(); ?>
