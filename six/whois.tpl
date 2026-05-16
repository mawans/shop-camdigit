{* ─── Featured .CM Domain Search ─────────────────────────────────────────── *}
<div class="cd-cm-hero">
    <div class="cd-cm-hero-inner">
        <div class="cd-cm-badge-wrap">
            <span class="cd-cm-flag">.CM</span>
            <span class="cd-cm-tag">{if $language == 'french'}Domaine national{else}National Domain{/if}</span>
        </div>
        <div class="cd-cm-copy">
            <h2>{if $language == 'french'}Enregistrez votre domaine <strong>.cm</strong>{else}Register your <strong>.cm</strong> domain{/if}</h2>
            <p>{if $language == 'french'}Le domaine national du Cameroun — géré directement par CamDigit, l'acteur de référence.{else}Cameroon's national domain — registered &amp; managed directly through CamDigit.{/if}</p>
        </div>
        <form class="cd-cm-search-form" id="cdCmHeroForm" novalidate>
            <div class="cd-cm-input-group">
                <input
                    type="text"
                    id="cdCmHeroInput"
                    class="cd-cm-input"
                    placeholder="{if $language == 'french'}votremarque{else}yourbrand{/if}"
                    maxlength="63"
                    autocapitalize="none"
                    spellcheck="false"
                    autocomplete="off"
                />
                <span class="cd-cm-suffix">.cm</span>
            </div>
            <button type="submit" class="cd-cm-btn">
                {if $language == 'french'}Vérifier <i class="fas fa-arrow-right"></i>{else}Check Availability <i class="fas fa-arrow-right"></i>{/if}
            </button>
        </form>
        <ul class="cd-cm-perks">
            <li><i class="fas fa-check-circle"></i> {if $language == 'french'}Enregistrement rapide{else}Fast registration{/if}</li>
            <li><i class="fas fa-check-circle"></i> {if $language == 'french'}DNS inclus{else}Free DNS included{/if}</li>
            <li><i class="fas fa-check-circle"></i> {if $language == 'french'}Support local{else}Local support{/if}</li>
        </ul>
    </div>
</div>

<script>
document.getElementById('cdCmHeroForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var raw = document.getElementById('cdCmHeroInput').value.trim().toLowerCase().replace(/\.cm$/i, '');
    if (!raw) { document.getElementById('cdCmHeroInput').focus(); return; }
    window.location.href = '{$WEB_ROOT}/order-cm.php?sld=' + encodeURIComponent(raw);
});
</script>

{* ─── Other Domain Extensions ────────────────────────────────────────────── *}
<div class="cd-whois-wrapper">
    <div class="cd-whois-header">
        <i class="fas fa-globe"></i>
        <span>{if $language == 'french'}Rechercher d'autres extensions{else}Search other domain extensions{/if}</span>
    </div>
    {$whois}
</div>

