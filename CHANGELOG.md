# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.4] - 2026-11-13

### Fixed

- Blog links in the default language follow the shop setting for the language prefix in
  the address. On PrestaShop 9.2 the prefix can be turned off for the default language,
  and the blog added it anyway, so the same post was reachable under two addresses.

### Changed

- Code comments tidied up. No change in how the shop works.

## [0.4.3] - 2026-11-13

### Fixed

- Blog posts output their structured data again (BlogPosting, breadcrumbs, FAQ). On
  PrestaShop 9.2 the theme printed only the shop's own data and dropped the blog's.
- Blog links in a language other than the default one now carry the language prefix in
  the address, so they no longer lead to a "page not found".
- The alternate language links (hreflang) of posts and categories point to the slug of
  each language, not to the slug of the current one.

## [0.4.2] - 2026-11-10

### Added

- SEO fields on the blog category edit screen: meta title and meta description, per
  language.

### Fixed

- Saving a blog category no longer swaps its languages. The edit screen showed the text
  of one language in the field of another, so every save mixed them up.
- Saving a blog category no longer clears its meta title and meta description.

### Changed

- The blog settings call the post list the blog home page and point to the own SEO
  fields of categories and posts.

## [0.4.1] - 2026-11-10

### Added

- Settings: the title and description of the post list and the title of a tag page,
  per language, under "Blog in search results". The RSS feed uses the same title and
  description.

### Fixed

- Blog pages show their own title and description in the browser tab and in search
  results. Until now the theme printed the shop name on every blog page.

### Changed

- Polish translation completed: the texts added since the previous translation are now translated.
- Texts no longer use long dashes. An empty value in a table shows a hyphen, and
  subcategories are indented with a hyphen.

## [0.4.0] - 2026-11-10

First public release.

### Added

- Posts, categories and tags, per shop and per language, edited in the back office
  with the PrestaShop rich text editor.
- Post fields: excerpt, featured image (JPG, PNG, WEBP or AVIF), meta title, meta
  description, robots, publish date for scheduled posts, featured products and a
  list of frequently asked questions.
- Preview of unpublished posts through a private link.
- Blog pages on clean addresses under `/blog/`: the post list, a post, a category,
  a tag and an RSS feed at `/blog/feed.xml`.
- Meta tags, hreflang links and JSON-LD for posts, breadcrumbs and FAQ.
- Teaser placements in three layouts, each with its own hook to insert anywhere in
  the theme.
- Settings for the post page width, the cover image width and the number of related
  posts.
- Polish translation.
- Declared dependency on Quince Engine.
