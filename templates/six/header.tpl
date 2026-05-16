<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="{$charset}" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{if $kbarticle.title}{$kbarticle.title} - {/if}{$pagetitle} - {$companyname}</title>
    {include file="$template/includes/head.tpl"}
    {$headoutput}
</head>
<body data-phone-cc-input="{$phoneNumberInputStyle}">
{if $captcha}{$captcha->getMarkup()}{/if}
{$headeroutput}

<!-- Offcanvas Area Start (CAMDigit mobile sidebar) -->
<div class="fix-area">
    <div class="offcanvas__info">
        <div class="offcanvas__wrapper">
            <div class="offcanvas__content">
                <div class="offcanvas__top mb-5 d-flex justify-content-between align-items-center">
                    <div class="offcanvas__logo">
                        <a href="{$WEB_ROOT}/index.php">
                            <img src="{$WEB_ROOT}/templates/{$template}/img/logo/logo-camdigit-small-small.png" alt="{$companyname}" width="128px" />
                        </a>
                    </div>
                    <div class="offcanvas__close">
                        <button><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <p class="text d-none d-xl-block">{if $language == 'french'}Votre partenaire de confiance pour des solutions d'hébergement web fiables.{else}Your trusted partner for reliable web hosting solutions.{/if}</p>
                <div class="mobile-menu fix mb-3"></div>
                <div class="offcanvas__contact">
                    <h4>{if $language == 'french'}Coordonnées{else}Contact Info{/if}</h4>
                    <ul>
                        <li class="d-flex align-items-center">
                            <div class="offcanvas__contact-icon"><i class="fal fa-map-marker-alt"></i></div>
                            <div class="offcanvas__contact-text"><a href="#">Yaound&eacute;, Cameroun</a></div>
                        </li>
                        <li class="d-flex align-items-center">
                            <div class="offcanvas__contact-icon mr-15"><i class="fal fa-envelope"></i></div>
                            <div class="offcanvas__contact-text"><a href="mailto:contact@camdigit.com">contact@camdigit.com</a></div>
                        </li>
                        <li class="d-flex align-items-center">
                            <div class="offcanvas__contact-icon mr-15"><i class="fal fa-clock"></i></div>
                            <div class="offcanvas__contact-text"><a href="#">{if $language == 'french'}Lun-Ven, 09h - 17h{else}Mon-Friday, 09am - 05pm{/if}</a></div>
                        </li>
                        <li class="d-flex align-items-center">
                            <div class="offcanvas__contact-icon mr-15"><i class="far fa-phone"></i></div>
                            <div class="offcanvas__contact-text"><a href="tel:+237696770074">(+237) 696 77 00 74</a></div>
                        </li>
                    </ul>
                    <div class="header-button mt-4">
                        <a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting" class="theme-btn text-center">
                            {$LANG.orderhosting} <i class="fa-solid fa-arrow-right-long"></i>
                        </a>
                    </div>
                    <div class="social-icon d-flex align-items-center">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="offcanvas__overlay"></div>

<!-- Header Section Start (exact CAMDigit layout) -->
<header class="header-section-1">
    <div class="header-top">
        <div class="container">
            <div class="header-top-wrapper">
                <ul class="contact-list">
                    <li>
                        <i class="far fa-envelope"></i>
                        <a href="mailto:contact@camdigit.com">contact@camdigit.com</a>
                    </li>
                    <li>
                        <i class="fa-regular fa-phone"></i>
                        <a href="tel:+237696770074">(+237) 696 77 00 74</a>
                    </li>
                </ul>
                <p>{if $language == 'french'}CAMDigit Hosting : À partir de <b>3,49 $/mois</b> pour une durée limitée{else}CAMDigit Hosting: Starting at <b>$3.49/mo</b> for a Limited time{/if}</p>
                <ul class="list">
                    {if $languagechangeenabled && count($locales) > 1}
                        <li class="language-switch">
                            <button type="button" class="language-toggle" id="languageToggle" aria-haspopup="true" aria-expanded="false">
                                <i class="fa-light fa-globe"></i>
                                <span class="language-label">{$activeLocale.localisedName}</span>
                                <i class="fa-solid fa-angle-down chevron"></i>
                            </button>
                            <ul class="language-dropdown" id="languageDropdown" role="menu">
                                {foreach $locales as $locale}
                                    {if $locale.language == 'english' || $locale.language == 'french'}
                                    <li class="{if $locale.language == $activeLocale.language}active{/if}" role="none">
                                        <a role="menuitem" href="{$currentpagelinkback}language={$locale.language}">
                                            {$locale.localisedName}
                                        </a>
                                    </li>
                                    {/if}
                                {/foreach}
                            </ul>
                        </li>
                    {/if}
                    <li>
                        <i class="fa-light fa-comments"></i>
                        <a href="{$WEB_ROOT}/submitticket.php">{$LANG.getsupport}</a>
                    </li>
                    {if $loggedin}
                        <li>
                            <i class="fa-light fa-user"></i>
                            <a href="#" data-bs-toggle="popover" id="accountNotifications" data-placement="bottom">
                                {$LANG.notifications}
                                {if count($clientAlerts) > 0}
                                    <span class="badge bg-danger">{lang key='notificationsnew'}</span>
                                {/if}
                            </a>
                            <div id="accountNotificationsContent" class="hidden">
                                <ul class="client-alerts">
                                {foreach $clientAlerts as $alert}
                                    <li>
                                        <a href="{$alert->getLink()}">
                                            <i class="fas fa-fw fa-{if $alert->getSeverity() == 'danger'}exclamation-circle{elseif $alert->getSeverity() == 'warning'}exclamation-triangle{elseif $alert->getSeverity() == 'info'}info-circle{else}check-circle{/if}"></i>
                                            <div class="message">{$alert->getMessage()}</div>
                                        </a>
                                    </li>
                                {foreachelse}
                                    <li class="none">{$LANG.notificationsnone}</li>
                                {/foreach}
                                </ul>
                            </div>
                        </li>
                        <li>
                            <i class="fa-light fa-sign-out-alt"></i>
                            <a href="{$WEB_ROOT}/logout.php">{$LANG.clientareanavlogout}</a>
                        </li>
                    {else}
                        <li>
                            <i class="fa-light fa-user"></i>
                            <a href="{$WEB_ROOT}/clientarea.php">{$LANG.login}</a>
                        </li>
                        {if $condlinks.allowClientRegistration}
                            <li>
                                <a href="{$WEB_ROOT}/register.php">{$LANG.register}</a>
                            </li>
                        {/if}
                    {/if}
                    {if $adminMasqueradingAsClient || $adminLoggedIn}
                        <li>
                            <a href="{$WEB_ROOT}/logout.php?returntoadmin=1" data-bs-toggle="tooltip" data-placement="bottom" title="{if $adminMasqueradingAsClient}{$LANG.adminmasqueradingasclient} {$LANG.logoutandreturntoadminarea}{else}{$LANG.adminloggedin} {$LANG.returntoadminarea}{/if}">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </li>
                    {/if}
                </ul>
            </div>
        </div>
    </div>
    <div id="header-sticky" class="header-1">
        <div class="container">
            <div class="mega-menu-wrapper">
                <div class="header-main">
                    <div class="header-left">
                        <div class="logo">
                            <a href="{$WEB_ROOT}/index.php" class="header-logo">
                                {if $assetLogoPath}
                                    <img src="{$assetLogoPath}" alt="{$companyname}" width="128px" />
                                {else}
                                    <img src="{$WEB_ROOT}/templates/{$template}/img/logo/logo-camdigit-small-small.png" alt="{$companyname}" width="128px" />
                                {/if}
                            </a>
                        </div>
                    </div>
                    <div class="header-right d-flex justify-content-end align-items-center">
                        <div class="mean__menu-wrapper">
                            <div class="main-menu">
                                <nav id="mobile-menu">
                                    <ul>
                                        {include file="$template/includes/navbar.tpl" navbar=$primaryNavbar}
                                        {include file="$template/includes/navbar.tpl" navbar=$secondaryNavbar}
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <a href="#0" class="search-trigger search-icon"><i class="fal fa-search"></i></a>
                        <div class="header__hamburger d-xl-block my-auto">
                            <div class="sidebar__toggle">
                                <i class="fas fa-bars"></i>
                            </div>
                        </div>
                        <div class="header-button">
                            <a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting" class="theme-btn">
                                {$LANG.orderhosting} <i class="fa-solid fa-arrow-right-long"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Search Area Start (exact CAMDigit) -->
<div class="search-wrap">
    <div class="search-inner">
        <i class="fas fa-times search-close" id="search-close"></i>
        <div class="search-cell">
            <form method="get" action="{$WEB_ROOT}/knowledgebase.php">
                <div class="search-field-holder">
                    <input type="search" name="search" class="main-search-input" placeholder="{$LANG.search}..." />
                </div>
            </form>
        </div>
    </div>
</div>

{if $templatefile == 'homepage'}
<!-- Hero Section Start (exact CAMDigit layout) -->
<section class="hero-section hero-1 bg-cover fix" style="background-image: url('{$WEB_ROOT}/templates/{$template}/img/hero/hero-bg-1.webp');">
    <div class="circle-shape-left">
        <img src="{$WEB_ROOT}/templates/{$template}/img/hero/hero-1-circle-left.png" alt="shape-img" />
    </div>
    <div class="circle-shape-right">
        <img src="{$WEB_ROOT}/templates/{$template}/img/hero/hero-1-circle-right.png" alt="shape-img" />
    </div>
    <div class="dot-left">
        <img src="{$WEB_ROOT}/templates/{$template}/img/hero/hero-1-dot-left.png" alt="img" />
    </div>
    <div class="dot-right">
        <img src="{$WEB_ROOT}/templates/{$template}/img/hero/hero-1-dot-right.png" alt="img" />
    </div>
    <div class="hero-social">
        <span>Follow on</span>
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
        <a href="#"><i class="fa-brands fa-youtube"></i></a>
    </div>
    <div class="container">
        <div class="row g-4 justify-content-between">
            <div class="col-lg-6">
                <div class="hero-content">
                    <span class="sub-text wow fadeInUp">
                        <img src="{$WEB_ROOT}/templates/{$template}/img/hero/activity.png" alt="img" class="me-2" />
                        {if $language == 'french'}Tout ce dont vous avez besoin pour créer un site web{else}Everything You Need to Create a Website{/if}
                    </span>
                    <h1 class="wow fadeInUp" data-wow-delay=".3s">
                        {if $language == 'french'}Passez à l'hébergement Cloud le plus rapide aujourd'hui{else}Upgrade To Fastest Cloud Hosting Today{/if}
                    </h1>
                    <h6 class="wow fadeInUp" data-wow-delay=".5s">
                        {if $language == 'french'}Stockage illimité, bande passante illimitée, hébergement imbattable.<br/>On s'occupe de tout.{else}Unlimited storage, unmetered bandwidth, unbeatable hosting.<br/>This gator's got ya covered.{/if}
                    </h6>
                    <div class="hero-author">
                        <a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting" class="theme-btn bg-color-2 wow fadeInUp" data-wow-delay=".7s">
                            {$LANG.orderhosting} <i class="fas fa-long-arrow-alt-right"></i>
                        </a>
                        <div class="author-content wow fadeInUp" data-wow-delay=".9s">
                            <div class="content">
                                <div class="star">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="far fa-star"></i>
                                </div>
                                <p class="text-white">{if $language == 'french'}450+ avis{else}450+ reviews{/if}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 wow fadeInUp" data-wow-delay=".4s">
                <div class="hero-image">
                    <img style="border-radius: 16px" src="{$WEB_ROOT}/templates/{$template}/img/hero/hero-banner.png" alt="img" width="720px" />
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Domain Name Section Start (exact CAMDigit + WHMCS domain search logic) -->
<section class="doming-name-area section-padding pt-0">
    <div class="container">
        <div class="doming-name-wrapper">
            <h3 class="text-white wow fadeInUp" data-wow-delay=".3s">
                {if $registerdomainenabled || $transferdomainenabled}{if $language == 'french'}Trouvez votre nom de domaine idéal{else}Find Your Perfect Domain Name{/if}{else}{$LANG.howcanwehelp}{/if}
            </h3>
            {if $registerdomainenabled || $transferdomainenabled}
            <form action="domainchecker.php" method="post" id="frmDomainHomepage" class="doming-input-form wow fadeInUp" data-wow-delay=".5s">
                <input type="hidden" name="transfer" />
                <div class="doming-input">
                    <input type="text" name="domain" placeholder="{$LANG.exampledomain}" autocapitalize="none" data-bs-toggle="tooltip" data-placement="left" data-trigger="manual" title="{lang key='orderForm.required'}" />
                    {if $registerdomainenabled}
                        <button class="theme-btn bg-color-2" type="submit" id="btnDomainSearch">{$LANG.search}</button>
                    {/if}
                </div>
                {include file="$template/includes/captcha.tpl"}
            </form>
            <script>
            (function() {
                var form = document.getElementById('frmDomainHomepage');
                if (!form) return;
                form.addEventListener('submit', function(e) {
                    var input = form.querySelector('input[name="domain"]');
                    if (!input) return;
                    var val = input.value.trim();
                    if (/\.cm$/i.test(val)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        var sld = val.replace(/\.cm$/i, '').toLowerCase();
                        if (sld) {
                            window.location.href = '{$WEB_ROOT}/order-cm.php?sld=' + encodeURIComponent(sld);
                        }
                    }
                }, true);
            })();
            </script>
            {/if}
            <ul class="doming-list">
                <li class="wow fadeInUp" data-wow-delay=".2s"><span>.com</span> $9.95</li>
                <li class="wow fadeInUp" data-wow-delay=".4s"><span>.cm</span> $9.99</li>
                <li class="wow fadeInUp" data-wow-delay=".6s"><span>.Info</span> $11.99</li>
                <li class="wow fadeInUp" data-wow-delay=".8s"><span>.Net</span> $8.95</li>
                <li class="wow fadeInUp" data-wow-delay=".10s"><span>.Store</span> $10.50</li>
                <li class="wow fadeInUp" data-wow-delay=".12s"><span>.ORG</span> $11.95</li>
            </ul>
        </div>
    </div>
</section>
{/if}

{include file="$template/includes/validateuser.tpl"}
{include file="$template/includes/verifyemail.tpl"}

{if $templatefile != 'homepage'}
<!-- Breadcrumb Section for inner pages (exact CAMDigit style) -->
<div class="breadcrumb-wrapper bg-cover" style="background-image: url('{$WEB_ROOT}/templates/{$template}/img/breadcrumb-1.jpg');">
    <div class="container">
        <div class="page-heading">
            <h1 class="wow fadeInUp" data-wow-delay=".3s">{$displayTitle}</h1>
            {if $tagline}<p class="wow fadeInUp" data-wow-delay=".5s">{$tagline}</p>{/if}
            <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".3s">
                <li><a href="{$WEB_ROOT}/index.php">{if $language == 'french'}Accueil{else}Home{/if}</a></li>
                {foreach $breadcrumb as $item}
                    {if $item@last}
                        <li><span>{$item.label}</span></li>
                    {else}
                        <li><a href="{$item.link}">{$item.label}</a></li>
                    {/if}
                {/foreach}
            </ul>
        </div>
    </div>
</div>
{/if}

<section id="main-body" class="section-padding">
    <div class="container{if $skipMainBodyContainer}-fluid without-padding{/if}">
        <div class="row">

        {if !$inShoppingCart && ($primarySidebar->hasChildren() || $secondarySidebar->hasChildren())}
            <div class="col-lg-3 col-md-4 sidebar">
                {include file="$template/includes/sidebar.tpl" sidebar=$primarySidebar}
            </div>
        {/if}
        <!-- Container for main page display content -->
        <div class="{if !$inShoppingCart && ($primarySidebar->hasChildren() || $secondarySidebar->hasChildren())}col-lg-9 col-md-8{else}col-12{/if} main-content">
