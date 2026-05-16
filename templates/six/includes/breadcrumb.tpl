<ol class="breadcrumb" style="background: transparent; padding: 0; margin: 0; font-size: 13px;">
    {foreach $breadcrumb as $item}
        <li{if $item@last} class="active" style="color: var(--theme2);"{/if}>
            {if !$item@last}<a href="{$item.link}" style="color: rgba(255,255,255,0.7);">{/if}
            {$item.label}
            {if !$item@last}</a>{/if}
        </li>
    {/foreach}
</ol>
