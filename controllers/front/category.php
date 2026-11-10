<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use Quince\Blog\Repository;
use Quince\Blog\Seo;

class Quince_BlogCategoryModuleFrontController extends ModuleFrontController
{
    /** @var array<string,mixed>|null */
    private $blogCategory;

    /** Ten sam zabieg co w `post.php`: natywny blok okruszków woła tę metodę
     * PRZED initContent(), więc $this->blogCategory jest jeszcze puste —
     * kategorię trzeba znaleźć od razu po slug z URL-a. Bez tego okruszek
     * pomija "Blog" i skacze od razu z Home do nazwy kategorii. */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        if (empty($this->blogCategory)) {
            $slug = (string) Tools::getValue('slug', '');
            if ($slug !== '') {
                $this->blogCategory = Repository::findCategoryBySlug(
                    $slug,
                    (int) $this->context->language->id,
                    (int) $this->context->shop->id
                );
            }
        }

        $breadcrumb['links'][] = ['title' => $this->trans('Blog', [], 'Modules.Quinceblog.Shop'), 'url' => Seo::listUrl($this->context)];

        if (!empty($this->blogCategory['name'])) {
            $breadcrumb['links'][] = [
                'title' => $this->blogCategory['name'],
                'url' => Seo::categoryUrl($this->blogCategory['slug'], $this->context),
            ];
        }

        return $breadcrumb;
    }

    public function initContent()
    {
        $slug = (string) Tools::getValue('slug', '');
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        // `getBreadcrumbLinks()` już ją znalazł (woła go natywny blok
        // okruszków przed initContent()) — drugie zapytanie o to samo
        // byłoby zbędne.
        $category = $this->blogCategory ?? ($slug !== '' ? Repository::findCategoryBySlug($slug, $idLang, $idShop) : null);
        if (!$category) {
            header('HTTP/1.1 404 Not Found');
            $this->context->controller = new PageNotFoundController();
            $this->context->controller->initContent();
            exit;
        }
        $this->blogCategory = $category;

        parent::initContent();

        $perPage = max(3, (int) Configuration::get('QUINCE_BLOG_POSTS_PER_PAGE') ?: 12);
        $page = max(1, (int) Tools::getValue('page', 1));
        $offset = ($page - 1) * $perPage;
        $idCategory = (int) $category['id_category'];

        $posts = Repository::listPosts([
            'id_shop' => $idShop,
            'lang_id' => $idLang,
            'status' => 'published',
            'id_category' => $idCategory,
            'limit' => $perPage,
            'offset' => $offset,
        ]);
        $total = Repository::countPosts([
            'id_shop' => $idShop,
            'lang_id' => $idLang,
            'status' => 'published',
            'id_category' => $idCategory,
        ]);

        // Jedno zapytanie o wszystkie okładki strony, nie jedno na wpis.
        $images = Repository::getImagesByIds(array_column($posts, 'featured_id_image'));

        foreach ($posts as &$p) {
            $idPost = (int) $p['id_post'];
            $p['url'] = Seo::postUrl($p['slug'], $this->context);
            $p['cover_url'] = $this->resolveCover($p['featured_id_image'] ?? null, $images);
            $p['categories'] = Repository::getPostCategories($idPost, $idLang);
        }
        unset($p);

        $meta = Seo::buildCategoryMeta($category, $this->context);
        $this->context->smarty->assign([
            'blog_category' => $category,
            'blog_posts' => $posts,
            'blog_pagination' => Seo::buildPagination($page, $perPage, $total, Seo::categoryUrl($slug, $this->context)),
            'blog_meta' => $meta,
            'meta_title' => $meta['title'],
            'meta_description' => $meta['description'],
        ]);
        Seo::applyPageMeta($this->context, $meta['title'], $meta['description']);

        $this->setTemplate('module:quince_blog/views/templates/front/category.tpl');
    }

    /** @param array<int,array<string,mixed>> $images zdjęcia pod id_image, z Repository::getImagesByIds() */
    private function resolveCover(?int $featuredIdImage, array $images): string
    {
        if ($featuredIdImage && isset($images[$featuredIdImage])) {
            return Seo::imageUrl($images[$featuredIdImage]['filename']);
        }

        return '';
    }

    /**
     * Niektóre moduły GTM/analityczne szukają `getCategory()` na kontrolerze
     * strony kategorii, żeby wzbogacić zdarzenie o dane kategorii — ten sam
     * zwyczaj, co we flavor_blog.
     */
    public function getCategory()
    {
        if (empty($this->blogCategory)) {
            return null;
        }
        $c = $this->blogCategory;
        $obj = new \stdClass();
        $obj->id = (int) $c['id_category'];
        $obj->id_category = (int) $c['id_category'];
        $obj->id_parent = (int) ($c['id_parent'] ?? 0);
        $obj->name = (string) ($c['name'] ?? '');
        $obj->link_rewrite = (string) ($c['slug'] ?? '');
        $obj->description = (string) ($c['description'] ?? '');
        $obj->meta_title = (string) ($c['meta_title'] ?? '');
        $obj->meta_description = (string) ($c['meta_description'] ?? '');
        $obj->active = !empty($c['active']);
        $obj->level_depth = 1;
        $obj->is_blog_category = true;

        return $obj;
    }
}
