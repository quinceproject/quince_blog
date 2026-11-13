{**
 * Zajawki bloga: osadzenie gdziekolwiek, każde umieszczenie z panelu
 * (Blog → Teasers) pod WŁASNYM hookiem, bez parametru:
 *   {hook h='displayQuinceBlogTeasersKLUCZ'}
 *
 * Znaczniki i klasy 1:1 z `makiety/zajawki-blogowe.html`. Trzy układy,
 * przełączane $qbl_teasers_layout: `hero` (Układ 1, q-posts--feature),
 * `row` (Układ 2, q-posts--grid), `strip` (Układ 3, q-posts--rail).
 *}
<div class="q-posts-head">
  <h2>{$qbl_teasers_title|escape:'html'}</h2>
  <a class="q-posts-more" href="{$qbl_teasers_all_url|escape:'html'}">{l s='See all posts' d='Modules.Quinceblog.Shop'} →</a>
</div>

{if $qbl_teasers_layout == 'hero' && $qbl_teasers_posts|count > 1}
  {assign var='_hero' value=$qbl_teasers_posts|@reset}
  <div class="q-posts--feature">
    <article class="q-post q-post--big">
      <a class="q-post__media" href="{$_hero.url}" aria-label="{$_hero.title|escape:'html'}">
        {if $_hero.cover_url}<img class="q-post__img" src="{$_hero.cover_url}" alt="{$_hero.title|escape:'html'}" loading="lazy">{/if}
        {if !empty($_hero.categories)}
          <span class="q-badge q-badge--post q-post__cat--over"{if !empty($_hero.categories[0].color)} style="--q-badge-post-bg: {$_hero.categories[0].color|escape:'html'}"{/if}>{$_hero.categories[0].name|escape:'html'}</span>
        {/if}
      </a>
      <div class="q-post__body">
        <h3 class="q-post__title"><a href="{$_hero.url}">{$_hero.title|escape:'html'}</a></h3>
        {if $_hero.excerpt}<p class="q-post__lead">{$_hero.excerpt|strip_tags|truncate:160:'…'|escape:'html'}</p>{/if}
        {if $_hero.date_pub}
          <span class="q-post__date">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/></svg>
            {$_hero.date_pub|date_format:'%d.%m.%Y'}
          </span>
        {/if}
      </div>
    </article>

    <div class="q-posts__side">
      {foreach $qbl_teasers_posts as $p name=qblTeasers}
        {if !$smarty.foreach.qblTeasers.first}
          <article class="q-post q-post--row">
            <a class="q-post__media" href="{$p.url}" aria-label="{$p.title|escape:'html'}">
              {if $p.cover_url}<img class="q-post__img" src="{$p.cover_url}" alt="{$p.title|escape:'html'}" loading="lazy">{/if}
            </a>
            <div class="q-post__body">
              {if !empty($p.categories)}
                <span class="q-badge q-badge--post"{if !empty($p.categories[0].color)} style="--q-badge-post-bg: {$p.categories[0].color|escape:'html'}"{/if}>{$p.categories[0].name|escape:'html'}</span>
              {/if}
              <h3 class="q-post__title"><a href="{$p.url}">{$p.title|escape:'html'}</a></h3>
              {if $p.date_pub}
                <span class="q-post__date">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/></svg>
                  {$p.date_pub|date_format:'%d.%m.%Y'}
                </span>
              {/if}
            </div>
          </article>
        {/if}
      {/foreach}
    </div>
  </div>

{elseif $qbl_teasers_layout == 'strip'}
  <div class="q-posts--rail" style="--q-posts-cols: {$qbl_teasers_columns|intval};">
    {foreach $qbl_teasers_posts as $p}
      <article class="q-post">
        <a class="q-post__media" href="{$p.url}" aria-label="{$p.title|escape:'html'}">
          {if $p.cover_url}<img class="q-post__img" src="{$p.cover_url}" alt="{$p.title|escape:'html'}" loading="lazy">{/if}
        </a>
        <div class="q-post__body">
          {if !empty($p.categories)}<span class="q-post__cat q-post__date">{$p.categories[0].name|escape:'html'}</span>{/if}
          <h3 class="q-post__title"><a href="{$p.url}">{$p.title|escape:'html'}</a></h3>
          {if $p.date_pub}<span class="q-post__date">{$p.date_pub|date_format:'%d.%m.%Y'}</span>{/if}
        </div>
      </article>
    {/foreach}
  </div>

{else}
  <div class="q-posts--grid" style="--q-posts-cols: {$qbl_teasers_columns|intval};">
    {foreach $qbl_teasers_posts as $p}
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
    {/foreach}
  </div>
{/if}
