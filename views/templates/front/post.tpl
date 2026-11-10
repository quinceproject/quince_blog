{**
 * Quince Blog — pojedynczy wpis.
 *}
{extends file='page.tpl'}

{block name='head' append}
  <meta property="og:type" content="article">
  {if $blog_meta.og_image}<meta property="og:image" content="{$blog_meta.og_image}">{/if}
  {foreach $blog_hreflangs as $hl}
    <link rel="alternate" hreflang="{$hl.hreflang}" href="{$hl.url}">
  {/foreach}
{/block}

{block name='head_microdata_special'}
  <script type="application/ld+json">{$blog_jsonld_post nofilter}</script>
  <script type="application/ld+json">{$blog_jsonld_breadcrumb nofilter}</script>
  {if $blog_jsonld_faq}<script type="application/ld+json">{$blog_jsonld_faq nofilter}</script>{/if}
{/block}

{block name='breadcrumb'}
  <div class="q-container q-blog-crumbs">
    {include file='_partials/breadcrumb.tpl'}
  </div>
{/block}

{block name='page_header_container'}{/block}

{block name='page_content'}
  <article class="q-post-single" style="--q-blog-content-width: {$blog_content_width|intval}px; --q-blog-cover-width: {$blog_cover_width|intval}px; --q-blog-content-cols: {$blog_content_cols|intval};">
    <header class="q-post-single__head">
      <h1 class="q-blog-title">{$blog_post.title|escape:'html'}</h1>
      <div class="q-post-single__meta">
        {if $blog_post.date_pub}
          <time class="q-post__date" datetime="{$blog_post.date_pub|date_format:'%Y-%m-%d'}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/></svg>
            {$blog_post.date_pub|date_format:'%d.%m.%Y'}
          </time>
        {/if}
        {foreach $blog_post.categories as $cat}
          <a class="q-post__date q-post-single__meta-category" href="{$cat.url|escape:'html'}">{$cat.name|escape:'html'}</a>
        {/foreach}
        {if $blog_post.view_count}
          <span class="q-post__date">{$blog_post.view_count} {l s='views' d='Modules.Quinceblog.Shop'}</span>
        {/if}
      </div>
    </header>

    {if $blog_post.cover_url}
      <figure class="q-post-single__cover">
        <img src="{$blog_post.cover_url}" alt="{$blog_post.title|escape:'html'}" loading="eager">
        {if $blog_post.cover_caption}
          <figcaption class="q-post-single__cover-caption">{$blog_post.cover_caption|escape:'html'}</figcaption>
        {/if}
      </figure>
    {/if}

    <div class="q-post-single__content rich-text">
      {$blog_post.content nofilter}
    </div>

    {if $blog_post.gallery}
      <div class="q-posts--grid" style="--q-posts-cols: 3;">
        {foreach $blog_post.gallery as $img}
          <img src="{$img.url}" alt="" loading="lazy" style="border-radius:var(--q-radius-md); width:100%; height:auto">
        {/foreach}
      </div>
    {/if}

    {if $blog_featured_products}
      {if $blog_post.featured_products_heading}
        {assign var='blogFeaturedHeading' value=$blog_post.featured_products_heading|escape:'html'}
      {else}
        {assign var='blogFeaturedHeading' value={l s='Featured products' d='Modules.Quinceblog.Shop'}}
      {/if}
      <div class="q-post-single__featured">
        {include file='catalog/_partials/product-carousel.tpl'
          qProdukty=$blog_featured_products
          qTytul=$blogFeaturedHeading
          qIkonaSvg=$blog_featured_icon_svg
          qId='blog-featured'
          qKontener=false
          qIle=$blog_featured_products|@count}
      </div>
    {/if}

    {if $blog_faq_items}
      <section class="q-post-single__faq">
        <header class="q-post-single__faq-head">
          <h2 class="q-post-single__faq-title">
            {if $blog_faq_icon_svg}
              <svg class="q-post-single__faq-ico" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">{$blog_faq_icon_svg nofilter}</svg>
            {/if}
            {if $blog_post.faq_heading}{$blog_post.faq_heading|escape:'html'}{else}{l s='Frequently Asked Questions' d='Modules.Quinceblog.Shop'}{/if}
          </h2>
        </header>
        <div class="q-accordion">
          {foreach $blog_faq_items as $faq name=blogFaq}
            <details class="q-accordion__item" id="q-post-faq-{$smarty.foreach.blogFaq.index}"{if $smarty.foreach.blogFaq.first} open{/if}>
              <summary class="q-accordion__header">
                <h2 class="q-accordion__title">{$faq.question|escape:'html'}</h2>
                <svg class="q-accordion__icon" width="16" height="16" viewBox="0 0 16 16"
                     fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
              </summary>
              <div class="q-accordion__body">{$faq.answer nofilter}</div>
            </details>
          {/foreach}
        </div>
      </section>
    {/if}

    {if $blog_prev_post || $blog_next_post}
      <nav class="q-post-single__nav" aria-label="{l s='More posts' d='Modules.Quinceblog.Shop'}">
        {if $blog_prev_post}<a class="q-btn q-btn--secondary" href="{$blog_prev_post.url}">← {$blog_prev_post.title|escape:'html'}</a>{else}<span></span>{/if}
        {if $blog_next_post}<a class="q-btn q-btn--secondary" href="{$blog_next_post.url}">{$blog_next_post.title|escape:'html'} →</a>{/if}
      </nav>
    {/if}

    {if $blog_related}
      <div class="q-posts-head">
        <h2>{l s='More from the blog' d='Modules.Quinceblog.Shop'}</h2>
        <a class="q-posts-more" href="{$blog_list_url|escape:'html'}">{l s='See all posts' d='Modules.Quinceblog.Shop'} →</a>
      </div>
      <div class="q-posts--grid" style="--q-posts-cols: {$blog_related|@count};">
        {foreach $blog_related as $p}
          {include file='module:quince_blog/views/templates/front/_partials/post-card.tpl' p=$p}
        {/foreach}
      </div>
    {/if}
  </article>
{/block}
