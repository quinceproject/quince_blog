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
use Quince\Blog\Settings\BlogLayoutSettings;
use Quince\Engine\Ui\IconLibrary;

class Quince_BlogPostModuleFrontController extends ModuleFrontController
{
    /** @var array<string,mixed> */
    private $postForBreadcrumb = [];

    /**
     * Nadpisuje natywny okruszek PS9, żeby pokazywał Home → Blog →
     * (Kategoria) → Tytuł posta — te same okruszki, co w JSON-LD.
     */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        // Natywny blok okruszków woła tę metodę PRZED initContent(), więc
        // $this->postForBreadcrumb jest jeszcze puste — post trzeba znaleźć
        // od razu po slug z URL-a. Token podglądu sprawdzamy tym samym
        // sposobem co w initContent() — bez tego podgląd szkicu pokazywał
        // same "Home → Blog", bez kategorii i tytułu, bo wczesne wyszukanie
        // pomijało nieopublikowane posty niezależnie od tokenu.
        if (empty($this->postForBreadcrumb)) {
            $slug = (string) Tools::getValue('slug', '');
            if ($slug !== '') {
                $idLang = (int) $this->context->language->id;
                $idShop = (int) $this->context->shop->id;
                $previewToken = (string) Tools::getValue('preview', '');
                $post = Repository::findPostBySlug($slug, $idLang, $idShop, $previewToken !== '');
                if ($post && ($post['status'] ?? '') !== 'published' && !self::isValidPreviewToken((int) $post['id_post'], $previewToken)) {
                    $post = null;
                }
                if ($post) {
                    $post['categories'] = Repository::getPostCategories((int) $post['id_post'], $idLang);
                    $this->postForBreadcrumb = $post;
                }
            }
        }

        $breadcrumb['links'][] = ['title' => $this->trans('Blog', [], 'Modules.Quinceblog.Shop'), 'url' => Seo::listUrl($this->context)];

        if (!empty($this->postForBreadcrumb['categories'])) {
            $cat = $this->postForBreadcrumb['categories'][0];
            $breadcrumb['links'][] = ['title' => $cat['name'], 'url' => Seo::categoryUrl($cat['slug'], $this->context)];
        }
        if (!empty($this->postForBreadcrumb['title'])) {
            $breadcrumb['links'][] = [
                'title' => $this->postForBreadcrumb['title'],
                'url' => Seo::postUrl($this->postForBreadcrumb['slug'], $this->context),
            ];
        }

        return $breadcrumb;
    }

    public function initContent()
    {
        $slug = (string) Tools::getValue('slug', '');
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        // Token podglądu pozwala adminowi zobaczyć nieopublikowany post.
        $previewToken = (string) Tools::getValue('preview', '');
        $post = $slug !== '' ? Repository::findPostBySlug($slug, $idLang, $idShop, $previewToken !== '') : null;

        if ($post && ($post['status'] ?? '') !== 'published') {
            if (!self::isValidPreviewToken((int) $post['id_post'], $previewToken)) {
                $post = null;
            }
        }

        if (!$post) {
            $this->redirect404();

            return;
        }

        parent::initContent();

        $layoutSettings = new BlogLayoutSettings();

        $idPost = (int) $post['id_post'];
        Repository::incrementViewCount($idPost);

        $post['categories'] = Repository::getPostCategories($idPost, $idLang);
        foreach ($post['categories'] as &$c) {
            $c['url'] = Seo::categoryUrl($c['slug'], $this->context);
        }
        unset($c);
        $post['cover_url'] = $this->resolveCover($idPost, $post['featured_id_image'] ?? null, $post['images'] ?? []);
        $post['cover_caption'] = !empty($post['featured_id_image'])
            ? (Repository::getImageCaptions((int) $post['featured_id_image'])[$idLang] ?? '')
            : '';
        $post['gallery'] = $this->buildGallery($post['images'] ?? []);

        $related = Repository::getRelatedPosts($idPost, $idLang, $idShop, $layoutSettings->relatedCount());
        // Jedno zapytanie o okładki powiązanych wpisów, nie jedno na pozycję.
        // Dopasowanie po dokładnym id_image, nie przez resolveCover() — jego
        // zapasowa pierwsza-miniaturka-z-galerii działa na JEDNYM poście
        // (sam post wyżej), a tu obrazy kilku różnych postów stałyby w jednej
        // tablicy i zapasowe zdjęcie trafiałoby z przypadkowego sąsiada.
        $relatedImages = Repository::getImagesByIds(array_column($related, 'featured_id_image'));
        foreach ($related as &$r) {
            $r['url'] = Seo::postUrl($r['slug'], $this->context);
            $featuredIdImage = (int) ($r['featured_id_image'] ?? 0);
            $r['cover_url'] = $featuredIdImage && isset($relatedImages[$featuredIdImage])
                ? Seo::imageUrl($relatedImages[$featuredIdImage]['filename'])
                : '';
        }
        unset($r);

        // Nawigacja do poprzedniego/następnego posta w pierwszej kategorii.
        $categoryIds = array_map(static fn (array $c): int => (int) $c['id_category'], $post['categories'] ?? []);
        $prevPost = $nextPost = null;
        if (!empty($categoryIds)) {
            $datePub = $post['date_pub'] ?? null;
            $prevPost = Repository::getAdjacentPost($idPost, $categoryIds, $idLang, $idShop, 'prev', $datePub);
            $nextPost = Repository::getAdjacentPost($idPost, $categoryIds, $idLang, $idShop, 'next', $datePub);
            foreach (['prevPost', 'nextPost'] as $var) {
                if ($$var) {
                    $$var['url'] = Seo::postUrl($$var['slug'], $this->context);
                }
            }
        }

        $meta = Seo::buildPostMeta($post, $this->context, $post['images'] ?? []);
        $this->context->smarty->assign(['meta_title' => $meta['title'], 'meta_description' => $meta['description']]);
        Seo::applyPageMeta($this->context, $meta['title'], $meta['description']);

        $perLang = $this->fetchPostLangSlugs($idPost);
        $hreflangs = Seo::buildHreflangs($perLang, 'post', $this->context);

        $crumbs = $this->buildBreadcrumbs($post);
        $jsonldPost = Seo::buildPostJsonLd($post, $meta, $this->context);
        $jsonldBreadcrumb = Seo::buildBreadcrumbJsonLd($crumbs);
        $this->postForBreadcrumb = $post;

        // Polecane produkty: ręcznie wybrane (priorytet), a gdy zostało
        // miejsce i post ma limit > 0 — dobrane po natywnych tagach PS.
        $manualIds = array_map('intval', (array) ($post['product_ids'] ?? []));
        $max = (int) ($post['featured_products_max'] ?? 0);
        if ($max > 0) {
            $manualIds = array_slice($manualIds, 0, $max);
            $slotsLeft = $max - count($manualIds);
            if ($slotsLeft > 0) {
                $tagIds = Repository::getPostPsTagIds($idPost);
                if (!empty($tagIds)) {
                    $manualIds = array_merge($manualIds, Repository::getProductsByPsTags($tagIds, $manualIds, $slotsLeft, $idShop));
                }
            }
        }
        $featuredProducts = !empty($manualIds) ? $this->presentFeaturedProducts($manualIds) : [];

        $faqItems = Repository::getPostFaqsForLang($idPost, $idLang);
        $jsonldFaq = !empty($faqItems) ? Seo::buildFaqJsonLd($faqItems) : '';

        $this->context->smarty->assign([
            'blog_post' => $post,
            'blog_related' => $related,
            'blog_prev_post' => $prevPost,
            'blog_next_post' => $nextPost,
            'blog_featured_products' => $featuredProducts,
            'blog_faq_items' => $faqItems,
            'blog_meta' => $meta,
            'blog_jsonld_post' => $jsonldPost,
            'blog_jsonld_breadcrumb' => $jsonldBreadcrumb,
            'blog_jsonld_faq' => $jsonldFaq,
            'blog_hreflangs' => $hreflangs,
            'blog_list_url' => Seo::listUrl($this->context),
            'blog_content_width' => $layoutSettings->contentWidthPx(),
            'blog_cover_width' => $layoutSettings->coverWidthPx(),
            'blog_content_cols' => $layoutSettings->contentColumns(),
            'blog_featured_icon_svg' => IconLibrary::svg((string) ($post['featured_products_icon'] ?? '')),
            'blog_faq_icon_svg' => IconLibrary::svg((string) ($post['faq_icon'] ?? '')),
        ]);

        $this->setTemplate('module:quince_blog/views/templates/front/post.tpl');
    }

    private function resolveCover(int $idPost, ?int $featuredIdImage, array $images = []): string
    {
        if ($featuredIdImage) {
            foreach ($images as $img) {
                if ((int) $img['id_image'] === $featuredIdImage) {
                    return Seo::imageUrl($img['filename']);
                }
            }
            $img = Repository::getImageById($featuredIdImage);
            if ($img) {
                return Seo::imageUrl($img['filename']);
            }
        }
        if (!empty($images[0]['filename'])) {
            return Seo::imageUrl($images[0]['filename']);
        }

        return '';
    }

    /** @return array<int,array<string,mixed>> */
    private function buildGallery(array $images): array
    {
        $out = [];
        foreach ($images as $img) {
            if (!empty($img['is_featured'])) {
                continue;
            }
            $out[] = [
                'id_image' => (int) $img['id_image'],
                'url' => Seo::imageUrl($img['filename']),
                'width' => $img['width'] ?? null,
                'height' => $img['height'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<int,array{id_lang:int,slug:string}> */
    private function fetchPostLangSlugs(int $idPost): array
    {
        return Db::getInstance()->executeS(
            'SELECT id_lang, slug FROM `' . _DB_PREFIX_ . 'quince_blog_post_lang` WHERE id_post = ' . $idPost
        ) ?: [];
    }

    /** @return array<int,array{name:string,url:string}> */
    private function buildBreadcrumbs(array $post): array
    {
        $crumbs = [
            ['name' => $this->trans('Home', [], 'Modules.Quinceblog.Shop'), 'url' => $this->context->link->getPageLink('index')],
            ['name' => $this->trans('Blog', [], 'Modules.Quinceblog.Shop'), 'url' => Seo::listUrl($this->context)],
        ];
        if (!empty($post['categories'])) {
            $cat = $post['categories'][0];
            $crumbs[] = ['name' => $cat['name'], 'url' => Seo::categoryUrl($cat['slug'], $this->context)];
        }
        $crumbs[] = ['name' => $post['title'], 'url' => Seo::postUrl($post['slug'], $this->context)];

        return $crumbs;
    }

    /**
     * Prezentuje polecane produkty tą samą drogą co natywny cross-selling PS9
     * ("Klienci kupili również") — ProductAssembler + ProductPresenterFactory
     * dają szablonowi karty `catalog/_partials/miniatures/product.tpl` dane
     * dokładnie w kształcie, jakiego oczekuje.
     */
    private function presentFeaturedProducts(array $idProducts): array
    {
        if (empty($idProducts)) {
            return [];
        }
        $assembler = new ProductAssembler($this->context);
        $factory = new ProductPresenterFactory($this->context);
        $settings = $factory->getPresentationSettings();
        $presenter = $factory->getPresenter();

        $out = [];
        foreach ($idProducts as $idProduct) {
            try {
                $raw = $assembler->assembleProduct(['id_product' => (int) $idProduct]);
                if (!$raw || empty($raw['id_product'])) {
                    continue;
                }
                $out[] = $presenter->present($settings, $raw, $this->context->language);
            } catch (\Throwable $e) {
                PrestaShopLogger::addLog(
                    'quince_blog featured product present failed: ' . $e->getMessage(),
                    2,
                    null,
                    'Product',
                    (int) $idProduct
                );
            }
        }

        return $out;
    }

    private static function isValidPreviewToken(int $idPost, string $token): bool
    {
        return $token !== '' && hash_equals(Repository::previewToken($idPost), $token);
    }

    private function redirect404(): void
    {
        header('HTTP/1.1 404 Not Found');
        header('Status: 404 Not Found');
        // Renderujemy 404 w obrębie bieżącego, w pełni złożonego kontrolera —
        // ręczne złożenie PageNotFoundController zostawia pusty kontener
        // Symfony i wywala "Call to a member function get() on null".
        parent::initContent();
        $this->setTemplate('errors/not-found');
    }
}
