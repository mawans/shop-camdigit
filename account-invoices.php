<?php
/**
 * CamDigit — Invoices list + single invoice view
 * Upload to: <whmcs_root>/account-invoices.php
 *
 *   /account-invoices.php          → list invoices
 *   /account-invoices.php?id=NNN   → view + pay a single invoice
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
cd_require_login();

$clientId  = cd_client_id();
$invoiceId = (int) ($_GET['id'] ?? 0);
$errors    = [];

if ($invoiceId > 0) {
    // ── Single invoice view ──────────────────────────────────────────────────
    $inv = null;
    try {
        $r = whmcs_api('GetInvoice', ['invoiceid' => $invoiceId]);
        if (($r['result'] ?? '') === 'success' && (int)($r['userid'] ?? 0) === $clientId) {
            $inv = $r;
        } else {
            $errors[] = t('Invoice not found.', 'Facture introuvable.');
        }
    } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

    $invStatus = $inv ? (string)$inv['status'] : '';
    $statusClass = strtolower($invStatus) === 'paid' ? 'cd-pill-ok'
        : (strtolower($invStatus) === 'unpaid' ? 'cd-pill-warn' : 'cd-pill-neutral');

    cd_render_head(
        t('Invoice', 'Facture') . ' #' . $invoiceId,
        t('Invoice','Facture') . ' <span style="color:var(--theme2,#ffa31a)">#' . $invoiceId . '</span>',
        $invStatus ? t('Status: ','Statut: ') . $invStatus : null
    );
    ?>

    <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
    <?php elseif ($inv): ?>
        <div class="cd-card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:8px">
                <div>
                    <h2 style="margin:0">#<?= (int)$inv['invoicenum'] ?: $invoiceId ?></h2>
                    <p class="cd-muted" style="margin:6px 0 0">
                        <i class="fa fa-calendar"></i> <?= htmlspecialchars((string)($inv['date'] ?? '')) ?>
                        &nbsp;·&nbsp;
                        <i class="fa fa-clock"></i> <?= t('Due','Échéance') ?>: <?= htmlspecialchars((string)($inv['duedate'] ?? '')) ?>
                    </p>
                </div>
                <span class="cd-pill <?= $statusClass ?>" style="font-size:14px;padding:6px 16px"><?= htmlspecialchars($invStatus) ?></span>
            </div>

            <div class="cd-divider"></div>

            <table>
                <thead><tr>
                    <th><?= t('Description','Description') ?></th>
                    <th style="text-align:right"><?= t('Amount','Montant') ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach (($inv['items']['item'] ?? []) as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$item['description']) ?></td>
                            <td style="text-align:right"><?= htmlspecialchars((string)$item['amount']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr><td style="padding:10px;text-align:right"><strong><?= t('Subtotal','Sous-total') ?></strong></td>
                        <td style="text-align:right"><?= htmlspecialchars((string)($inv['subtotal'] ?? '')) ?></td></tr>
                    <tr><td style="padding:10px;text-align:right"><strong style="font-size:16px"><?= t('Total','Total') ?></strong></td>
                        <td style="text-align:right;font-size:22px;font-weight:700;color:var(--theme,#236a25)"><?= htmlspecialchars((string)($inv['total'] ?? '')) ?></td></tr>
                </tfoot>
            </table>

            <?php if (strtolower($invStatus) === 'unpaid'): ?>
                <div style="margin-top:28px;text-align:center;background:linear-gradient(135deg,rgba(255,163,26,.08),rgba(35,106,37,.05));padding:24px;border-radius:12px">
                    <a class="cd-btn" style="font-size:16px;padding:15px 36px"
                       href="<?= SITE_URL ?>/viewinvoice.php?id=<?= $invoiceId ?>">
                        <i class="fa fa-credit-card"></i> <?= t('Pay this invoice','Payer cette facture') ?>
                    </a>
                    <p class="cd-muted" style="margin:12px 0 0;font-size:13px">
                        <i class="fa fa-shield-alt"></i>
                        <?= t('Secure payment via your configured gateway.',
                              'Paiement sécurisé via votre passerelle configurée.') ?>
                    </p>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>

    <p style="text-align:center;margin-top:8px">
        <a href="<?= SITE_URL ?>/account-invoices.php" class="cd-link-soft">
            <i class="fa fa-arrow-left"></i> <?= t('Back to all invoices','Retour aux factures') ?>
        </a>
    </p>

    <?php cd_render_foot();
    exit;
}

// ── List view ───────────────────────────────────────────────────────────────
$invoices = [];
try {
    $r = whmcs_api('GetInvoices', ['userid' => $clientId, 'limitnum' => 100]);
    $invoices = $r['invoices']['invoice'] ?? [];
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

cd_render_head(
    t('My Invoices', 'Mes Factures'),
    t('My <span style="color:var(--theme2,#ffa31a)">Invoices</span>',
      'Mes <span style="color:var(--theme2,#ffa31a)">Factures</span>'),
    t('Pay outstanding invoices and review your billing history.',
      'Payez les factures impayées et consultez votre historique.')
);
?>

<div class="cd-card">
    <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (!$invoices): ?>
        <div style="text-align:center;padding:50px 20px">
            <i class="fa fa-file-invoice" style="font-size:48px;color:#cbd2dd;margin-bottom:16px"></i>
            <h3><?= t('No invoices yet', 'Aucune facture') ?></h3>
            <p class="cd-muted"><?= t('Your invoices will appear here once you place an order.', 'Vos factures apparaîtront ici dès votre première commande.') ?></p>
        </div>
    <?php else: ?>
        <table>
            <thead><tr>
                <th>#</th>
                <th><?= t('Date','Date') ?></th>
                <th><?= t('Due date','Échéance') ?></th>
                <th><?= t('Total','Total') ?></th>
                <th><?= t('Status','Statut') ?></th>
                <th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($invoices as $inv):
                    $status = (string)$inv['status'];
                    $s = strtolower($status);
                    $pill = $s === 'paid' ? 'cd-pill-ok' : ($s === 'unpaid' ? 'cd-pill-warn' : ($s === 'cancelled' ? 'cd-pill-err' : 'cd-pill-neutral'));
                    ?>
                    <tr>
                        <td><strong>#<?= (int)$inv['id'] ?></strong></td>
                        <td><?= htmlspecialchars((string)($inv['date'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($inv['duedate'] ?? '')) ?></td>
                        <td><strong style="color:var(--theme,#236a25)"><?= htmlspecialchars((string)($inv['total'] ?? '')) ?></strong></td>
                        <td><span class="cd-pill <?= $pill ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td style="text-align:right">
                            <?php if ($s === 'unpaid'): ?>
                                <a class="cd-btn" style="padding:7px 16px;font-size:12px" href="?id=<?= (int)$inv['id'] ?>"><?= t('Pay now','Payer') ?> →</a>
                            <?php else: ?>
                                <a href="?id=<?= (int)$inv['id'] ?>"><?= t('View','Voir') ?> →</a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<p style="text-align:center;margin-top:8px">
    <a href="<?= SITE_URL ?>/account.php" class="cd-link-soft">
        <i class="fa fa-arrow-left"></i> <?= t('Back to My Account','Retour à mon compte') ?>
    </a>
</p>

<?php cd_render_foot(); ?>
