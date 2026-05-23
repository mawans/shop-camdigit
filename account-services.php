<?php
/**
 * CamDigit — Services List
 * Upload to: <whmcs_root>/account-services.php
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';
cd_require_login();

$clientId = cd_client_id();
$errors   = [];
$products = [];

try {
    $r = whmcs_api('GetClientsProducts', ['clientid' => $clientId, 'limitnum' => 100]);
    $products = $r['products']['product'] ?? [];
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

cd_render_head(
    t('My Services', 'Mes Services'),
    t('My <span style="color:var(--theme2,#ffa31a)">Services</span>',
      'Mes <span style="color:var(--theme2,#ffa31a)">Services</span>'),
    t('All your hosting plans and add-ons in one place.',
      'Tous vos plans d\'hébergement et add-ons en un seul endroit.')
);

function cd_status_pill(string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['active','registered','paid'], true)) return 'cd-pill-ok';
    if (in_array($s, ['pending','pending registration'], true)) return 'cd-pill-warn';
    if (in_array($s, ['cancelled','suspended','terminated','expired','fraud'], true)) return 'cd-pill-err';
    return 'cd-pill-neutral';
}
?>

<?php cd_account_layout_start('services'); ?>

<div class="cd-card">
    <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (!$products): ?>
        <div style="text-align:center;padding:50px 20px">
            <i class="fa fa-cubes" style="font-size:48px;color:#cbd2dd;margin-bottom:16px"></i>
            <h3><?= t('No active services yet', 'Aucun service actif') ?></h3>
            <p class="cd-muted"><?= t('Browse our hosting plans to get started.', 'Découvrez nos plans d\'hébergement pour commencer.') ?></p>
            <a class="cd-btn" href="<?= SITE_URL ?>/order-hosting.php"><i class="fa fa-server"></i> <?= t('Browse hosting plans', 'Voir les plans') ?></a>
        </div>
    <?php else: ?>
        <table>
            <thead><tr>
                <th><?= t('Product','Produit') ?></th>
                <th><?= t('Domain','Domaine') ?></th>
                <th><?= t('Next due','Échéance') ?></th>
                <th><?= t('Status','Statut') ?></th>
                <th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string)$p['name']) ?></strong></td>
                        <td><?= htmlspecialchars((string)($p['domain'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($p['nextduedate'] ?? '')) ?></td>
                        <td><span class="cd-pill <?= cd_status_pill((string)$p['status']) ?>"><?= htmlspecialchars((string)$p['status']) ?></span></td>
                        <td style="text-align:right">
                            <a class="cd-btn cd-btn-ghost" style="padding:6px 14px;font-size:12px"
                               href="<?= SITE_URL ?>/account-service.php?id=<?= (int)$p['id'] ?>">
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
