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

class Quince_BlogTagModuleFrontController extends ModuleFrontController
{
    /** @var array<string,mixed>|null */
    private $blogTag;

    /** Ten sam zabieg co w `post.php`/`category.php`: natywny blok okruszków
     * woła tę metodę PRZED initContent(), więc tag trzeba znaleźć od razu
     * po slug z URL-a. */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        if (empty($this->blogTag)) {
            $slug = (string) Tools::getValue('slug', '');
            if ($slug !== '') {
                $this->blogTag = Repository::findTagBySlug(
                    $slug,
                    (int) $this->context->language->id,
                    (int) $this->context->shop->id
                );
            }
        }

        $breadcrumb['links'][] = ['title' => $this->trans('Blog', [], 'Modules.Quinceblog.Shop'), 'url' => Seo::listUrl($this->context)];

        if (!empty($this->blogTag['name'])) {
            $breadcrumb['links'][] = [
                'title' => '#' . $this->blogTag['name'],
                'url' => Seo::tagUrl($this->blogTag['slug'], $this->context),
            ];
        }

        return $breadcrumb;
    }

    public function initContent()
    {
        $slug = (string) Tools::getValue('slug', '');
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        $tag = $slug !== '' ? Repository::findTagBySlug($slug, $idLang, $idShop) : null;
        if (!$tag) {
            header('HTTP/1.1 404 Not Found');
            $this->context->controller = new PageNotFoundController();
            $this->context->controller->initContent();
            exit;
        }
        $this->blogTag = $tag;

        parent::initContent();

        $perPage = max(3, (int) Configuration::get('QUINCE_BLOG_POSTS_PER_PAGE') ?: 12);
        $page = max(1, (int) Tools::getValue('page', 1));
        $offset = ($page - 1) * $perPage;
        $idTag = (int) $tag['id_tag'];

        $posts = Repository::listPosts([
            'id_shop' => $idShop,
            'lang_id' => $idLang,
            'status' => 'published',
            'id_tag' => $idTag,
            'limit' => $perPage,
            'offset' => $offset,
        ]);
        $total = Repository::countPosts([
            'id_shop' => $idShop,
            'lang_id' => $idLang,
            'status' => 'published',
            'id_tag' => $idTag,
        ]);

        foreach ($posts as &$p) {
            $idPost = (int) $p['id_post'];
            $p['url'] = Seo::postUrl($p['slug'], $this->context);
            $p['cover_url'] = $this->resolveCover($idPost, $p['featured_id_image'] ?? null);
            $p['categories'] = Repository::getPostCategories($idPost, $idLang);
        }
        unset($p);

        $title = sprintf($this->trans('#%s — Blog', [], 'Modules.Quinceblog.Shop'), $tag['name']);
        $this->context->smarty->assign([
            'blog_tag' => $tag,
            'blog_posts' => $posts,
            'blog_pagination' => Seo::buildPagination($page, $perPage, $total, Seo::tagUrl($slug, $this->context)),
            'meta_title' => $title,
            'meta_description' => sprintf($this->trans('Posts tagged with %s', [], 'Modules.Quinceblog.Shop'), $tag['name']),
        ]);

        $this->setTemplate('module:quince_blog/views/templates/front/tag.tpl');
    }

    private function resolveCover(int $idPost, ?int $featuredIdImage): string
    {
        if ($featuredIdImage) {
            $img = Repository::getImageById($featuredIdImage);
            if ($img) {
                return Seo::imageUrl($img['filename']);
            }
        }

        return '';
    }
}
