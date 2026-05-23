<?php
/**
 * CamDigit — Knowledgebase index + article view
 * Upload to: <whmcs_root>/knowledgebase.php
 *
 *   /knowledgebase.php          → category + article grid
 *   /knowledgebase.php?id=NNN   → single article
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

$articleId = (int)($_GET['id'] ?? 0);
$search    = cd_sanitize_text($_GET['q'] ?? '', 100);
$errors    = [];

if ($articleId > 0) {
    // ── Single article view ────────────────────────────────────────────────
    $article = null;
    try {
        $r = whmcs_api('GetKnowledgebaseArticle', ['articleid' => $articleId]);
        if (($r['result'] ?? '') === 'success') $article = $r;
    } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

    cd_render_head(
        $article ? (string)$article['title'] : t('Knowledge Base','Base de connaissances'),
        $article ? '<span style="color:var(--theme2,#ffa31a)">' . htmlspecialchars((string)$article['title']) . '</span>'
                 : t('Knowledge Base','Base de connaissances'),
        $article ? '<i class="fa fa-eye"></i> ' . (int)($article['views'] ?? 0) . ' ' . t('views','vues') : null
    );
    ?>
    <div class="cd-card" style="max-width:860px;margin:0 auto">
        <?php if ($errors): ?>
        <div class="cd-alert cd-alert-error">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
        </div>
        <?php elseif ($article): ?>
        <div style="color:#445375;line-height:1.8;font-size:15px">
            <?= $article['article'] /* raw WHMCS-rendered HTML */ ?>
        </div>
        <div class="cd-divider"></div>
        <p class="cd-muted" style="font-size:13px">
            <i class="fa fa-circle-info"></i>
            <?= t('Did this article help? ','Cet article vous a-t-il aidé ?') ?>
            <a href="<?= SITE_URL ?>/submitticket.php"><?= t('Contact support','Contactez le support') ?></a>
            <?= t(' if you still need help.',' si vous avez besoin d\'aide.') ?>
        </p>
        <?php endif ?>
    </div>
    <p style="text-align:center;margin-top:14px">
        <a href="<?= SITE_URL ?>/knowledgebase.php" class="cd-link-soft">
            <i class="fa fa-arrow-left"></i> <?= t('Back to all articles','Retour aux articles') ?>
        </a>
    </p>
    <?php cd_render_foot(); exit;
}

// ── Index view ─────────────────────────────────────────────────────────────
$articles   = [];
$categories = [];
try {
    $r = whmcs_api('GetKnowledgebaseArticles', ['limitnum' => 50, 'search' => $search ?: '']);
    $articles = $r['kbarticles']['kbarticle'] ?? [];
} catch (RuntimeException $e) { $errors[] = $e->getMessage(); }

try {
    $r = whmcs_api('GetKnowledgebaseCategories', []);
    $categories = $r['categories']['category'] ?? [];
} catch (RuntimeException) { /* non-fatal */ }

cd_render_head(
    t('Knowledge Base','Base de connaissances'),
    t('Knowledge <span style="color:var(--theme2,#ffa31a)">Base</span>',
      'Base de <span style="color:var(--theme2,#ffa31a)">connaissances</span>'),
    t('Quick answers to common questions about domains, hosting, and billing.',
      'Réponses rapides aux questions fréquentes sur domaines, hébergement et facturation.')
);
?>

<form action="" method="get" class="cd-card" style="margin-top:-40px;position:relative;z-index:5">
    <div class="cd-search-bar">
        <input class="cd-input" type="text" name="q" placeholder="<?= t('Search articles…','Rechercher…') ?>"
               value="<?= htmlspecialchars($search) ?>">
        <button class="cd-btn" type="submit"><i class="fa fa-search"></i> <?= t('Search','Rechercher') ?></button>
    </div>
</form>

<?php if ($errors): ?>
<div class="cd-alert cd-alert-error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach ?>
</div>
<?php endif ?>

<?php if ($categories): ?>
<div class="cd-card">
    <h2 style="margin-top:0"><i class="fa fa-folder-tree" style="color:var(--theme2,#ffa31a);margin-right:8px"></i><?= t('Browse by category','Par catégorie') ?></h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
        <?php foreach ($categories as $cat): ?>
        <a href="?cat=<?= (int)$cat['id'] ?>" style="background:#f3f7fb;padding:18px;border-radius:10px;border:1px solid #e3e8f0;transition:all .2s;display:block;color:#0f0d1d"
           onmouseover="this.style.borderColor='var(--theme,#236a25)';this.style.transform='translateY(-2px)'"
           onmouseout="this.style.borderColor='#e3e8f0';this.style.transform=''">
            <i class="fa fa-folder" style="color:var(--theme2,#ffa31a);margin-right:8px"></i>
            <strong><?= htmlspecialchars((string)$cat['name']) ?></strong>
            <div class="cd-muted" style="font-size:12px;margin-top:4px"><?= (int)($cat['articles'] ?? 0) ?> <?= t('articles','articles') ?></div>
        </a>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<div class="cd-card">
    <h2 style="margin-top:0">
        <?= $search
            ? t('Results for','Résultats pour') . ' "' . htmlspecialchars($search) . '"'
            : t('Popular articles','Articles populaires') ?>
    </h2>
    <?php if (!$articles): ?>
    <div style="text-align:center;padding:40px 20px">
        <i class="fa fa-book-open" style="font-size:42px;color:#cbd2dd;margin-bottom:14px"></i>
        <p class="cd-muted"><?= t('No articles found.','Aucun article trouvé.') ?></p>
        <a class="cd-btn cd-btn-secondary" href="<?= SITE_URL ?>/submitticket.php"><i class="fa fa-life-ring"></i> <?= t('Ask support','Demander au support') ?></a>
    </div>
    <?php else: ?>
    <ul style="list-style:none;padding:0;margin:0">
        <?php foreach ($articles as $a): ?>
        <li style="padding:16px 0;border-bottom:1px solid #f0f4f8">
            <a href="?id=<?= (int)$a['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;gap:12px;color:#0f0d1d">
                <div>
                    <strong style="font-size:15px"><?= htmlspecialchars((string)$a['title']) ?></strong>
                    <div class="cd-muted" style="font-size:13px;margin-top:3px">
                        <i class="fa fa-eye"></i> <?= (int)($a['views'] ?? 0) ?> <?= t('views','vues') ?>
                    </div>
                </div>
                <i class="fa fa-arrow-right" style="color:var(--theme,#236a25);font-size:14px"></i>
            </a>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</div>

<?php cd_render_foot(); ?>
