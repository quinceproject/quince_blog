{**
 * Jedna karta wpisu w siatce listy/kategorii/tagu. Te same znaczniki i klasy
 * co karta w `hook/teasers.tpl` (układ „row"): jeden wygląd karty wpisu
 * w całym sklepie.
 *}
<article class="q-post">
  <a class="q-post__media" href="{$p.url}" aria-label="{$p.title|escape:'html'}">
    {if $p.cover_url}<img class="q-post__img" src="{$p.cover_url}" alt="{$p.title|escape:'html'}" loading="lazy">{/if}
    {if !empty($p.categories)}
      <span class="q-badge q-badge--post q-post__cat--over"{if !empty($p.categories[0].color)} style="--q-badge-post-bg: {$p.categories[0].color|escape:'html'}"{/if}>{$p.categories[0].name|escape:'html'}</span>
    {/if}
  </a>
  <div class="q-post__body">
    <h3 class="q-post__title"><a href="{$p.url}">{$p.title|escape:'html'}</a></h3>
    {if $p.excerpt}<p class="q-post__lead">{$p.excerpt|strip_tags|truncate:140:'…'|escape:'html'}</p>{/if}
    {if $p.date_pub}
      <span class="q-post__date">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/></svg>
        {$p.date_pub|date_format:'%d.%m.%Y'}
      </span>
    {/if}
  </div>
</article>
