<?php
/**
 * CamDigit Main — Domain Registration Landing
 * Uses lib/layout.php (cdm_head/cdm_foot) — no theme dependency.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/cart.php';
require_once __DIR__ . '/lib/layout.php';

$initialSld = cd_sanitize_sld($_GET['sld'] ?? '');
$initialTld = strtolower(preg_replace('/[^a-z0-9.]/', '', (string)($_GET['tld'] ?? '.cm')) ?? '.cm');
if ($initialTld === '' || $initialTld[0] !== '.') $initialTld = '.' . $initialTld;

// ── Featured TLDs ───────────────────────────────────────────────────────────
$tlds = [
    '.cm'     => ['icon' => 'fa-flag',          'tag' => ['For Cameroon',   'Pour le Cameroun'],   'g' => ['#10b981','#059669']],
    '.com'    => ['icon' => 'fa-globe',         'tag' => ['Most popular',   'Le plus populaire'],  'g' => ['#3b82f6','#1d4ed8']],
    '.africa' => ['icon' => 'fa-earth-africa',  'tag' => ['Pan-African',    'Pan-africain'],       'g' => ['#f59e0b','#d97706']],
    '.io'     => ['icon' => 'fa-code',          'tag' => ['Tech & startups','Tech & startups'],    'g' => ['#8b5cf6','#6d28d9']],
    '.net'    => ['icon' => 'fa-network-wired', 'tag' => ['Networks',       'Réseaux'],            'g' => ['#06b6d4','#0e7490']],
    '.org'    => ['icon' => 'fa-handshake',     'tag' => ['Non-profits',    'Associations'],       'g' => ['#ec4899','#be185d']],
    '.info'   => ['icon' => 'fa-circle-info',   'tag' => ['Informational',  'Informatif'],         'g' => ['#14b8a6','#0f766e']],
    '.biz'    => ['icon' => 'fa-briefcase',     'tag' => ['Business',       'Affaires'],           'g' => ['#f97316','#c2410c']],
];

// ── Live prices from WHMCS GetTLDPricing ────────────────────────────────────
// Response shape (WHMCS 8.x): pricing[<tld>][register][<years>] = price_string
$prices = [];
$currCode = 'USD'; $currPrefix = '$'; $currSuffix = '';
try {
    $r = whmcs_api('GetTLDPricing', []);
    $c = $r['currency'] ?? null;
    if (is_array($c)) {
        $currCode   = (string)($c['code']   ?? 'USD');
        $currPrefix = (string)($c['prefix'] ?? '');
        $currSuffix = (string)($c['suffix'] ?? '');
    }
    foreach (array_keys($tlds) as $tld) {
        $reg = $r['pricing'][ltrim($tld, '.')]['register'] ?? null;
        if (!is_array($reg) || empty($reg)) continue;
        // Find the smallest registration period with a numeric price > 0
        $price = null;
        foreach ($reg as $period => $val) {
            if (is_numeric($val) && (float)$val > 0) { $price = (float)$val; break; }
        }
        if ($price !== null) $prices[$tld] = $price;
    }
} catch (Throwable) {}

function od_money(?float $amt, string $code, string $prefix = '', string $suffix = ''): string
{
    if ($amt === null) return '';
    $n = number_format($amt, 2, ',', ' ');
    if ($prefix !== '' || $suffix !== '') return trim($prefix . $n . ($suffix ? ' ' . $suffix : ''));
    return match (strtoupper($code)) {
        'EUR' => $n . ' €', 'USD' => '$' . $n, 'GBP' => '£' . $n, 'XAF' => $n . ' FCFA',
        default => $code . ' ' . $n,
    };
}

cdm_head([
    'title'         => t('Find your perfect domain','Trouvez votre domaine idéal'),
    'active'        => 'domains',
    'hero_title'    => t('Find the <span class="accent">domain</span> that\'s yours',
                         'Trouvez le <span class="accent">domaine</span> qui vous ressemble'),
    'hero_subtitle' => t('Search 200+ extensions in real time. Lock yours in seconds — no hidden fees. Privacy and SSL included.',
                         'Recherchez 200+ extensions en temps réel. Réservez en quelques secondes — sans frais cachés.'),
    'breadcrumb'    => t('Domains','Domaines'),
]);
?>

<style>
/* Page-scoped tweaks — everything else comes from /assets/cdm.css */
.od-hero-search { max-width: 760px; margin: -40px auto 0; background: #fff; border-radius: 18px; padding: 8px; box-shadow: 0 30px 80px rgba(0,0,0,.18); display: flex; gap: 6px; align-items: center; position: relative; z-index: 5; flex-wrap: wrap; }
.od-hero-search .icon { padding: 0 6px 0 18px; color: var(--ink-mute); font-size: 16px; flex-shrink: 0; }
.od-hero-search input { flex: 1; min-width: 200px; border: 0; outline: 0; padding: 16px 8px; font-size: 16px; font-family: inherit; color: var(--ink); background: transparent; }
.od-hero-search select { border: 0; outline: 0; background: var(--bg); padding: 12px 18px; border-radius: 99px; font-weight: 700; font-size: 14px; cursor: pointer; color: var(--ink); font-family: inherit; }
.od-hero-search button { padding: 14px 30px; border-radius: 99px; border: 0; cursor: pointer; background: linear-gradient(135deg, var(--theme2), #f97316); color: #fff; font-weight: 700; font-size: 15px; font-family: inherit; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 8px 20px rgba(255,163,26,.4); transition: transform .15s, box-shadow .15s; }
.od-hero-search button:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(255,163,26,.5); }
.od-hero-search button:disabled { opacity: .7; cursor: wait; }

.od-result { max-width: 760px; margin: 18px auto 0; padding: 18px 22px; border-radius: 14px; background: #fff; box-shadow: 0 20px 50px rgba(0,0,0,.12); position: relative; z-index: 5; animation: odSlide .25s ease-out; }
.od-result[hidden] { display: none; }
@keyframes odSlide { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
.od-result-primary { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; padding: 12px 16px; border-radius: 10px; }
.od-result-primary.available { background: #ecfdf5; border: 1px solid #a7f3d0; }
.od-result-primary.taken { background: #fef2f2; border: 1px solid #fecaca; }
.od-result-name { font-weight: 700; font-size: 18px; }
.od-result-name .ext { color: var(--theme); }
.od-result-primary.taken .od-result-name .ext { color: #b91c1c; }
.od-result-status { font-size: 13px; font-weight: 600; }
.od-result-primary.available .od-result-status { color: var(--ok); }
.od-result-primary.taken .od-result-status { color: var(--err); }

.od-sugs { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line-soft); }
.od-sugs h5 { margin: 0 0 10px; font-size: 12px; font-weight: 700; color: var(--ink-mute); text-transform: uppercase; letter-spacing: .05em; }
.od-sug-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
.od-sug { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--line); background: #fafafa; transition: all .15s; }
.od-sug:hover { border-color: var(--theme); background: #f0fdf4; }
.od-sug.taken { opacity: .55; }
.od-sug .name { font-weight: 600; font-size: 14px; }
.od-sug .name .ext { color: var(--theme); }
.od-sug-add { border: 0; padding: 6px 10px; border-radius: 6px; background: var(--theme); color: #fff; font-size: 11px; font-weight: 700; cursor: pointer; font-family: inherit; }
.od-sug-add:hover { background: var(--theme2); }
.od-sug-add.in-cart { background: #6b7280; cursor: default; }

/* TLD card grid */
.od-tld-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
@media (max-width: 991px) { .od-tld-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 700px)  { .od-tld-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 420px)  { .od-tld-grid { grid-template-columns: 1fr; } }
.od-tld { --g1: var(--theme); --g2: var(--theme-dark); background: #fff; border: 1px solid var(--line-soft); border-radius: 16px; padding: 24px; position: relative; transition: transform .25s, box-shadow .25s; display: flex; flex-direction: column; }
.od-tld:hover { transform: translateY(-4px); box-shadow: 0 25px 50px -15px rgba(0,0,0,.15); }
.od-tld-icon { width: 50px; height: 50px; border-radius: 14px; background: linear-gradient(135deg, var(--g1), var(--g2)); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 14px; }
.od-tld-ext { font-size: 32px; font-weight: 800; letter-spacing: -.02em; line-height: 1; background: linear-gradient(135deg, var(--g1), var(--g2)); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 4px; }
.od-tld-tag { font-size: 13px; color: var(--ink-mute); margin-bottom: 14px; }
.od-tld-price { margin-top: auto; font-size: 22px; font-weight: 800; color: var(--ink); letter-spacing: -.02em; }
.od-tld-price small { font-size: 13px; color: var(--ink-mute); font-weight: 500; margin-left: 4px; }
.od-tld-price.tbd { font-size: 14px; color: var(--ink-mute); font-weight: 500; }
.od-tld-btn { margin-top: 12px; display: flex; justify-content: space-between; align-items: center; padding: 11px 16px; border-radius: 10px; background: var(--bg); color: var(--ink); font-weight: 600; font-size: 13px; cursor: pointer; border: 0; font-family: inherit; transition: all .15s; text-decoration: none; }
.od-tld-btn i { transition: transform .15s; }
.od-tld:hover .od-tld-btn { background: linear-gradient(135deg, var(--g1), var(--g2)); color: #fff; }
.od-tld:hover .od-tld-btn i { transform: translateX(4px); }
.od-tld-popular { position: absolute; top: 12px; right: 12px; padding: 4px 10px; border-radius: 99px; background: linear-gradient(135deg, var(--theme2), #f97316); color: #fff; font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; box-shadow: 0 4px 12px rgba(255,163,26,.4); }

/* Why us cards */
.od-why { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
@media (max-width: 991px) { .od-why { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 460px)  { .od-why { grid-template-columns: 1fr; } }
.od-why-card { background: #fff; border: 1px solid var(--line-soft); border-radius: 16px; padding: 28px 24px; transition: all .25s; }
.od-why-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -15px rgba(0,0,0,.12); }
.od-why-icon { width: 52px; height: 52px; border-radius: 14px; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 14px; }
.od-why-card:nth-child(1) .od-why-icon { background: linear-gradient(135deg, var(--theme), var(--theme-dark)); }
.od-why-card:nth-child(2) .od-why-icon { background: linear-gradient(135deg, var(--theme2), var(--theme2-dark)); }
.od-why-card:nth-child(3) .od-why-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.od-why-card:nth-child(4) .od-why-icon { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.od-why-card h3 { margin: 0 0 6px; font-size: 17px; font-weight: 700; }
.od-why-card p { margin: 0; color: var(--ink-mute); font-size: 14px; line-height: 1.55; }

/* How it works */
.od-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
@media (max-width: 767px) { .od-steps { grid-template-columns: 1fr; } }
.od-step { background: #fff; border: 1px solid var(--line-soft); border-radius: 16px; padding: 32px 24px; text-align: center; }
.od-step-num { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--theme2), #f97316); color: #fff; font-weight: 800; font-size: 19px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; box-shadow: 0 8px 20px rgba(255,163,26,.3); }
.od-step h3 { margin: 0 0 6px; font-size: 18px; }
.od-step p { margin: 0; color: var(--ink-mute); font-size: 14px; }

/* FAQ */
.od-faq { display: grid; gap: 12px; max-width: 820px; margin: 0 auto; }
.od-faq details { background: #fff; border: 1px solid var(--line-soft); border-radius: 14px; overflow: hidden; transition: all .2s; }
.od-faq details[open] { border-color: var(--theme); box-shadow: 0 10px 30px rgba(35,106,37,.08); }
.od-faq summary { padding: 20px 24px; cursor: pointer; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 14px; list-style: none; }
.od-faq summary::-webkit-details-marker { display: none; }
.od-faq summary i.q { width: 36px; height: 36px; border-radius: 10px; background: var(--theme-light); color: var(--theme); display: inline-flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.od-faq summary span { flex: 1; }
.od-faq summary::after { content: "+"; font-size: 22px; font-weight: 300; color: var(--theme); width: 28px; height: 28px; border-radius: 50%; background: var(--theme-light); display: inline-flex; align-items: center; justify-content: center; transition: transform .25s; flex-shrink: 0; }
.od-faq details[open] summary::after { transform: rotate(45deg); }
.od-faq .body { padding: 0 24px 20px 74px; color: var(--ink-soft); font-size: 14.5px; line-height: 1.65; }

/* Stats panel */
.od-stats-panel { background: linear-gradient(135deg, #0a0817, var(--navy-soft)); border-radius: 24px; padding: 44px; color: #fff; display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; position: relative; overflow: hidden; }
.od-stats-panel::before { content: ""; position: absolute; top: -120px; right: -120px; width: 380px; height: 380px; background: radial-gradient(circle, rgba(255,163,26,.3), transparent 70%); pointer-events: none; }
@media (max-width: 767px) { .od-stats-panel { grid-template-columns: repeat(2, 1fr); padding: 30px; } }
.od-stat-block { text-align: center; position: relative; z-index: 1; }
.od-stat-block .ic { width: 56px; height: 56px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; margin-bottom: 14px; }
.od-stat-block .ic.s1 { background: linear-gradient(135deg, var(--theme2), #f97316); }
.od-stat-block .ic.s2 { background: linear-gradient(135deg, #10b981, #059669); }
.od-stat-block .ic.s3 { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.od-stat-block .ic.s4 { background: linear-gradient(135deg, #ec4899, #be185d); }
.od-stat-block .n { font-size: clamp(28px, 4vw, 42px); font-weight: 800; letter-spacing: -.02em; background: linear-gradient(135deg, #ffa31a, #ffd97a); -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1; }
.od-stat-block .l { font-size: 12px; color: rgba(255,255,255,.6); margin-top: 8px; text-transform: uppercase; letter-spacing: .06em; }

/* Final CTA */
.od-final { margin-top: 60px; padding: 60px 40px; background: radial-gradient(circle at 30% 30%, rgba(255,163,26,.35), transparent 60%), radial-gradient(circle at 80% 70%, rgba(124,58,237,.25), transparent 60%), linear-gradient(135deg, #0a0817, var(--navy-soft)); border-radius: 24px; text-align: center; color: #fff; }
.od-final h2 { color: #fff; font-size: clamp(26px, 4vw, 38px); margin: 0 0 10px; }
.od-final p { color: rgba(255,255,255,.75); margin: 0 0 26px; font-size: 16px; }

.od-section { margin-top: 60px; }
.od-section-head { text-align: center; margin-bottom: 36px; max-width: 600px; margin-left: auto; margin-right: auto; }
.od-section-head .eyebrow { display: inline-block; padding: 5px 14px; border-radius: 99px; background: var(--theme-light); color: var(--theme); font-weight: 700; letter-spacing: .04em; text-transform: uppercase; font-size: 11px; margin-bottom: 12px; }
.od-section-head h2 { font-size: clamp(24px, 3.5vw, 36px); font-weight: 800; margin: 0 0 8px; letter-spacing: -.02em; }
.od-section-head p { color: var(--ink-mute); font-size: 15px; margin: 0; }
</style>

<!-- ── HERO SEARCH (pinned under the hero) ───────────────────────── -->
<form class="od-hero-search" data-od-form autocomplete="off">
    <span class="icon"><i class="fa-solid fa-magnifying-glass"></i></span>
    <input type="text" id="odSld" placeholder="<?= htmlspecialchars(t('Try yourbrand, mystartup, mycompany…','Essayez votremarque, monidée…')) ?>" value="<?= htmlspecialchars($initialSld) ?>" required>
    <select id="odTld">
        <?php foreach (array_keys($tlds) as $opt): ?>
            <option value="<?= $opt ?>"<?= $opt === $initialTld ? ' selected' : '' ?>><?= $opt ?></option>
        <?php endforeach ?>
    </select>
    <button type="submit" id="odBtn"><i class="fa-solid fa-bolt"></i> <?= t('Search','Rechercher') ?></button>
</form>

<!-- Result panel (filled by JS) -->
<div class="od-result" id="odResult" hidden>
    <div class="od-result-primary" id="odPrimary"></div>
    <div class="od-sugs" id="odSugsWrap" hidden>
        <h5><?= t('Also available','Aussi disponible') ?></h5>
        <div class="od-sug-list" id="odSugList"></div>
    </div>
</div>

<!-- ── PRICING GRID ──────────────────────────────────────────────── -->
<section class="od-section">
    <div class="od-section-head">
        <span class="eyebrow"><?= t('Transparent pricing','Tarifs transparents') ?></span>
        <h2><?= t('Pick the extension<br>that fits your brand','Choisissez l\'extension<br>qui colle à votre marque') ?></h2>
        <p><?= t('First-year registration prices, pulled live from our registry partners.','Tarifs de première année, en direct de nos registraires partenaires.') ?></p>
    </div>

    <div class="od-tld-grid">
        <?php foreach ($tlds as $tld => $meta):
            $price = $prices[$tld] ?? null;
            $popular = in_array($tld, ['.cm', '.com'], true);
            [$g1, $g2] = $meta['g'];
        ?>
        <div class="od-tld" style="--g1: <?= $g1 ?>; --g2: <?= $g2 ?>">
            <?php if ($popular): ?><span class="od-tld-popular"><?= t('Popular','Populaire') ?></span><?php endif ?>
            <div class="od-tld-icon"><i class="fa-solid <?= htmlspecialchars($meta['icon']) ?>"></i></div>
            <div class="od-tld-ext"><?= $tld ?></div>
            <div class="od-tld-tag"><?= htmlspecialchars(t($meta['tag'][0], $meta['tag'][1])) ?></div>
            <?php if ($price !== null): ?>
                <div class="od-tld-price"><?= od_money($price, $currCode, $currPrefix, $currSuffix) ?><small>/ <?= t('yr','an') ?></small></div>
            <?php else: ?>
                <div class="od-tld-price tbd"><?= t('Price on quote','Prix sur devis') ?></div>
            <?php endif ?>
            <button class="od-tld-btn" data-od-tld="<?= $tld ?>">
                <?= t('Choose','Choisir') ?> <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
        <?php endforeach ?>
    </div>
</section>

<!-- ── WHY US ───────────────────────────────────────────────────── -->
<section class="od-section">
    <div class="od-section-head">
        <span class="eyebrow"><?= t('Why CamDigit','Pourquoi CamDigit') ?></span>
        <h2><?= t('Everything included, no upsells','Tout inclus, sans frais cachés') ?></h2>
        <p><?= t('Every feature competitors charge extra for — free with us, for life.','Chaque option facturée ailleurs — gratuite chez nous, à vie.') ?></p>
    </div>

    <div class="od-why">
        <div class="od-why-card">
            <div class="od-why-icon"><i class="fa-solid fa-bolt"></i></div>
            <h3><?= t('Instant activation','Activation instantanée') ?></h3>
            <p><?= t('Domains go live within seconds of payment confirmation.','Vos domaines actifs en quelques secondes après paiement.') ?></p>
        </div>
        <div class="od-why-card">
            <div class="od-why-icon"><i class="fa-solid fa-user-shield"></i></div>
            <h3><?= t('Free WHOIS privacy','WHOIS privé gratuit') ?></h3>
            <p><?= t('Your personal info stays hidden — free for life on every eligible TLD.','Vos coordonnées masquées — gratuit à vie pour toutes les extensions éligibles.') ?></p>
        </div>
        <div class="od-why-card">
            <div class="od-why-icon"><i class="fa-solid fa-sliders"></i></div>
            <h3><?= t('Full DNS control','Contrôle DNS complet') ?></h3>
            <p><?= t('Unlimited records, DNSSEC, email forwarding — all in your dashboard.','Enregistrements illimités, DNSSEC et redirections email — dans votre espace.') ?></p>
        </div>
        <div class="od-why-card">
            <div class="od-why-icon"><i class="fa-solid fa-headset"></i></div>
            <h3><?= t('Bilingual support','Support bilingue') ?></h3>
            <p><?= t('Cameroon-based team, 24/7 in English and French.','Équipe basée au Cameroun, 24h/24 en français et anglais.') ?></p>
        </div>
    </div>
</section>

<!-- ── HOW IT WORKS ─────────────────────────────────────────────── -->
<section class="od-section">
    <div class="od-section-head">
        <span class="eyebrow"><?= t('How it works','Comment ça marche') ?></span>
        <h2><?= t('Live in under 5 minutes','En ligne en moins de 5 minutes') ?></h2>
    </div>
    <div class="od-steps">
        <div class="od-step">
            <div class="od-step-num">1</div>
            <h3><i class="fa-solid fa-magnifying-glass" style="color:var(--theme2);margin-right:6px"></i><?= t('Search','Recherchez') ?></h3>
            <p><?= t('Type your brand name and check availability across all extensions.','Tapez votre nom et vérifiez la disponibilité.') ?></p>
        </div>
        <div class="od-step">
            <div class="od-step-num">2</div>
            <h3><i class="fa-solid fa-cart-plus" style="color:var(--theme2);margin-right:6px"></i><?= t('Add to cart','Ajoutez au panier') ?></h3>
            <p><?= t('Stack as many domains as you need — they all add to the same cart.','Empilez autant de domaines que voulu.') ?></p>
        </div>
        <div class="od-step">
            <div class="od-step-num">3</div>
            <h3><i class="fa-solid fa-rocket" style="color:var(--theme2);margin-right:6px"></i><?= t('Go live','Lancez') ?></h3>
            <p><?= t('Pay with MoMo, Orange Money, Visa or PayPal — instant activation.','Payez avec MoMo, Orange Money, Visa ou PayPal — activation immédiate.') ?></p>
        </div>
    </div>
</section>

<!-- ── STATS ────────────────────────────────────────────────────── -->
<section class="od-section">
    <div class="od-stats-panel">
        <div class="od-stat-block">
            <div class="ic s1"><i class="fa-solid fa-globe"></i></div>
            <div class="n" data-count="12500" data-suffix="+">0</div>
            <div class="l"><?= t('Domains registered','Domaines') ?></div>
        </div>
        <div class="od-stat-block">
            <div class="ic s2"><i class="fa-solid fa-layer-group"></i></div>
            <div class="n" data-count="200" data-suffix="+">0</div>
            <div class="l"><?= t('Extensions','Extensions') ?></div>
        </div>
        <div class="od-stat-block">
            <div class="ic s3"><i class="fa-solid fa-bolt"></i></div>
            <div class="n" data-count="99.99" data-suffix="%" data-decimals="2">0</div>
            <div class="l"><?= t('DNS uptime','Disponibilité DNS') ?></div>
        </div>
        <div class="od-stat-block">
            <div class="ic s4"><i class="fa-solid fa-headset"></i></div>
            <div class="n">24/7</div>
            <div class="l"><?= t('Bilingual support','Support bilingue') ?></div>
        </div>
    </div>
</section>

<!-- ── FAQ ──────────────────────────────────────────────────────── -->
<section class="od-section">
    <div class="od-section-head">
        <span class="eyebrow">FAQ</span>
        <h2><?= t('Frequently asked','Questions fréquentes') ?></h2>
    </div>
    <div class="od-faq">
        <details>
            <summary><i class="q fa-solid fa-clock"></i><span><?= t('How long does it take to register?','Combien de temps pour enregistrer ?') ?></span></summary>
            <div class="body"><?= t('Most domains activate within seconds. .CM domains complete in 1–5 minutes through the local registry. Some country extensions can take up to 24 hours.','La plupart en quelques secondes. Les .CM en 1 à 5 minutes via le registre local. Certaines extensions peuvent prendre 24h.') ?></div>
        </details>
        <details>
            <summary><i class="q fa-solid fa-arrows-rotate"></i><span><?= t('Can I transfer a domain in?','Puis-je transférer un domaine ?') ?></span></summary>
            <div class="body"><?= t('Yes. Get the EPP code from your current registrar. Transfer completes in 5–7 days and extends expiry by one year at no extra cost.','Oui. Récupérez le code EPP. Transfert en 5 à 7 jours, prolonge l\'expiration d\'un an gratuitement.') ?></div>
        </details>
        <details>
            <summary><i class="q fa-solid fa-user-shield"></i><span><?= t('Is WHOIS privacy really free?','La confidentialité WHOIS est-elle gratuite ?') ?></span></summary>
            <div class="body"><?= t('Yes — free for life on every TLD that supports it. Your name, address, phone and email stay hidden from public WHOIS.','Oui — gratuit à vie pour toutes les extensions qui le supportent.') ?></div>
        </details>
        <details>
            <summary><i class="q fa-solid fa-bell"></i><span><?= t('What if I forget to renew?','Que se passe-t-il si j\'oublie de renouveler ?') ?></span></summary>
            <div class="body"><?= t('We send reminders 60, 30, 7 and 1 day before expiry. A 30-day grace period lets you renew at the normal price.','Rappels à 60, 30, 7 et 1 jour avant expiration. Période de grâce de 30 jours au tarif normal.') ?></div>
        </details>
        <details>
            <summary><i class="q fa-solid fa-credit-card"></i><span><?= t('Payment methods?','Moyens de paiement ?') ?></span></summary>
            <div class="body"><?= t('MTN MoMo, Orange Money, Visa, Mastercard, PayPal, and bank transfer. All processed over SSL.','MTN MoMo, Orange Money, Visa, Mastercard, PayPal et virement bancaire. Sécurisé SSL.') ?></div>
        </details>
    </div>
</section>

<!-- ── FINAL CTA ────────────────────────────────────────────────── -->
<div class="od-final">
    <h2><?= t('Your perfect domain is waiting','Votre domaine idéal vous attend') ?></h2>
    <p><?= t('Search above. Lock it in seconds. Own your online identity.','Recherchez ci-dessus. Réservez en quelques secondes.') ?></p>
    <button class="cdm-btn cdm-btn-accent cdm-btn-lg" id="odBackTop">
        <i class="fa-solid fa-arrow-up"></i> <?= t('Start your search','Commencer la recherche') ?>
    </button>
</div>

<script>
(function () {
    'use strict';
    var SITE = CDM_CONFIG.site;
    var LANG = CDM_CONFIG.lang;
    var ALL = <?= json_encode(array_keys($tlds)) ?>;
    var INIT_SLD = <?= json_encode($initialSld) ?>;

    function t(en, fr) { return LANG === 'french' ? fr : en; }
    function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
    function sanitizeSld(v) { return String(v||'').toLowerCase().replace(/[^a-z0-9-]/g,'').replace(/^-+|-+$/g,'').substring(0,63); }

    // Native fetch JSON helper (doesn't depend on CDM module)
    function postJson(url, body) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().catch(function () { return { result: 'error' }; }); });
    }
    function notify(msg, kind) {
        if (window.CDM && CDM.toast) return CDM.toast(msg, kind);
        // inline fallback
        var box = document.createElement('div');
        box.style.cssText = 'position:fixed;top:18px;right:18px;z-index:9999;padding:12px 18px;border-radius:10px;color:#fff;font-size:14px;box-shadow:0 10px 30px rgba(0,0,0,.2);background:' + (kind === 'err' ? '#dc2626' : '#236a25');
        box.textContent = msg;
        document.body.appendChild(box);
        setTimeout(function () { box.remove(); }, 3000);
    }
    function inCart(domain) {
        // Naive — could query /cart-api.php, but we'll let server confirm via add response
        return false;
    }

    var resBox = document.getElementById('odResult');
    var primary = document.getElementById('odPrimary');
    var sugsWrap = document.getElementById('odSugsWrap');
    var sugList = document.getElementById('odSugList');

    function renderResult(sld, tld, items) {
        var dom = sld + tld;
        var p = items.find(function (d) { return d.tld === tld; });
        if (p) {
            var avail = p.status === 'available';
            primary.className = 'od-result-primary ' + (avail ? 'available' : 'taken');
            primary.innerHTML =
                '<div>' +
                    '<div class="od-result-name">' + esc(sld) + '<span class="ext">' + esc(tld) + '</span></div>' +
                    '<div class="od-result-status">' +
                        (avail ? '<i class="fa-solid fa-circle-check"></i> ' + esc(t('Available — yours for the taking','Disponible'))
                               : '<i class="fa-solid fa-circle-xmark"></i> ' + esc(t('Already registered','Déjà enregistré'))) +
                    '</div>' +
                '</div>' +
                (avail ? '<button class="cdm-btn cdm-btn-accent" data-add="' + esc(dom) + '">' +
                            '<i class="fa-solid fa-cart-plus"></i> ' + esc(t('Add to cart','Ajouter au panier')) +
                         '</button>'
                       : '');
        }
        var others = items.filter(function (d) { return d.tld !== tld; });
        if (others.length) {
            sugsWrap.hidden = false;
            sugList.innerHTML = others.map(function (d) {
                var avail = d.status === 'available';
                return '<div class="od-sug ' + (avail ? '' : 'taken') + '">' +
                            '<div class="name">' + esc(sld) + '<span class="ext">' + esc(d.tld) + '</span></div>' +
                            (avail ? '<button class="od-sug-add" data-add="' + esc(d.domain) + '">' + esc(t('Add','Ajouter')) + '</button>'
                                   : '<span style="color:#9ca3af;font-size:11px">' + esc(t('Taken','Pris')) + '</span>') +
                       '</div>';
            }).join('');
        } else {
            sugsWrap.hidden = true;
        }
        resBox.hidden = false;
    }

    function doSearch() {
        var sld = sanitizeSld(document.getElementById('odSld').value);
        var tld = document.getElementById('odTld').value;
        if (!sld) { document.getElementById('odSld').focus(); return; }

        var btn = document.getElementById('odBtn');
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + t('Searching…','Recherche…');

        postJson(SITE + '/api-proxy.php', { action: '__csrf__' }).then(function (d) {
            return postJson(SITE + '/api-proxy.php', {
                action: 'CheckMultiAvailability', _csrf: d.csrf_token,
                sld: sld, tlds: [tld].concat(ALL.filter(function (x) { return x !== tld; })).slice(0, 8)
            });
        }).then(function (data) {
            btn.disabled = false; btn.innerHTML = orig;
            if (!data || data.result !== 'success') {
                notify(data && data.message || t('Search failed','Recherche échouée'), 'err');
                return;
            }
            renderResult(sld, tld, data.domains);
            resBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }).catch(function () {
            btn.disabled = false; btn.innerHTML = orig;
            notify(t('Network error','Erreur réseau'), 'err');
        });
    }

    function addToCart(domain, btn) {
        var origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        postJson(SITE + '/cart-api.php', { action: '__csrf__' }).then(function (d) {
            return postJson(SITE + '/cart-api.php', { action: 'add_domain', _csrf: d.csrf_token, domain: domain, years: 1 });
        }).then(function (r) {
            if (r && r.result === 'success') {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + t('In cart','Au panier');
                btn.style.background = '#6b7280';
                notify(t('Added to cart','Ajouté au panier') + ': ' + domain, 'ok');
                // Bump the floating cart badge
                var badge = document.querySelector('.cdm-cart-badge');
                if (badge) badge.textContent = String(r.count || (parseInt(badge.textContent, 10) || 0) + 1);
                else if (r.count) {
                    var link = document.querySelector('.cdm-cart-link');
                    if (link) { link.classList.add('has-items'); link.insertAdjacentHTML('beforeend', '<span class="cdm-cart-badge">' + r.count + '</span>'); }
                }
            } else {
                btn.disabled = false; btn.innerHTML = origHtml;
                notify((r && r.message) || t('Could not add to cart','Impossible'), 'err');
            }
        }).catch(function () {
            btn.disabled = false; btn.innerHTML = origHtml;
            notify(t('Network error','Erreur réseau'), 'err');
        });
    }

    // Wire events
    document.querySelector('[data-od-form]').addEventListener('submit', function (e) { e.preventDefault(); doSearch(); });
    resBox.addEventListener('click', function (e) {
        var b = e.target.closest('[data-add]'); if (!b || b.disabled) return;
        addToCart(b.getAttribute('data-add'), b);
    });
    document.querySelectorAll('[data-od-tld]').forEach(function (b) {
        b.addEventListener('click', function () {
            var tld = b.getAttribute('data-od-tld');
            var sel = document.getElementById('odTld');
            Array.from(sel.options).forEach(function (o) { if (o.value === tld) sel.value = tld; });
            document.getElementById('odSld').focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            if (document.getElementById('odSld').value.trim()) doSearch();
        });
    });
    document.getElementById('odBackTop').addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(function () { document.getElementById('odSld').focus(); }, 400);
    });

    // Animated counters
    function animate(el) {
        var target = parseFloat(el.getAttribute('data-count'));
        var suffix = el.getAttribute('data-suffix') || '';
        var dec = parseInt(el.getAttribute('data-decimals') || '0', 10);
        var dur = 1400, start = performance.now();
        function tick(now) {
            var p = Math.min(1, (now - start) / dur);
            var v = target * (1 - Math.pow(1 - p, 3));
            el.textContent = (dec ? v.toFixed(dec) : Math.floor(v).toLocaleString()) + suffix;
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) { if (e.isIntersecting) { animate(e.target); io.unobserve(e.target); } });
        }, { threshold: .3 });
        document.querySelectorAll('[data-count]').forEach(function (n) { io.observe(n); });
    }

    // Auto-search if URL has ?sld=
    if (INIT_SLD) doSearch();
})();
</script>

<?php cdm_foot(); ?>
