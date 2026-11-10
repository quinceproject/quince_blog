# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
