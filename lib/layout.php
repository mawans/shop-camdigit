<?php
/**
 * CamDigit Main — shared layout helpers
 * ─────────────────────────────────────────────────────────────────────────────
 * Every custom front-end page uses these. No dependency on the WHMCS "six"
 * theme. Loads /assets/cdm.css + /assets/cdm.js only.
 *
 * Usage:
 *   require_once __DIR__ . '/lib/whmcs.php';
 *   require_once __DIR__ . '/lib/layout.php';
 *
 *   cdm_head([
 *       'title'        => 'Your cart',
 *       'description'  => 'Review your order before checkout',
 *       'active'       => 'cart',          // highlights nav item
 *       'hero_title'   => 'Your <span class="accent">cart</span>',
 *       'hero_subtitle'=> 'Review your selection and complete your order.',
 *       'breadcrumb'   => 'Cart',           // optional, just a label
 *   ]);
 *
 *   // ... page content ...
 *
 *   cdm_foot();
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);

const CDM_ASSET_VERSION = '2026052301';

function cdm_head(array $opts = []): void
{
    $title       = (string)($opts['title']       ?? COMPANY_NAME);
    $description = (string)($opts['description'] ?? 'Domains, hosting and online tools for African brands.');
    $active      = (string)($opts['active']      ?? '');
    $heroTitle   = $opts['hero_title']    ?? null;
    $heroSub     = $opts['hero_subtitle'] ?? null;
    $breadcrumb  = (string)($opts['breadcrumb']   ?? '');
    $lang        = function_exists('cd_lang') ? cd_lang() : 'english';
    $cartCount   = function_exists('cd_cart_count') ? cd_cart_count() : 0;
    $loggedIn    = function_exists('cd_client_id') && cd_client_id() > 0;
    $v           = CDM_ASSET_VERSION;
?>
<!doctype html>
<html lang="<?= $lang === 'french' ? 'fr' : 'en' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES) ?>">
<title><?= htmlspecialchars($title, ENT_QUOTES) ?> — <?= COMPANY_NAME ?></title>

<link rel="icon" type="image/png" href="<?= LOGO_URL ?>">
<link rel="apple-touch-icon" href="<?= LOGO_URL ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/cdm.css?v=<?= $v ?>">

<script>
window.CDM_CONFIG = {
    site:    <?= json_encode(SITE_URL) ?>,
    lang:    <?= json_encode($lang) ?>,
    cartApi: <?= json_encode(SITE_URL . '/cart-api.php') ?>,
    csrf:    <?= json_encode(cd_csrf()) ?>
};
</script>
<script src="<?= SITE_URL ?>/assets/cdm.js?v=<?= $v ?>" defer></script>
</head>
<body>

<!-- ── Topbar ────────────────────────────────────────────── -->
<header class="cdm-topbar">
    <div class="cdm-topbar-bar">
        <div class="cdm-container">
            <ul>
                <li><i class="fa-regular fa-envelope"></i><a href="mailto:contact@camdigit.com">contact@camdigit.com</a></li>
                <li><i class="fa-solid fa-phone"></i><a href="tel:+237696770074">(+237) 696 77 00 74</a></li>
            </ul>
            <p style="margin:0"><?= t('CAMDigit Hosting: Starting at <b>$3.49/mo</b>', 'CAMDigit Hébergement : À partir de <b>3,49 $/mois</b>') ?></p>
            <ul>
                <li><a href="?language=english" <?= $lang !== 'french' ? 'style="color:'.($lang==='english'?'#ffa31a':'inherit').'"' : '' ?>>EN</a></li>
                <li><a href="?language=french"  <?= $lang === 'french' ? 'style="color:#ffa31a"' : '' ?>>FR</a></li>
            </ul>
        </div>
    </div>
    <div class="cdm-topbar-main">
        <div class="cdm-container">
            <a class="cdm-logo" href="<?= SITE_URL ?>"><img src="<?= LOGO_URL ?>" alt="<?= COMPANY_NAME ?>"></a>
            <nav class="cdm-nav" id="cdmNav">
                <a href="<?= SITE_URL ?>/"               class="<?= $active === 'home'     ? 'active' : '' ?>"><?= t('Home','Accueil') ?></a>
                <a href="<?= SITE_URL ?>/order-domain.php" class="<?= $active === 'domains'  ? 'active' : '' ?>"><?= t('Domains','Domaines') ?></a>
                <a href="<?= SITE_URL ?>/order-hosting.php" class="<?= $active === 'hosting' ? 'active' : '' ?>"><?= t('Hosting','Hébergement') ?></a>
                <a href="<?= SITE_URL ?>/knowledgebase.php" class="<?= $active === 'kb'      ? 'active' : '' ?>"><?= t('Knowledge Base','Base de connaissances') ?></a>
                <a href="<?= SITE_URL ?>/contact.php"     class="<?= $active === 'contact'  ? 'active' : '' ?>"><?= t('Contact','Contact') ?></a>
            </nav>
            <div class="cdm-nav-actions">
                <a class="cdm-cart-link <?= $cartCount > 0 ? 'has-items' : '' ?>" href="<?= SITE_URL ?>/cart.php" aria-label="<?= t('Cart','Panier') ?>">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($cartCount > 0): ?><span class="cdm-cart-badge"><?= $cartCount ?></span><?php endif ?>
                </a>
                <?php if ($loggedIn): ?>
                    <a href="<?= SITE_URL ?>/account.php" class="cdm-btn cdm-btn-dark cdm-btn-sm">
                        <i class="fa-solid fa-user"></i> <?= t('Account','Compte') ?>
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/login.php" class="cdm-btn cdm-btn-accent cdm-btn-sm">
                        <?= t('Log in','Connexion') ?> <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php endif ?>
                <button class="cdm-burger" aria-label="menu"><i class="fa-solid fa-bars"></i></button>
            </div>
        </div>
    </div>
</header>

<?php if ($heroTitle !== null): ?>
<!-- ── Hero ──────────────────────────────────────────────── -->
<section class="cdm-hero">
    <div class="cdm-hero-inner cdm-container">
        <h1 class="cdm-fade-up"><?= $heroTitle /* caller HTML allowed */ ?></h1>
        <?php if ($heroSub !== null): ?>
            <p class="cdm-fade-up" style="animation-delay:.05s"><?= $heroSub ?></p>
        <?php endif ?>
        <?php if ($breadcrumb !== ''): ?>
            <nav class="cdm-breadcrumb">
                <a href="<?= SITE_URL ?>/"><?= t('Home','Accueil') ?></a>
                <i class="fa-solid fa-chevron-right"></i>
                <span><?= htmlspecialchars($breadcrumb) ?></span>
            </nav>
        <?php endif ?>
    </div>
</section>
<?php endif ?>

<main class="cdm-main">
<div class="cdm-container">
<?php
}

function cdm_foot(): void
{
    $lang = function_exists('cd_lang') ? cd_lang() : 'english';
?>
</div>
</main>

<!-- ── Footer ────────────────────────────────────────────── -->
<footer class="cdm-footer">
    <div class="cdm-container">
        <div class="cdm-footer-grid">
            <div class="cdm-footer-col cdm-footer-brand">
                <img src="<?= LOGO_URL ?>" alt="<?= COMPANY_NAME ?>">
                <p><?= t('Your trusted partner for reliable web hosting solutions. Speed, security, and 24/7 support.',
                         'Votre partenaire de confiance pour des solutions d\'hébergement web fiables. Vitesse, sécurité et support 24/7.') ?></p>
                <div class="cdm-footer-contact">
                    <span><i class="fa-solid fa-phone"></i> (+237) 696 77 00 74</span>
                    <span><i class="fa-solid fa-envelope"></i> contact@camdigit.com</span>
                    <span><i class="fa-solid fa-location-dot"></i> Yaoundé, Cameroun</span>
                </div>
            </div>
            <div class="cdm-footer-col">
                <h4><?= t('Company','Société') ?></h4>
                <ul>
                    <li><a href="<?= SITE_URL ?>/"><i class="fa-solid fa-chevron-right"></i><?= t('Home','Accueil') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/contact.php"><i class="fa-solid fa-chevron-right"></i><?= t('Contact','Contact') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/knowledgebase.php"><i class="fa-solid fa-chevron-right"></i><?= t('Knowledge Base','Base de connaissances') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/privacy.php"><i class="fa-solid fa-chevron-right"></i><?= t('Privacy','Confidentialité') ?></a></li>
                </ul>
            </div>
            <div class="cdm-footer-col">
                <h4><?= t('Hosting','Hébergement') ?></h4>
                <ul>
                    <li><a href="<?= SITE_URL ?>/order-hosting.php"><i class="fa-solid fa-chevron-right"></i><?= t('Shared Hosting','Hébergement partagé') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/order-hosting.php"><i class="fa-solid fa-chevron-right"></i>VPS</a></li>
                    <li><a href="<?= SITE_URL ?>/order-hosting.php"><i class="fa-solid fa-chevron-right"></i><?= t('Cloud Hosting','Cloud') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/order-domain.php"><i class="fa-solid fa-chevron-right"></i><?= t('Domains','Domaines') ?></a></li>
                </ul>
            </div>
            <div class="cdm-footer-col">
                <h4><?= t('Support','Support') ?></h4>
                <ul>
                    <li><a href="<?= SITE_URL ?>/submitticket.php"><i class="fa-solid fa-chevron-right"></i><?= t('Submit Ticket','Ouvrir un ticket') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/knowledgebase.php"><i class="fa-solid fa-chevron-right"></i><?= t('Knowledge Base','Base de connaissances') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/account.php"><i class="fa-solid fa-chevron-right"></i><?= t('My Account','Mon Compte') ?></a></li>
                </ul>
            </div>
            <div class="cdm-footer-col">
                <h4><?= t('Newsletter','Newsletter') ?></h4>
                <p style="font-size:14px;line-height:1.65;color:#a8b0c5"><?= t('Get the latest offers and product news.','Recevez nos offres et nouveautés.') ?></p>
                <form class="cdm-newsletter" onsubmit="event.preventDefault();this.querySelector('button').innerHTML='<i class=\'fa-solid fa-check\'></i>'">
                    <input type="email" placeholder="<?= t('Your email','Votre email') ?>" required>
                    <button type="submit" aria-label="subscribe"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
                <div class="cdm-socials">
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>
        </div>
    </div>
    <div class="cdm-footer-bottom">
        <div class="cdm-container">
            <span>© <?= date('Y') ?> <?= COMPANY_NAME ?>. <?= t('All rights reserved.','Tous droits réservés.') ?></span>
            <span><?= t('Built in','Conçu à') ?> Yaoundé, Cameroun</span>
        </div>
    </div>
</footer>

</body>
</html>
<?php
}

/** Render the account sidebar (used on logged-in account pages). */
function cdm_account_sidebar(string $active = ''): void
{
    $cid = cd_client_id();
    $name = ''; $email = ''; $initials = '?';
    if ($cid > 0) {
        try {
            $r = whmcs_api('GetClientsDetails', ['clientid' => $cid]);
            $c = $r['client'] ?? [];
            $first = trim((string)($c['firstname'] ?? ''));
            $last  = trim((string)($c['lastname']  ?? ''));
            $name  = trim($first . ' ' . $last) ?: t('Account','Compte');
            $email = (string)($c['email'] ?? '');
            $initials = strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: '#';
        } catch (Throwable) {}
    }
    $nav = [
        ['key' => 'dashboard', 'href' => 'account.php',          'icon' => 'fa-gauge-high',    'lbl' => ['Dashboard','Tableau de bord']],
        ['key' => 'services',  'href' => 'account-services.php', 'icon' => 'fa-cubes',         'lbl' => ['My Services','Mes Services']],
        ['key' => 'domains',   'href' => 'account-domains.php',  'icon' => 'fa-globe',         'lbl' => ['My Domains','Mes Domaines']],
        ['key' => 'invoices',  'href' => 'account-invoices.php', 'icon' => 'fa-file-invoice',  'lbl' => ['Invoices','Factures']],
        ['key' => 'tickets',   'href' => 'account-tickets.php',  'icon' => 'fa-life-ring',     'lbl' => ['Support','Support']],
        ['key' => 'profile',   'href' => 'account-profile.php',  'icon' => 'fa-id-card',       'lbl' => ['Profile','Profil']],
        ['key' => 'password',  'href' => 'account-password.php', 'icon' => 'fa-key',           'lbl' => ['Password','Mot de passe']],
    ];
    ?>
    <aside class="cdm-sidebar">
        <div class="cdm-sidebar-user">
            <div class="cdm-avatar"><?= htmlspecialchars($initials) ?></div>
            <div>
                <div class="cdm-sidebar-user-name"><?= htmlspecialchars($name) ?></div>
                <div class="cdm-sidebar-user-mail"><?= htmlspecialchars($email) ?></div>
            </div>
        </div>
        <nav class="cdm-sidebar-nav">
            <?php foreach ($nav as $it): ?>
                <a href="<?= SITE_URL ?>/<?= $it['href'] ?>" class="<?= $active === $it['key'] ? 'active' : '' ?>">
                    <i class="fa-solid <?= $it['icon'] ?>"></i>
                    <span><?= t($it['lbl'][0], $it['lbl'][1]) ?></span>
                </a>
            <?php endforeach ?>
            <div class="cdm-sidebar-nav-sep"></div>
            <a href="<?= SITE_URL ?>/logout.php" class="logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span><?= t('Sign out','Déconnexion') ?></span>
            </a>
        </nav>
    </aside>
    <?php
}
