<!-- CAMDigit Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<!-- CAMDigit CSS (exact match to original site) -->
<link href="{assetPath file='bootstrap.min.css'}" rel="stylesheet">
<link href="{assetPath file='camdigit-all.min.css'}" rel="stylesheet">
<link href="{assetPath file='animate.css'}" rel="stylesheet">
<link href="{assetPath file='magnific-popup.css'}" rel="stylesheet">
<link href="{assetPath file='meanmenu.css'}" rel="stylesheet">
<link href="{assetPath file='swiper-bundle.min.css'}" rel="stylesheet">
<link href="{assetPath file='nice-select.css'}?v={$versionHash}" rel="stylesheet">
<link href="{assetPath file='color.css'}?v={$versionHash}" rel="stylesheet">
<link href="{assetPath file='camdigit-main.css'}?v={$versionHash}" rel="stylesheet">
<link href="{assetPath file='camdigit-whmcs-bridge.css'}?v={$versionHash}" rel="stylesheet">
{assetExists file="custom.css"}
<link href="{$__assetPath__}?v={$versionHash}" rel="stylesheet">
{/assetExists}
<link rel="shortcut icon" href="{$WEB_ROOT}/templates/{$template}/img/logo/logo-camdigit-small-small.png" />
<!-- WHMCS Required JS Variables -->
<script type="text/javascript">
    var csrfToken = '{$token}',
        markdownGuide = '{lang|addslashes key="markdown.title"}',
        locale = '{if !empty($mdeLocale)}{$mdeLocale}{else}en{/if}',
        saved = '{lang|addslashes key="markdown.saved"}',
        saving = '{lang|addslashes key="markdown.saving"}',
        whmcsBaseUrl = "{$WEB_ROOT}";
    {if $captcha}{$captcha->getPageJs()}{/if}
</script>
<script src="{assetPath file='scripts.min.js'}?v={$versionHash}"></script>
{if $templatefile == "viewticket" && !$loggedin}
  <meta name="robots" content="noindex" />
{/if}
