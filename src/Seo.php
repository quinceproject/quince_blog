<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Blog;

use Configuration;
use Context;
use Language;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Meta tagi, hreflang i JSON-LD dla widoków bloga. Przepisane z
 * `FlavorBlogSeo` — Organization JSON-LD celowo stąd wycięty, bo to
 * zajmuje się nim quince_engine (BrandSchemaSettings) na każdej stronie
 * sklepu, nie tylko na blogu.
 */
final class Seo
{
    public static function buildPostMeta(array $post, Context $context, array $images = []): array
    {
        $title = trim((string) ($post['meta_title'] ?? ''));
        if ($title === '') {
            $title = (string) ($post['title'] ?? '');
        }
        $desc = trim((string) ($post['meta_description'] ?? ''));
        if ($desc === '') {
            $desc = self::stripTagsExcerpt((string) ($post['excerpt'] ?? ''), 160);
        }
        $ogImage = self::resolveOgImage($post, $images);

        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => self::postUrl($post['slug'], $context),
            'og_image' => $ogImage,
            'og_type' => 'article',
            'robots' => (string) ($post['robots'] ?? 'index'),
            'twitter_card' => $ogImage ? 'summary_large_image' : 'summary',
        ];
    }

    public static function buildCategoryMeta(array $category, Context $context): array
    {
        $title = trim((string) ($category['meta_title'] ?? ''));
        if ($title === '') {
            $title = (string) ($category['name'] ?? '');
        }
        $desc = trim((string) ($category['meta_description'] ?? ''));
        if ($desc === '') {
            $desc = self::stripTagsExcerpt((string) ($category['description'] ?? ''), 160);
        }

        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => self::categoryUrl($category['slug'], $context),
            'og_image' => '',
            'og_type' => 'website',
            'robots' => 'index',
            'twitter_card' => 'summary',
        ];
    }

    public static function buildListMeta(Context $context): array
    {
        $shopName = (string) Configuration::get('PS_SHOP_NAME');
        $blog = $context->getTranslator()->trans('Blog', [], 'Modules.Quinceblog.Shop');

        return [
            'title' => $blog . ($shopName ? ' — ' . $shopName : ''),
            'description' => '',
            'canonical' => self::listUrl($context),
            'og_image' => '',
            'og_type' => 'website',
            'robots' => 'index',
            'twitter_card' => 'summary',
        ];
    }

    /**
     * Odpowiedniki posta w pozostałych językach — dla `<link rel="alternate" hreflang>`.
     *
     * @param array<int,array{id_lang:int,slug:string}> $perLang
     *
     * @return array<int,array{hreflang:string,url:string}>
     */
    public static function buildHreflangs(array $perLang, string $type, Context $context): array
    {
        $out = [];
        $languages = Language::getLanguages(true, $context->shop->id);
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idMap = [];
        foreach ($languages as $l) {
            $idMap[(int) $l['id_lang']] = $l['iso_code'];
        }

        $defaultUrl = null;
        foreach ($perLang as $row) {
            $idLang = (int) ($row['id_lang'] ?? 0);
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || !isset($idMap[$idLang])) {
                continue;
            }
            $url = $type === 'category' ? self::categoryUrl($slug, $context) : self::postUrl($slug, $context);
            $out[] = ['hreflang' => $idMap[$idLang], 'url' => $url];
            if ($idLang === $defaultLang) {
                $defaultUrl = $url;
            }
        }
        if ($defaultUrl !== null) {
            $out[] = ['hreflang' => 'x-default', 'url' => $defaultUrl];
        }

        return $out;
    }

    public static function buildPostJsonLd(array $post, array $meta, Context $context): string
    {
        $shopName = (string) Configuration::get('PS_SHOP_NAME');
        $logo = (string) Configuration::get('PS_LOGO');
        $publisherLogo = $logo ? _PS_BASE_URL_ . _PS_IMG_ . $logo : '';

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $meta['canonical']],
            'headline' => mb_substr((string) ($post['title'] ?? ''), 0, 110),
            'description' => $meta['description'],
            'datePublished' => self::iso($post['date_pub'] ?? $post['date_add'] ?? null),
            'dateModified' => self::iso($post['date_upd'] ?? $post['date_pub'] ?? null),
            'author' => ['@type' => 'Organization', 'name' => $shopName],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $shopName,
                'logo' => $publisherLogo ? ['@type' => 'ImageObject', 'url' => $publisherLogo] : null,
            ],
        ];
        if (!empty($meta['og_image'])) {
            $data['image'] = $meta['og_image'];
        }

        // `JSON_HEX_TAG`: ten znacznik idzie wprost w `<script type="application/
        // ld+json">` bez cudzego filtrowania — tytuł albo opis z `</script>` w treści
        // wyłamałby się z bloku JSON-LD i uruchomił jako osobny znacznik. Zamienia
        // `<`/`>` na `<`/`>`, więc taki ciąg zostaje samym tekstem.
        return (string) json_encode(
            array_filter($data, static fn ($v) => $v !== null),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG
        );
    }

    /** @param array<int,array{name:string,url:string}> $crumbs */
    public static function buildBreadcrumbJsonLd(array $crumbs): string
    {
        $items = [];
        foreach (array_values($crumbs) as $i => $c) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'] ?? '',
                'item' => $c['url'] ?? '',
            ];
        }

        // `JSON_HEX_TAG`: patrz komentarz w `buildPostJsonLd()` — nazwa okruszka
        // (tytuł kategorii, tytuł posta) trafia tu bez przefiltrowania.
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG);
    }

    /**
     * Musi dokładnie odpowiadać widocznej liście pytań pod postem — wymóg
     * danych strukturalnych Google (i silników AI, które też czytają schema).
     *
     * @param array<int,array{question:string,answer:string}> $faqs
     */
    public static function buildFaqJsonLd(array $faqs): string
    {
        $entities = [];
        foreach ($faqs as $faq) {
            $q = trim(strip_tags((string) ($faq['question'] ?? '')));
            $a = trim(strip_tags((string) ($faq['answer'] ?? '')));
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
        if (empty($entities)) {
            return '';
        }

        // `JSON_HEX_TAG`: patrz komentarz w `buildPostJsonLd()`.
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG);
    }

    /**
     * Kształt, którego oczekuje `_partials/pagination.tpl` (ten sam, którego
     * używa listing produktów rdzenia) — numery stron, wielokropek w miejscu
     * przeskoku, „Następna" bez „Poprzednia" (wraca się przyciskiem przeglądarki).
     *
     * @return array{should_be_displayed:bool,pages:array<int,array<string,mixed>>}
     */
    public static function buildPagination(int $page, int $perPage, int $total, string $baseUrl): array
    {
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        if ($totalPages <= 1) {
            return ['should_be_displayed' => false, 'pages' => []];
        }

        $urlFor = static fn (int $p): string => $p <= 1 ? $baseUrl : $baseUrl . '?page=' . $p;

        $window = 2;
        $shown = [];
        for ($p = 1; $p <= $totalPages; ++$p) {
            if ($p === 1 || $p === $totalPages || abs($p - $page) <= $window) {
                $shown[] = $p;
            }
        }

        $pages = [];
        $prev = 0;
        foreach ($shown as $p) {
            if ($prev && $p - $prev > 1) {
                $pages[] = ['type' => 'spacer'];
            }
            $pages[] = ['type' => 'page', 'page' => $p, 'url' => $urlFor($p), 'current' => $p === $page, 'clickable' => $p !== $page];
            $prev = $p;
        }
        if ($page < $totalPages) {
            $pages[] = ['type' => 'next', 'url' => $urlFor($page + 1), 'clickable' => true];
        }

        return ['should_be_displayed' => true, 'pages' => $pages];
    }

    public static function imageUrl(string $filename): string
    {
        $filename = basename($filename);

        return _PS_BASE_URL_ . __PS_BASE_URI__ . 'img/blog/' . $filename;
    }

    public static function routePrefix(): string
    {
        return trim((string) Configuration::get('QUINCE_BLOG_ROUTE_PREFIX')) ?: 'blog';
    }

    public static function listUrl(Context $context): string
    {
        return rtrim($context->shop->getBaseURL(true), '/') . '/' . self::routePrefix();
    }

    public static function postUrl(string $slug, Context $context): string
    {
        return rtrim($context->shop->getBaseURL(true), '/') . '/' . self::routePrefix() . '/' . $slug;
    }

    public static function categoryUrl(string $slug, Context $context): string
    {
        return rtrim($context->shop->getBaseURL(true), '/') . '/' . self::routePrefix() . '/category/' . $slug;
    }

    public static function tagUrl(string $slug, Context $context): string
    {
        return rtrim($context->shop->getBaseURL(true), '/') . '/' . self::routePrefix() . '/tag/' . $slug;
    }

    private static function resolveOgImage(array $post, array $images): string
    {
        if (!empty($post['og_image_override'])) {
            return self::imageUrl((string) $post['og_image_override']);
        }
        foreach ($images as $img) {
            if (!empty($img['is_featured']) && !empty($img['filename'])) {
                return self::imageUrl((string) $img['filename']);
            }
        }
        if (!empty($images[0]['filename'])) {
            return self::imageUrl((string) $images[0]['filename']);
        }

        return '';
    }

    private static function stripTagsExcerpt(string $html, int $maxLen): string
    {
        $text = trim(strip_tags($html));
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen - 1) . '…';
        }

        return $text;
    }

    private static function iso(?string $dt): ?string
    {
        if (!$dt) {
            return null;
        }
        $ts = strtotime($dt);

        return $ts ? date('c', $ts) : null;
    }
}
