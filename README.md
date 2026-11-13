# Quince Blog

**Multilingual, multistore blog for PrestaShop 9.2.**

Quince Blog adds a blog to the shop: posts, categories and tags, written in the back
office, with their own pages, an RSS feed and the structured data search engines
read. Each shop in a multistore installation keeps its own posts, so two brands in
one installation never show each other's articles. Teasers bring the latest posts
anywhere in the theme, such as the home page.

> Quince Blog is a free, open-source module (OSL-3.0). There is nothing to buy.
> If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Posts, categories and tags.** Each post, category and tag belongs to one shop
  and has its own text in every language. Categories can be nested and can have
  their own colour, shown as the category badge on teasers.
- **A full post editor.** Title, address, excerpt and content in the PrestaShop rich
  text editor, a featured image (JPG, PNG, WEBP or AVIF, up to 5 MB) with a caption,
  categories and tags, featured products from the catalogue and a list of frequently
  asked questions shown under the post.
- **Scheduled publishing and preview.** A post is a draft, published or scheduled for
  a publish date. An unpublished post can be previewed through a private link.
- **Post list with bulk actions.** Filter by category, status or title, then publish,
  set as draft or delete the selected posts at once.
- **Blog pages on clean addresses.** The post list at `/blog`, posts at
  `/blog/{post}`, categories at `/blog/category/{category}`, tags at
  `/blog/tag/{tag}` and an RSS 2.0 feed at `/blog/feed.xml`, for every shop and
  every language.
- **Built for search engines.** Meta title, meta description and robots per post,
  hreflang links between language versions, and JSON-LD for the post, the
  breadcrumbs and the FAQ. The FAQ data matches the questions shown on the page.
- **Teasers anywhere.** Each teaser placement gets its own hook, with no parameters,
  to insert anywhere in the theme. A placement has a heading, an optional category
  filter and one of three layouts: a featured post with a list, an equal row of
  tiles or a compact strip.
- **Post page layout.** Set how many grid columns the text and the cover image take,
  and how many related posts appear under the post.
- **Multilingual.** English base with a Polish translation, on PrestaShop's standard
  translation system.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | required |
| Quince theme | required for the look of the blog pages |

Quince Engine hosts the module's settings screens. The module declares it as a
dependency, so PrestaShop refuses to install Quince Blog without it. The styles of the
blog pages come with the Quince theme, which is published separately.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_blog.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_blog/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_blog` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince Blog and click **Install**.

**From Quince Engine**

With Quince Engine installed, **Quince project → Quince modules** installs and updates
this module from the Quince release list, without downloading anything by hand.

**With Composer**

A PrestaShop shop ships without a `composer.json` of its own, so Quince packages are
installed from a separate folder next to the shop. The one-time setup of that folder is
described in the [Quince Engine README](https://github.com/quinceproject/quince_engine#installation).
Then, from that folder:

```sh
composer require "quinceproject/blog:*"
php ../bin/console prestashop:module install quince_blog
```

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_blog/releases).

Uninstalling the module removes all blog posts, categories, tags and settings.

## Configuration

Open **Quince project → Quince Engine** in the back office menu and choose **Blog**
in the **Modules** group of the sidebar. The screens:

1. **Posts.** Write and publish posts.
2. **Categories** and **Tags.** Group the posts.
3. **Teasers.** Add a placement, pick its layout, then insert its hook in the theme,
   for example as a module block on the home page.
4. **Settings.** The post page width, the cover image width and the number of related
   posts.

## Architecture

- **Symfony controllers and Twig** for the back office screens, inside the Quince
  Engine shell.
- **Front controllers** `list`, `post`, `category`, `tag` and `feed`, routed to clean
  addresses through the `moduleRoutes` hook.
- **`Repository`** reads and writes posts, categories and tags in the module's own
  `quince_blog_*` tables.
- **`Seo`** builds meta tags, hreflang links and JSON-LD.
- **Teaser hooks** are named after their placement, `displayQuinceBlogTeasers` plus
  the placement key, and are answered through `__call()`.
- **Images** are stored in the shop's `img/blog/` folder.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_blog.git
cd quince_blog
composer install
```

The folder must be called `quince_blog`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_blog/` folder **with its `vendor/`
directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
