<?php
/**
 * CamDigit — Tickets list + single-ticket view (with reply)
 * Upload to: <whmcs_root>/account-tickets.php
 *
 *   /account-tickets.php          → list tickets
 *   /account-tickets.php?id=NNN   → view + reply
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/account_nav.php';
cd_require_login();

$clientId = cd_client_id();
$ticketId = (int)($_GET['id'] ?? 0);
$errors   = [];
$success  = '';

// ── Single ticket view ─────────────────────────────────────────────────────
if ($ticketId > 0) {
    $ticket = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        cd_csrf_check();
        if (!cd_rate_limit('treply_' . $clientId, 12, 600)) {
            $errors[] = t('Too many replies. Please wait.','Trop de réponses. Patientez.');
        } else {
            $message = trim((string)($_POST['message'] ?? ''));
            $message = substr(strip_tags($message), 0, 8000);
            if (!$message) {
                $errors[] = t('Please enter a reply.','Veuillez entrer une réponse.');
            } else {
                try {
                    $r = whmcs_api('AddTicketReply', [
                        'ticketid' => $ticketId,
                        'clientid' => $clientId,
                        'message'  => $message,
                    ]);
                    if (($r['result'] ?? '') === 'success') {
                        $success = t('Reply sent.','Réponse envoyée.');
                    } else {
                        $errors[] = (string)($r['message'] ?? t('Reply failed.','Échec.'));
                    }
                } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
            }
        }
    }

    try {
        $r = whmcs_api('GetTicket', ['ticketid' => $ticketId]);
        if (($r['result'] ?? '') === 'success' && (int)($r['userid'] ?? 0) === $clientId) {
            $ticket = $r;
        } else {
            $errors[] = t('Ticket not found.','Ticket introuvable.');
        }
    } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

    cd_render_head(
        t('Ticket','Ticket') . ' #' . $ticketId,
        '<span style="color:var(--theme2,#ffa31a)">#' . $ticketId . '</span>',
        $ticket ? htmlspecialchars((string)$ticket['subject']) : null
    );
    cd_account_layout_start('tickets');
    ?>
    <?php if ($success): ?><div class="cd-alert cd-alert-ok"><?= htmlspecialchars($success) ?></div><?php endif ?>
    <?php if ($errors): ?>
    <div class="cd-alert cd-alert-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if ($ticket):
        $status = (string)$ticket['status']; $s = strtolower($status);
        $pill = in_array($s,['open','customer-reply','awaiting reply','in progress'],true) ? 'cd-pill-warn'
              : (in_array($s,['answered'],true) ? 'cd-pill-ok'
              : (in_array($s,['closed'],true) ? 'cd-pill-neutral' : 'cd-pill-neutral'));
        $replies = $ticket['replies']['reply'] ?? []; ?>

    <div class="cd-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
            <div>
                <h2 style="margin:0"><?= htmlspecialchars((string)$ticket['subject']) ?></h2>
                <p class="cd-muted" style="margin:6px 0 0">
                    <i class="fa fa-calendar"></i> <?= htmlspecialchars((string)$ticket['date']) ?>
                    &nbsp;·&nbsp;
                    <i class="fa fa-tag"></i> <?= htmlspecialchars((string)($ticket['priority'] ?? '')) ?>
                </p>
            </div>
            <span class="cd-pill <?= $pill ?>" style="font-size:13px;padding:6px 14px"><?= htmlspecialchars($status) ?></span>
        </div>
    </div>

    <!-- Original message -->
    <div class="cd-card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <div class="cd-acc-avatar" style="width:36px;height:36px;font-size:13px"><i class="fa fa-user"></i></div>
            <div>
                <strong><?= t('You','Vous') ?></strong>
                <span class="cd-muted" style="font-size:12px;margin-left:8px"><?= htmlspecialchars((string)$ticket['date']) ?></span>
            </div>
        </div>
        <div style="white-space:pre-wrap;color:#445375;line-height:1.7"><?= nl2br(htmlspecialchars((string)$ticket['message'])) ?></div>
    </div>

    <!-- Replies -->
    <?php foreach ($replies as $rep):
        $isAdmin = !empty($rep['admin']); ?>
    <div class="cd-card" style="<?= $isAdmin ? 'background:linear-gradient(to right,#f0fdf4,#fff);border-left:3px solid var(--theme,#236a25)' : '' ?>">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <div class="cd-acc-avatar" style="width:36px;height:36px;font-size:13px;<?= $isAdmin ? 'background:linear-gradient(135deg,var(--theme,#236a25),var(--theme2,#ffa31a))' : '' ?>">
                <i class="fa <?= $isAdmin ? 'fa-headset' : 'fa-user' ?>"></i>
            </div>
            <div>
                <strong><?= $isAdmin ? t('Support team','Équipe support') : t('You','Vous') ?></strong>
                <span class="cd-muted" style="font-size:12px;margin-left:8px"><?= htmlspecialchars((string)($rep['date'] ?? '')) ?></span>
            </div>
        </div>
        <div style="white-space:pre-wrap;color:#445375;line-height:1.7"><?= nl2br(htmlspecialchars((string)($rep['message'] ?? ''))) ?></div>
    </div>
    <?php endforeach ?>

    <!-- Reply form -->
    <?php if (!in_array($s, ['closed'], true)): ?>
    <div class="cd-card">
        <div class="cd-form-section" style="margin-top:0"><i class="fa fa-reply"></i><?= t('Reply','Répondre') ?></div>
        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <textarea class="cd-input" name="message" rows="6" required
                      placeholder="<?= t('Type your reply…','Tapez votre réponse…') ?>"
                      style="font-family:inherit;resize:vertical"></textarea>
            <button class="cd-btn" type="submit" style="margin-top:14px">
                <i class="fa fa-paper-plane"></i> <?= t('Send reply','Envoyer la réponse') ?>
            </button>
        </form>
    </div>
    <?php endif ?>
    <?php endif ?>

    <p style="text-align:center;margin-top:8px">
        <a href="<?= SITE_URL ?>/account-tickets.php" class="cd-link-soft">
            <i class="fa fa-arrow-left"></i> <?= t('Back to all tickets','Retour aux tickets') ?>
        </a>
    </p>

    <?php cd_account_layout_end(); cd_render_foot(); exit;
}

// ── List view ──────────────────────────────────────────────────────────────
$tickets = [];
try {
    $r = whmcs_api('GetTickets', ['clientid' => $clientId, 'limitnum' => 100]);
    $tickets = $r['tickets']['ticket'] ?? [];
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

cd_render_head(
    t('Support Tickets','Tickets'),
    t('Support <span style="color:var(--theme2,#ffa31a)">Tickets</span>',
      '<span style="color:var(--theme2,#ffa31a)">Tickets</span> Support'),
    t('Review and reply to your support conversations.',
      'Consultez et répondez à vos demandes.')
);
cd_account_layout_start('tickets');
?>

<div class="cd-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
        <h2 style="margin:0"><i class="fa fa-life-ring" style="color:var(--theme2,#ffa31a);margin-right:8px"></i><?= t('Your tickets','Vos tickets') ?></h2>
        <a class="cd-btn" href="<?= SITE_URL ?>/submitticket.php">
            <i class="fa fa-plus"></i> <?= t('New ticket','Nouveau ticket') ?>
        </a>
    </div>

    <?php if ($errors): ?>
    <div class="cd-alert cd-alert-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if (!$tickets): ?>
    <div style="text-align:center;padding:50px 20px">
        <i class="fa fa-life-ring" style="font-size:48px;color:#cbd2dd;margin-bottom:16px"></i>
        <h3><?= t('No tickets yet','Aucun ticket') ?></h3>
        <p class="cd-muted"><?= t('Need help? Open a ticket and our team will get back to you.',
                                   'Besoin d\'aide ? Ouvrez un ticket et notre équipe vous répondra.') ?></p>
        <a class="cd-btn" href="<?= SITE_URL ?>/submitticket.php"><i class="fa fa-plus"></i> <?= t('Open a ticket','Ouvrir un ticket') ?></a>
    </div>
    <?php else: ?>
    <table>
        <thead><tr>
            <th>#</th>
            <th><?= t('Subject','Sujet') ?></th>
            <th><?= t('Last update','Dernière màj') ?></th>
            <th><?= t('Status','Statut') ?></th>
            <th></th>
        </tr></thead>
        <tbody>
            <?php foreach ($tickets as $tk):
                $st = strtolower((string)$tk['status']);
                $pill = in_array($st,['open','customer-reply','awaiting reply','in progress'],true) ? 'cd-pill-warn'
                      : ($st === 'answered' ? 'cd-pill-ok'
                      : ($st === 'closed' ? 'cd-pill-neutral' : 'cd-pill-neutral')); ?>
            <tr>
                <td><strong>#<?= (int)$tk['id'] ?></strong></td>
                <td><?= htmlspecialchars((string)$tk['subject']) ?></td>
                <td><?= htmlspecialchars((string)$tk['lastreply']) ?></td>
                <td><span class="cd-pill <?= $pill ?>"><?= htmlspecialchars((string)$tk['status']) ?></span></td>
                <td style="text-align:right">
                    <a href="?id=<?= (int)$tk['id'] ?>"><?= t('View','Voir') ?> →</a>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>
</div>

<?php cd_account_layout_end(); ?>
<?php cd_render_foot(); ?>
