<?php
/**
 * CamDigit Hosting / Product Order Page
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/order-hosting.php
 *
 * Lists products from a WHMCS product group (default: shared hosting) and lets
 * the user purchase one. Optionally attaches a new domain registration.
 *
 *   ?gid=N        → product group id (defaults to PRODUCT_GROUP_DEFAULT)
 *   ?pid=N        → pre-selected product id (skip listing, jump to checkout)
 *
 * All API calls happen server-side via whmcs_api() — no credentials in browser.
 * ─────────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';
require_once __DIR__ . '/lib/cart.php';

// Default product group id — change to your shared-hosting group id
const PRODUCT_GROUP_DEFAULT = 1;

$gid    = (int) ($_GET['gid'] ?? $_POST['gid'] ?? PRODUCT_GROUP_DEFAULT);
$pid    = (int) ($_GET['pid'] ?? $_POST['pid'] ?? 0);
$step   = (string) ($_POST['step'] ?? '');
$errors = [];

// ── Fetch product list for the group ────────────────────────────────────────
$products = [];
try {
    $r = whmcs_api('GetProducts', ['gid' => $gid]);
    if (($r['result'] ?? '') === 'success') {
        $products = $r['products']['product'] ?? [];
    }
} catch (RuntimeException $e) {
    $errors[] = $e->getMessage();
}

// Build a quick lookup: pid → product
$byPid = [];
foreach ($products as $p) $byPid[(int)$p['pid']] = $p;
$selected = $pid && isset($byPid[$pid]) ? $byPid[$pid] : null;

// ── Helper: pull price for a given cycle from a product's pricing block ────
function cd_pick_price(array $product, string $cycle): array
{
    foreach (($product['pricing'] ?? []) as $cur => $prices) {
        if (isset($prices[$cycle]) && (float)$prices[$cycle] >= 0) {
            return ['price' => (float)$prices[$cycle], 'currency' => (string)$cur, 'all' => $prices];
        }
    }
    return ['price' => 0.0, 'currency' => 'XAF', 'all' => []];
}

// ── Step: add to cart ──────────────────────────────────────────────────────
if ($step === 'addcart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    cd_csrf_check();
    if (!cd_rate_limit('order_hosting', 10, 600)) {
        $errors[] = t('Too many attempts. Please wait a few minutes.',
                      'Trop de tentatives. Veuillez patienter quelques minutes.');
    } else {
        $pid = (int) $_POST['pid'];
        if (!isset($byPid[$pid])) {
            $errors[] = t('Invalid product.', 'Produit invalide.');
        } else {
            $product = $byPid[$pid];
            $billing = (string)($_POST['billingcycle'] ?? 'annually');
            $info    = cd_pick_price($product, $billing);

            // map cycle → years for "Modifier" duration shown in cart
            $years = match ($billing) {
                'monthly' => 1, 'quarterly' => 1, 'semiannually' => 1,
                'annually' => 1, 'biennially' => 2, 'triennially' => 3,
                default => 1,
            };

            // Build a normalized pricing map keyed by cycle (for cart year-toggle)
            $pricing = [];
            foreach (['annually','biennially','triennially'] as $cy) {
                if (isset($info['all'][$cy]) && (float)$info['all'][$cy] >= 0) {
                    $pricing[$cy] = (float)$info['all'][$cy];
                }
            }

            cd_cart_add_hosting([
                'pid'          => $pid,
                'name'         => (string)$product['name'],
                'description'  => (string)($product['description'] ?? ''),
                'billingcycle' => $billing,
                'years'        => $years,
                'price'        => $info['price'],
                'original'     => $info['price'],
                'currency'     => $info['currency'],
                'pricing'      => $pricing,
            ]);

            header('Location: ' . SITE_URL . '/cart.php');
            exit;
        }
    }
}

// ── Render ──────────────────────────────────────────────────────────────────
cd_render_head(
    t('Hosting Plans', "Plans d'Hébergement"),
    $selected
        ? htmlspecialchars((string)$selected['name'])
        : t('Powerful <span style="color:var(--theme2,#ffa31a)">hosting</span> plans',
            'Plans d\'<span style="color:var(--theme2,#ffa31a)">hébergement</span> puissants'),
    $selected
        ? t('Configure your plan and complete checkout in minutes.',
            'Configurez votre plan et passez commande en quelques minutes.')
        : t('Fast SSD storage, free SSL, 24/7 support — built for African businesses.',
            'Stockage SSD rapide, SSL gratuit, support 24/7 — conçu pour les entreprises africaines.')
);
?>

<?php if ($errors): ?>
    <div class="cd-alert cd-alert-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
    </div>
<?php endif ?>

<?php if (!$selected): ?>
    <div class="cd-card">
        <div class="cd-section-title">
            <h2><?= t('Choose a hosting plan', "Choisissez un plan d'hébergement") ?></h2>
            <p><?= t('Every plan includes free DNS management, automatic backups, and a 30-day money-back guarantee.',
                    'Chaque plan inclut la gestion DNS gratuite, des sauvegardes automatiques et une garantie de remboursement de 30 jours.') ?></p>
        </div>
        <?php if (!$products): ?>
            <p class="cd-muted" style="text-align:center"><?= t('No products are available right now. Please try again later.', 'Aucun produit disponible pour le moment.') ?></p>
        <?php else: ?>
            <div class="cd-product-grid">
                <?php foreach ($products as $i => $p):
                    $price = $p['pricing'][array_key_first($p['pricing'] ?? [])] ?? null;
                    $monthly = is_array($price) ? ($price['monthly'] ?? '') : '';
                    $featured = ($i === (int)floor(count($products) / 2));
                    ?>
                    <div class="cd-product-card<?= $featured ? ' cd-product-featured' : '' ?>">
                        <?php if ($featured): ?>
                            <div style="position:absolute;top:14px;right:14px"><span class="cd-pill cd-pill-warn"><?= t('Popular','Populaire') ?></span></div>
                        <?php endif ?>
                        <h3><?= htmlspecialchars((string)$p['name']) ?></h3>
                        <div class="cd-product-desc"><?= nl2br(htmlspecialchars((string)($p['description'] ?? ''))) ?></div>
                        <?php if ($monthly !== ''): ?>
                            <div class="cd-product-price">
                                <?= htmlspecialchars($monthly) ?><small>/<?= t('month','mois') ?></small>
                            </div>
                        <?php endif ?>
                        <a class="cd-btn" href="?gid=<?= $gid ?>&pid=<?= (int)$p['pid'] ?>">
                            <?= t('Select plan', 'Choisir ce plan') ?> <i class="fa fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
<?php else: ?>
    <div class="cd-card">
        <p class="cd-muted" style="margin-top:0"><?= nl2br(htmlspecialchars((string)($selected['description'] ?? ''))) ?></p>
        <div class="cd-divider"></div>

        <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(cd_csrf()) ?>">
            <input type="hidden" name="step"  value="addcart">
            <input type="hidden" name="gid"   value="<?= $gid ?>">
            <input type="hidden" name="pid"   value="<?= (int)$selected['pid'] ?>">

            <div class="cd-form-section"><i class="fa fa-calendar"></i><?= t('Billing cycle','Cycle de facturation') ?></div>
            <select class="cd-input" name="billingcycle">
                <?php foreach (($selected['pricing'] ?? []) as $curCode => $prices):
                    foreach (['monthly','quarterly','semiannually','annually','biennially','triennially'] as $cy):
                        $v = $prices[$cy] ?? null;
                        if ($v !== null && (float)$v >= 0): ?>
                            <option value="<?= $cy ?>" <?= $cy === 'annually' ? 'selected' : '' ?>>
                                <?= ucfirst($cy) ?> — <?= htmlspecialchars($curCode) ?> <?= htmlspecialchars((string)$v) ?>
                            </option>
                <?php       endif;
                    endforeach;
                endforeach ?>
            </select>

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:22px">
                <button class="cd-btn" type="submit">
                    <i class="fa fa-cart-plus"></i> <?= t('Add to cart','Ajouter au panier') ?>
                </button>
                <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/order-domain.php">
                    <i class="fa fa-globe"></i> <?= t('Add a domain','Ajouter un domaine') ?>
                </a>
            </div>

            <p class="cd-muted" style="font-size:13px;margin-top:14px">
                <i class="fa fa-circle-info" style="color:var(--theme2,#ffa31a);margin-right:5px"></i>
                <?= t('You can add a domain and review your cart before checkout. Account details are collected at the final step.',
                      'Vous pourrez ajouter un domaine et vérifier votre panier avant de finaliser. Les coordonnées sont demandées à la dernière étape.') ?>
            </p>
        </form>
    </div>
<?php endif ?>

<?php cd_render_foot(); ?>
