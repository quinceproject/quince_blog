{**
 * Quince Blog — archiwum tagu.
 *}
{extends file='page.tpl'}

{block name='breadcrumb'}
  <div class="q-container q-blog-crumbs">
    {include file='_partials/breadcrumb.tpl'}
  </div>
{/block}

{block name='page_header_container'}
  <h1 class="q-blog-title">#{$blog_tag.name|escape:'html'}</h1>
{/block}

{block name='page_content'}
  {if $blog_posts}
    <div class="q-posts--grid" style="--q-posts-cols: 3;">
      {foreach $blog_posts as $p}
        {include file='module:quince_blog/views/templates/front/_partials/post-card.tpl' p=$p}
      {/foreach}
    </div>
    {include file='_partials/pagination.tpl' pagination=$blog_pagination}
  {else}
    <div class="q-blog-empty">
      <p>{l s='No posts with this tag yet.' d='Modules.Quinceblog.Shop'}</p>
    </div>
  {/if}
{/block}
