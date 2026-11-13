<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Blog\Controller\Admin;

use Configuration;
use Quince\Blog\ImageStore;
use Quince\Blog\Repository;
use Quince\Engine\Ui\IconLibrary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Wpisy blogowe: lista z filtrem i zbiorczymi akcjami, edycja.
 */
class PostsController extends BlogBaseController
{
    public function indexAction(Request $request): Response
    {
        $idShop = (int) $this->getContext()->shop->id;
        $idLang = (int) $this->getContext()->language->id;

        $filters = [
            'search' => trim((string) $request->query->get('search', '')),
            'status' => (string) $request->query->get('status', ''),
            'id_category' => (int) $request->query->get('id_category', 0),
        ];
        $sort = (string) $request->query->get('sort', 'date_pub');
        $dir = strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;

        $filter = array_filter([
            'id_shop' => $idShop,
            'lang_id' => $idLang,
            'status' => $filters['status'] ?: null,
            'id_category' => $filters['id_category'] ?: null,
            'search' => $filters['search'] ?: null,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ], static fn ($v) => $v !== null);
        $filter['id_shop'] = $idShop;
        $filter['lang_id'] = $idLang;

        $rows = $this->sortRows(Repository::listPosts($filter), $sort, $dir);
        $total = Repository::countPosts($filter);

        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/posts.html.twig', $this->chrome('quince_blog_posts') + [
            'rows' => $rows,
            'categories' => Repository::listCategories($idLang, $idShop),
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'pages' => (int) ceil($total / $perPage),
            'total' => $total,
        ]);
    }

    public function editAction(Request $request, int $id = 0): Response
    {
        $idShop = (int) $this->getContext()->shop->id;
        $idLang = (int) $this->getContext()->language->id;
        $languages = $this->languages();

        $post = $id > 0 ? Repository::getPostById($id, $idShop) : null;
        if ($id > 0 && $post === null) {
            $this->addFlash('error', $this->trans('That post does not exist in this shop.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_posts');
        }

        $productIds = $post['product_ids'] ?? [];
        $products = $productIds ? $this->productNames($productIds) : [];

        $featuredImageUrl = '';
        $featuredImageCaption = [];
        if ($post) {
            foreach ($post['images'] ?? [] as $image) {
                if ((int) $image['id_image'] === (int) $post['featured_id_image']) {
                    $featuredImageUrl = (new ImageStore())->url((string) $image['filename']);
                    $featuredImageCaption = Repository::getImageCaptions((int) $image['id_image']);
                    break;
                }
            }
        }

        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/post-edit.html.twig', $this->chrome('quince_blog_posts') + [
            'post' => $post,
            'languages' => $languages,
            'categories' => Repository::listCategories($idLang, $idShop),
            'tags' => Repository::listTags($idLang, $idShop),
            'products' => $products,
            'featuredImageUrl' => $featuredImageUrl,
            'featuredImageCaption' => $featuredImageCaption,
            'previewToken' => $post ? Repository::previewToken((int) $post['id_post']) : '',
            'shopUrl' => rtrim($this->getContext()->shop->getBaseURL(true), '/'),
            'routePrefix' => trim((string) Configuration::get('QUINCE_BLOG_ROUTE_PREFIX')) ?: 'blog',
            'featuredIconSvg' => IconLibrary::svg((string) ($post['featured_products_icon'] ?? '')),
            'faqIconSvg' => IconLibrary::svg((string) ($post['faq_icon'] ?? '')),
            'iconsRoute' => 'quince_icons_search',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_posts_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_posts');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $idPost = (int) $request->request->get('id_post', 0);
        $values = $request->request->all();
        $languages = $this->languages();

        $base = [
            'id_post' => $idPost ?: null,
            'id_shop' => $idShop,
            'status' => in_array($values['status'] ?? '', ['draft', 'published', 'scheduled'], true) ? $values['status'] : 'draft',
            'date_pub' => !empty($values['date_pub']) ? (string) $values['date_pub'] : null,
            'author_id_employee' => (int) $this->getContext()->employee->id ?: null,
            'featured_products_max' => (int) ($values['featured_products_max'] ?? 0),
            'featured_products_icon' => trim((string) ($values['featured_products_icon'] ?? '')) ?: null,
            'faq_icon' => trim((string) ($values['faq_icon'] ?? '')) ?: null,
        ];

        $byLang = [];
        foreach ($languages as $lang) {
            $idLang = (int) $lang['id_lang'];
            $byLang[$idLang] = (array) ($values['lang'][$idLang] ?? []);
        }

        $idPost = Repository::savePost($base, $byLang);

        $categoryIds = array_map('intval', (array) ($values['category_ids'] ?? []));
        Repository::syncPostCategories($idPost, $categoryIds);

        $tagIds = array_map('intval', (array) ($values['tag_ids'] ?? []));
        Repository::syncPostTags($idPost, $tagIds);

        $productIds = array_values(array_filter(array_map('intval', explode(',', (string) ($values['product_ids'] ?? '')))));
        Repository::syncPostProducts($idPost, $productIds);

        $faqItems = [];
        foreach ((array) ($values['faq'] ?? []) as $item) {
            $faqItems[] = [
                'question' => (array) ($item['question'] ?? []),
                'answer' => (array) ($item['answer'] ?? []),
            ];
        }
        Repository::syncPostFaqs($idPost, $faqItems);

        $previousFeaturedId = (int) (Repository::getPostById($idPost, $idShop)['featured_id_image'] ?? 0) ?: null;

        $images = new ImageStore();
        $featured = $request->files->get('featured_image');
        $featuredIdImage = null;
        if ($featured) {
            $result = $images->accept($featured, $idPost);
            if ($result !== null) {
                $featuredIdImage = Repository::insertImage($idPost, $result['filename'], $result['width'], $result['height'], 0, true);
                Repository::savePost(['id_post' => $idPost, 'id_shop' => $idShop, 'status' => $base['status'], 'featured_id_image' => $featuredIdImage], []);
                // Stare zdjęcie wyróżnione kasujemy. Inaczej zostaje na dysku
                // i w bazie, niewidoczne i niemożliwe do usunięcia z panelu.
                if ($previousFeaturedId !== null && $previousFeaturedId !== $featuredIdImage) {
                    Repository::deleteImage($previousFeaturedId);
                }
            }
        }
        if ($featuredIdImage === null) {
            $featuredIdImage = $previousFeaturedId;
        }
        if ($featuredIdImage !== null) {
            $captionByLang = [];
            foreach ($languages as $lang) {
                $idLang = (int) $lang['id_lang'];
                $captionByLang[$idLang] = (string) ($values['lang'][$idLang]['featured_image_caption'] ?? '');
            }
            Repository::updateImageCaption($featuredIdImage, $captionByLang);
        }

        $this->addFlash('success', $this->trans('Post saved.', self::DOMAIN));

        return $this->redirectToRoute('quince_blog_posts_edit', ['id' => $idPost]);
    }

    public function deleteAction(Request $request): RedirectResponse
    {
        // Przycisk usuwania wiersza żyje w TYM SAMYM formularzu co akcje
        // zbiorcze (HTML5 `formaction`/`formmethod`), więc dzieli jedno
        // ciało żądania. Stąd własne nazwane pole tokenu, nie goły
        // `_csrf_token` dzielony z `bulkAction`.
        if (!$this->isCsrfTokenValid('quince_blog_posts_delete', $request->request->get('_csrf_token_delete'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_posts');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $idPost = (int) $request->request->get('id', 0);

        if ($idPost > 0 && Repository::deletePost($idPost, $idShop)) {
            $this->addFlash('success', $this->trans('Post removed.', self::DOMAIN));
        } else {
            $this->addFlash('error', $this->trans('Could not remove that post.', self::DOMAIN));
        }

        return $this->redirectToRoute('quince_blog_posts');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        // Jak w `deleteAction`: ten formularz ma dwa cele (bulk i usuwanie
        // wiersza), więc własne nazwane pole tokenu dla tej akcji.
        if (!$this->isCsrfTokenValid('quince_blog_posts_bulk', $request->request->get('_csrf_token_bulk'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_posts');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $ids = array_map('intval', (array) $request->request->get('ids', []));
        $action = (string) $request->request->get('bulk_action', '');

        if ($ids === []) {
            $this->addFlash('error', $this->trans('No post selected.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_posts');
        }

        if ($action === 'delete') {
            $count = Repository::bulkDeletePosts($ids, $idShop);
            $this->addFlash('success', $this->trans('%count% post(s) removed.', self::DOMAIN, ['%count%' => $count]));
        } elseif (in_array($action, ['draft', 'published', 'scheduled'], true)) {
            $count = Repository::bulkUpdateStatus($ids, $action, $idShop);
            $this->addFlash('success', $this->trans('%count% post(s) updated.', self::DOMAIN, ['%count%' => $count]));
        }

        return $this->redirectToRoute('quince_blog_posts');
    }

    /** @return array<int,array<string,mixed>> */
    private function sortRows(array $rows, string $sort, string $dir): array
    {
        $allowed = ['title', 'date_pub', 'status', 'view_count'];
        if (!in_array($sort, $allowed, true)) {
            return $rows;
        }
        usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
            $cmp = ($a[$sort] ?? '') <=> ($b[$sort] ?? '');

            return $dir === 'ASC' ? $cmp : -$cmp;
        });

        return $rows;
    }

    /** @param array<int,string> $lang */
    /** @return array<int,array{id_product:int,name:string}> */
    private function productNames(array $ids): array
    {
        $idLang = (int) $this->getContext()->language->id;
        $idShop = (int) $this->getContext()->shop->id;
        $idsIn = implode(',', array_map('intval', $ids));
        if ($idsIn === '') {
            return [];
        }
        // `product_lang` ma wiersz na sklep (wielosklepowość), nie tylko na
        // język. Bez `id_shop` każdy produkt wychodzi raz na każdy sklep
        // instalacji.
        $rows = \Db::getInstance()->executeS(
            'SELECT p.id_product, pl.name
             FROM `' . _DB_PREFIX_ . 'product` p
             JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . "
             WHERE p.id_product IN ({$idsIn})"
        ) ?: [];

        return array_map(static fn (array $r): array => ['id_product' => (int) $r['id_product'], 'name' => (string) $r['name']], $rows);
    }
}
