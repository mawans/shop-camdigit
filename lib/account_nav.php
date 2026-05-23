<?php
/**
 * CamDigit — Account sidebar
 * ─────────────────────────────────────────────────────────────────────────────
 * Renders the left-rail navigation for every logged-in /account*.php page.
 * Usage: cd_account_layout_start('services'); ... cd_account_layout_end();
 * The `$active` key matches the data-key on each nav item below.
 * Styling lives in templates/six/css/cd-design.css (.cd-acc-* classes).
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);

function cd_account_nav_items(): array
{
    return [
        ['key' => 'dashboard', 'href' => '/account.php',          'icon' => 'fa-gauge-high',     'label' => ['Dashboard','Tableau de bord']],
        ['key' => 'services',  'href' => '/account-services.php', 'icon' => 'fa-cubes',          'label' => ['My Services','Mes Services']],
        ['key' => 'domains',   'href' => '/account-domains.php',  'icon' => 'fa-globe-africa',   'label' => ['My Domains','Mes Domaines']],
        ['key' => 'invoices',  'href' => '/account-invoices.php', 'icon' => 'fa-file-invoice',   'label' => ['Invoices','Factures']],
        ['key' => 'tickets',   'href' => '/account-tickets.php',  'icon' => 'fa-life-ring',      'label' => ['Support Tickets','Tickets']],
        ['key' => 'profile',   'href' => '/account-profile.php',  'icon' => 'fa-id-card',        'label' => ['Profile','Profil']],
        ['key' => 'password',  'href' => '/account-password.php', 'icon' => 'fa-key',            'label' => ['Password','Mot de passe']],
    ];
}

/** Best-effort: fetch client display name/email for the sidebar avatar. */
function cd_account_avatar_data(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cid = cd_client_id();
    $name = ''; $email = ''; $initials = '#';
    if ($cid > 0) {
        try {
            $r = whmcs_api('GetClientsDetails', ['clientid' => $cid]);
            $c = $r['client'] ?? [];
            $first = trim((string)($c['firstname'] ?? ''));
            $last  = trim((string)($c['lastname']  ?? ''));
            $name  = trim($first . ' ' . $last);
            $email = (string)($c['email'] ?? '');
            $initials = strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: '#';
        } catch (Throwable) {}
    }
    return $cache = ['name' => $name, 'email' => $email, 'initials' => $initials];
}

function cd_account_sidebar(string $active = ''): void
{
    $items = cd_account_nav_items();
    $av = cd_account_avatar_data();
    ?>
    <aside class="cd-acc-sidebar">
        <div class="cd-acc-avatar">
            <div class="cd-acc-avatar-img"><?= htmlspecialchars($av['initials']) ?></div>
            <div>
                <div class="cd-acc-avatar-name"><?= htmlspecialchars($av['name'] ?: t('My Account','Mon Compte')) ?></div>
                <div class="cd-acc-avatar-mail"><?= htmlspecialchars($av['email']) ?></div>
            </div>
        </div>
        <nav class="cd-acc-nav">
            <?php foreach ($items as $it):
                $is = $active === $it['key']; ?>
                <a class="<?= $is ? 'active' : '' ?>" href="<?= SITE_URL . htmlspecialchars($it['href']) ?>">
                    <i class="fa <?= htmlspecialchars($it['icon']) ?>"></i>
                    <span><?= t($it['label'][0], $it['label'][1]) ?></span>
                </a>
            <?php endforeach ?>
            <div class="cd-acc-nav-divider"></div>
            <a href="<?= SITE_URL ?>/logout.php" style="color:var(--err)">
                <i class="fa fa-arrow-right-from-bracket"></i>
                <span><?= t('Sign out','Déconnexion') ?></span>
            </a>
        </nav>
    </aside>
    <?php
}

/** Open the two-column layout (sidebar + main). Must close with cd_account_layout_end(). */
function cd_account_layout_start(string $active = ''): void
{
    ?>
    <div class="cd-acc-grid cd-fade-in">
    <?php cd_account_sidebar($active); ?>
    <div class="cd-acc-content cd-acc-main">
    <?php
}

function cd_account_layout_end(): void
{
    echo '</div></div>';
}
