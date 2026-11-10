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

class Quince_BlogListModuleFrontController extends ModuleFrontController
{
    /** Domyślny okruszek PS9 dla modułowego kontrolera pokazuje samo
     * "Strona główna" jako bieżącą pozycję — nie dokłada tytułu strony. */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = ['title' => $this->trans('Blog', [], 'Modules.Quinceblog.Shop'), 'url' => Seo::listUrl($this->context)];

        return $breadcrumb;
    }

    public function initContent()
    {
        parent::initContent();

        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;
        $perPage = max(3, (int) Configuration::get('QUINCE_BLOG_POSTS_PER_PAGE') ?: 12);
        $page = max(1, (int) Tools::getValue('page', 1));
        $offset = ($page - 1) * $perPage;

        $posts = Repository::listPosts([
            'id_shop' => $idShop,
            'lang_id' => $idLang,
            'status' => 'published',
            'limit' => $perPage,
            'offset' => $offset,
        ]);
        $total = Repository::countPosts(['id_shop' => $idShop, 'lang_id' => $idLang, 'status' => 'published']);

        $meta = Seo::buildListMeta($this->context);
        $this->context->smarty->assign([
            'blog_posts' => $this->decoratePosts($posts, $idLang, $idShop),
            'blog_categories' => $this->decorateCategories(Repository::listCategories($idLang, $idShop, true)),
            'blog_pagination' => Seo::buildPagination($page, $perPage, $total, Seo::listUrl($this->context)),
            'blog_meta' => $meta,
            'meta_title' => $meta['title'],
            'meta_description' => $meta['description'],
        ]);
        Seo::applyPageMeta($this->context, $meta['title'], $meta['description']);

        $this->setTemplate('module:quince_blog/views/templates/front/list.tpl');
    }

    /** @return array<int,array<string,mixed>> */
    private function decoratePosts(array $posts, int $idLang, int $idShop): array
    {
        // Jedno zapytanie o wszystkie okładki strony, nie jedno na wpis.
        $images = Repository::getImagesByIds(array_column($posts, 'featured_id_image'));

        foreach ($posts as &$p) {
            $idPost = (int) $p['id_post'];
            $p['url'] = Seo::postUrl($p['slug'], $this->context);
            $p['cover_url'] = $this->resolveCover($p['featured_id_image'] ?? null, $images);
            $p['categories'] = Repository::getPostCategories($idPost, $idLang);
        }
        unset($p);

        return $posts;
    }

    /** @param array<int,array<string,mixed>> $images zdjęcia pod id_image, z Repository::getImagesByIds() */
    private function resolveCover(?int $featuredIdImage, array $images): string
    {
        if ($featuredIdImage && isset($images[$featuredIdImage])) {
            return Seo::imageUrl($images[$featuredIdImage]['filename']);
        }

        return '';
    }

    /** @return array<int,array<string,mixed>> */
    private function decorateCategories(array $categories): array
    {
        foreach ($categories as &$c) {
            $c['url'] = Seo::categoryUrl($c['slug'], $this->context);
        }
        unset($c);

        return $categories;
    }
}
