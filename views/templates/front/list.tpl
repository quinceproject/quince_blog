{**
 * Quince Blog: lista wpisów. Ten sam bazowy układ co mapa strony
 * (`cms/sitemap.tpl`), siatka kart ta sama co w zajawkach (hook/teasers.tpl).
 *}
{extends file='page.tpl'}

{block name='breadcrumb'}
  <div class="q-container q-blog-crumbs">
    {include file='_partials/breadcrumb.tpl'}
  </div>
{/block}

{block name='page_header_container'}
  <h1 class="q-blog-title">{l s='Blog' d='Modules.Quinceblog.Shop'}</h1>
{/block}

{block name='page_content'}
  {if $blog_categories}
    <nav class="q-posts-cats" aria-label="{l s='Blog categories' d='Modules.Quinceblog.Shop'}">
      <span class="q-chip q-chip--active" aria-current="page">{l s='All' d='Modules.Quinceblog.Shop'}</span>
      {foreach $blog_categories as $cat}
        <a class="q-chip" href="{$cat.url|escape:'html'}">{$cat.name|escape:'html'}</a>
      {/foreach}
    </nav>
  {/if}

  {if $blog_posts}
    <div class="q-posts--grid" style="--q-posts-cols: 3;">
      {foreach $blog_posts as $p}
        {include file='module:quince_blog/views/templates/front/_partials/post-card.tpl' p=$p}
      {/foreach}
    </div>
    {include file='_partials/pagination.tpl' pagination=$blog_pagination}
  {else}
    <div class="q-blog-empty">
      <p>{l s='No posts yet.' d='Modules.Quinceblog.Shop'}</p>
    </div>
  {/if}
{/block}
