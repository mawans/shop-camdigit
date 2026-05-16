<div class="domain-pricing">

    {* ── Featured .CM Hero Banner ── *}
    <div class="cd-dp-cm-hero">
        <div class="cd-dp-cm-left">
            <span class="cd-dp-cm-tld">.CM</span>
            <div>
                <h3>{if $language == 'french'}Domaine national du Cameroun{else}Cameroon's National Domain{/if}</h3>
                <p>{if $language == 'french'}Enregistrez votre identité numérique camerounaise — rapide, fiable, local.{else}Secure your Cameroonian digital identity — fast, reliable &amp; locally managed.{/if}</p>
            </div>
        </div>
        <form class="cd-dp-cm-search" id="cdDpCmForm" novalidate>
            <div class="cd-dp-cm-input-wrap">
                <input type="text" id="cdDpCmInput" placeholder="{if $language == 'french'}votremarque{else}yourbrand{/if}" maxlength="63" autocapitalize="none" autocomplete="off" />
                <span class="cd-dp-suffix">.cm</span>
            </div>
            <button type="submit">
                {if $language == 'french'}Vérifier{else}Check{/if} <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
    <script>
    document.getElementById('cdDpCmForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var v = document.getElementById('cdDpCmInput').value.trim().toLowerCase().replace(/\.cm$/i, '');
        if (v) window.location.href = '{$WEB_ROOT}/order-cm.php?sld=' + encodeURIComponent(v);
    });
    </script>

    {if $featuredTlds}
        <div class="featured-tlds-container">
            <div class="row">
                {foreach $featuredTlds as $num => $tldinfo}
                    <div class="col-md-3 col-sm-4 col-xs-6">
                        <div class="featured-tld">
                            <div class="img-container">
                                <img src="{$BASE_PATH_IMG}/tld_logos/{$tldinfo.tldNoDots}.png">
                            </div>
                            <div class="price {$tldinfo.tldNoDots}">
                                {if is_object($tldinfo.register)}
                                    {$tldinfo.register->toPrefixed()}{if $tldinfo.period > 1}{lang key="orderForm.shortPerYears" years={$tldinfo.period}}{else}{lang key="orderForm.shortPerYear" years=''}{/if}
                                {else}
                                    {lang key="domainregnotavailable"}
                                {/if}
                            </div>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
    {/if}

    <div class="cd-dp-browse-header">
        <h4><i class="fas fa-th-list"></i> {lang key='pricing.browseExtByCategory'}</h4>
        {if !$loggedin && $currencies}
            <form method="post" action="" class="cd-dp-currency-form">
                <i class="fas fa-coins"></i>
                <select name="currency" class="form-control cd-dp-currency-select" onchange="submit()">
                    <option>{lang key="changeCurrency"} ({$activeCurrency.prefix} {$activeCurrency.code})</option>
                    {foreach $currencies as $currency}
                        <option value="{$currency['id']}">{$currency['prefix']} {$currency['code']}</option>
                    {/foreach}
                </select>
            </form>
        {/if}
    </div>

    <div class="tld-filters cd-tld-filters">
        <a href="#" data-category="" class="cd-filter-all label label-default label-success">{if $language == 'french'}Tous{else}All{/if}</a>
        {foreach $tldCategories as $category => $count}
            <a href="#" data-category="{$category}" class="label label-default">{lang key="domainTldCategory.$category" defaultValue=$category} <span class="cd-filter-count">{$count}</span></a>
        {/foreach}
    </div>

    {include file="$template/includes/tablelist.tpl" tableName="DomainPricing" noOrdering=true}
    <script type="text/javascript">
        jQuery(document).ready(function(){
            var table = jQuery('#tableDomainPricing').removeClass('hidden').DataTable();
            {if $orderby == 'date'}
                table.order(0, '{$sort}');
            {elseif $orderby == 'subject'}
                table.order(1, '{$sort}');
            {/if}
            table.draw();
            jQuery('#tableLoading').addClass('hidden');

            // Highlight .cm row
            jQuery('#tableDomainPricing tbody tr').each(function() {
                var tld = jQuery(this).find('td:first').text().trim();
                if (tld === '.cm' || tld.indexOf('.cm') === 0) {
                    jQuery(this).addClass('cd-cm-row');
                }
            });

            jQuery('.tld-filters a').unbind();
            jQuery('.tld-filters a').click(function(e) {
                e.preventDefault();
                var cat = jQuery(this).data('category');
                if (jQuery(this).hasClass('label-success') || cat === '') {
                    jQuery('#tableDomainPricing_wrapper input[type="search"]').val('').trigger('keyup');
                    jQuery('.tld-filters a').removeClass('label-success');
                    jQuery('.cd-filter-all').addClass('label-success');
                } else {
                    jQuery('#tableDomainPricing_wrapper input[type="search"]').val(cat).trigger('keyup');
                    jQuery('.tld-filters a').removeClass('label-success');
                    jQuery(this).addClass('label-success');
                }
            });
        });
    </script>

    <div class="table-container clearfix">
        <table class="table table-list hidden" id="tableDomainPricing">
            <thead>
            <tr>
                <th>{lang key='domaintld'}</th>
                <th>{lang key='category'}</th>
                <th>{lang key='pricing.register'}</th>
                <th>{lang key='pricing.transfer'}</th>
                <th>{lang key='pricing.renewal'}</th>
                <th>{lang key='gracePeriod'}</th>
                <th>{lang key='redemptionPeriod'}</th>
            </tr>
            </thead>
            <tbody>
            {foreach $pricing as $extension => $data}
                <tr>
                    <td>
                        {$extension}
                        {if $data.group}
                            <span class="tld-sale-group tld-sale-group-{$data.group}">
                                {$data.group}!
                            </span>
                        {/if}
                    </td>
                    <td>
                        {$data.categories[0]}
                        <span class="hidden">
                            {foreach $data.categories as $category}
                                {$category}
                            {/foreach}
                        </span>
                    </td>
                    {foreach $data.register as $years => $price}
                        <td>
                            {if $price >= 0}
                                {$price}<br>
                                <small>{$years} {if $years > 1}{lang key="orderForm.years"}{else}{lang key="orderForm.year"}{/if}</small>
                            {else}
                                <small>{lang key="domainregnotavailable"}</small>
                            {/if}
                        </td>
                        {break}
                    {foreachelse}
                        <td>-</td>
                    {/foreach}
                    {foreach $data.transfer as $years => $price}
                        <td>
                            {if $price >= 0}
                                {$price}<br>
                                <small>{$years} {if $years > 1}{lang key="orderForm.years"}{else}{lang key="orderForm.year"}{/if}</small>
                            {else}
                                <small>{lang key="domainregnotavailable"}</small>
                            {/if}
                        </td>
                        {break}
                    {foreachelse}
                        <td>-</td>
                    {/foreach}
                    {foreach $data.renew as $years => $price}
                        <td>
                            {if $price >= 0}
                                {$price}<br>
                                <small>{$years} {if $years > 1}{lang key="orderForm.years"}{else}{lang key="orderForm.year"}{/if}</small>
                            {else}
                                <small>{lang key="domainregnotavailable"}</small>
                            {/if}
                        </td>
                        {break}
                    {foreachelse}
                        <td>-</td>
                    {/foreach}
                    <td>
                        {if is_null($data.grace_period)}
                            -
                        {else}
                            {$data.grace_period.days} {lang key='domainrenewalsdays'}<br>
                            <small>({$data.grace_period.price})</small>
                        {/if}
                    </td>
                    <td>
                        {if is_null($data.redemption_period)}
                            -
                        {else}
                            {$data.redemption_period.days} {lang key='domainrenewalsdays'}<br>
                            <small>({$data.redemption_period.price})</small>
                        {/if}
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="7">{lang key="pricing.noExtensionsDefined"}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
        <div class="text-center" id="tableLoading">
            <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
        </div>
    </div>

</div>
