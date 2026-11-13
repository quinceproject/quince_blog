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

use Db;
use Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Jeden magazyn na wszystkie operacje bazodanowe quince_blog.
 *
 * Metody statyczne na tablicach zamiast ObjectModel per tabela: nic tu nie
 * korzysta z cyklu validate/save, tylko z jawnego CRUD-u. Każda metoda
 * dotycząca posta/kategorii/tagu bierze `$idShop` i filtruje/zapisuje nim.
 */
final class Repository
{
    public static function previewToken(int $idPost): string
    {
        return substr(Tools::hash('quince_blog_preview_' . $idPost), 0, 20);
    }

    // ---------------------------------------------------------------------
    // POSTY
    // ---------------------------------------------------------------------

    /**
     * @param array{id_shop:int,lang_id:int,status?:string,id_category?:int,id_tag?:int,limit?:int,offset?:int,search?:string,date_from?:string} $filter
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listPosts(array $filter): array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $langId = (int) ($filter['lang_id'] ?? \Configuration::get('PS_LANG_DEFAULT'));
        $idShop = (int) $filter['id_shop'];

        $joins = '';
        $where = ['pl.id_lang = ' . $langId, 'p.id_shop = ' . $idShop];

        if (!empty($filter['status'])) {
            $where[] = "p.status = '" . pSQL($filter['status']) . "'";
        }
        if (!empty($filter['id_category'])) {
            $joins .= " JOIN `{$p}quince_blog_post_category` pc ON pc.id_post = p.id_post AND pc.id_category = " . (int) $filter['id_category'];
        }
        if (!empty($filter['id_tag'])) {
            $joins .= " JOIN `{$p}quince_blog_post_tag` pt ON pt.id_post = p.id_post AND pt.id_tag = " . (int) $filter['id_tag'];
        }
        if (!empty($filter['search'])) {
            $s = pSQL($filter['search']);
            $where[] = "(pl.title LIKE '%{$s}%' OR pl.excerpt LIKE '%{$s}%' OR pl.slug LIKE '%{$s}%')";
        }
        if (!empty($filter['date_from'])) {
            $where[] = "p.date_pub >= '" . pSQL($filter['date_from']) . "'";
        }

        $limit = max(1, min(200, (int) ($filter['limit'] ?? 12)));
        $offset = max(0, (int) ($filter['offset'] ?? 0));

        $sql = "SELECT p.id_post, p.status, p.featured_id_image, p.author_id_employee,
                       p.view_count, p.date_pub, p.date_add, p.date_upd,
                       pl.title, pl.slug, pl.excerpt, pl.meta_title, pl.meta_description,
                       pl.og_image_override, pl.robots
                FROM `{$p}quince_blog_post` p
                JOIN `{$p}quince_blog_post_lang` pl ON pl.id_post = p.id_post
                {$joins}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.date_pub DESC, p.id_post DESC
                LIMIT {$offset}, {$limit}";

        return $db->executeS($sql) ?: [];
    }

    public static function countPosts(array $filter): int
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $langId = (int) ($filter['lang_id'] ?? \Configuration::get('PS_LANG_DEFAULT'));
        $idShop = (int) $filter['id_shop'];

        $joins = '';
        $where = ['pl.id_lang = ' . $langId, 'p.id_shop = ' . $idShop];

        if (!empty($filter['status'])) {
            $where[] = "p.status = '" . pSQL($filter['status']) . "'";
        }
        if (!empty($filter['id_category'])) {
            $joins .= " JOIN `{$p}quince_blog_post_category` pc ON pc.id_post = p.id_post AND pc.id_category = " . (int) $filter['id_category'];
        }
        if (!empty($filter['id_tag'])) {
            $joins .= " JOIN `{$p}quince_blog_post_tag` pt ON pt.id_post = p.id_post AND pt.id_tag = " . (int) $filter['id_tag'];
        }
        if (!empty($filter['search'])) {
            $s = pSQL($filter['search']);
            $where[] = "(pl.title LIKE '%{$s}%' OR pl.excerpt LIKE '%{$s}%')";
        }

        return (int) $db->getValue(
            "SELECT COUNT(DISTINCT p.id_post)
             FROM `{$p}quince_blog_post` p
             JOIN `{$p}quince_blog_post_lang` pl ON pl.id_post = p.id_post
             {$joins}
             WHERE " . implode(' AND ', $where)
        );
    }

    /**
     * Pełny post ze wszystkimi wierszami językowymi + powiązaniami. Sprawdza
     * `$idShop`, żeby panel jednego sklepu nie mógł otworzyć/zapisać posta
     * innego. Bez tego link z guzikiem „Edytuj" skopiowany między sklepami
     * dawałby dostęp do cudzej treści.
     */
    public static function getPostById(int $idPost, int $idShop): ?array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;

        $post = $db->getRow("SELECT * FROM `{$p}quince_blog_post` WHERE id_post = " . $idPost . " AND id_shop = " . $idShop);
        if (!$post) {
            return null;
        }
        $langs = $db->executeS("SELECT * FROM `{$p}quince_blog_post_lang` WHERE id_post = " . $idPost) ?: [];
        $post['lang'] = [];
        foreach ($langs as $row) {
            $post['lang'][(int) $row['id_lang']] = $row;
        }
        $post['category_ids'] = array_map(
            fn ($r) => (int) $r['id_category'],
            $db->executeS("SELECT id_category FROM `{$p}quince_blog_post_category` WHERE id_post = " . $idPost) ?: []
        );
        $post['tag_ids'] = array_map(
            fn ($r) => (int) $r['id_tag'],
            $db->executeS("SELECT id_tag FROM `{$p}quince_blog_post_tag` WHERE id_post = " . $idPost) ?: []
        );
        $post['product_ids'] = self::getPostProductIds($idPost);
        $post['ps_tag_ids'] = self::getPostPsTagIds($idPost);
        $post['faqs'] = self::getPostFaqs($idPost);
        $post['images'] = $db->executeS(
            "SELECT * FROM `{$p}quince_blog_image` WHERE id_post = " . $idPost . " ORDER BY position ASC, id_image ASC"
        ) ?: [];

        return $post;
    }

    public static function findPostBySlug(string $slug, int $idLang, int $idShop, bool $includeDrafts = false): ?array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $statusFilter = $includeDrafts ? '' : " AND p.status = 'published'";
        $row = $db->getRow(
            "SELECT p.*, pl.title, pl.slug, pl.excerpt, pl.content, pl.meta_title, pl.meta_description,
                    pl.og_image_override, pl.robots, pl.featured_products_heading, pl.faq_heading
             FROM `{$p}quince_blog_post` p
             JOIN `{$p}quince_blog_post_lang` pl ON pl.id_post = p.id_post
             WHERE pl.slug = '" . pSQL($slug) . "' AND pl.id_lang = " . $idLang . " AND p.id_shop = " . $idShop . $statusFilter
        );
        if (!$row) {
            return null;
        }
        $idPost = (int) $row['id_post'];
        $row['category_ids'] = array_map(
            fn ($r) => (int) $r['id_category'],
            $db->executeS("SELECT id_category FROM `{$p}quince_blog_post_category` WHERE id_post = " . $idPost) ?: []
        );
        $row['tag_ids'] = array_map(
            fn ($r) => (int) $r['id_tag'],
            $db->executeS("SELECT id_tag FROM `{$p}quince_blog_post_tag` WHERE id_post = " . $idPost) ?: []
        );
        $row['product_ids'] = self::getPostProductIds($idPost);
        $row['images'] = $db->executeS(
            "SELECT * FROM `{$p}quince_blog_image` WHERE id_post = " . $idPost . " ORDER BY position ASC, id_image ASC"
        ) ?: [];

        return $row;
    }

    /**
     * Tworzy albo uaktualnia post. Zwraca id_post.
     *
     * @param array{id_post?:int,id_shop:int,status:string,featured_id_image?:?int,author_id_employee?:?int,date_pub?:?string} $base
     * @param array<int,array{title:string,slug:string,excerpt?:string,content?:string,meta_title?:string,meta_description?:string,og_image_override?:?string,robots?:string}> $byLang
     */
    public static function savePost(array $base, array $byLang): int
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $now = date('Y-m-d H:i:s');
        $idPost = (int) ($base['id_post'] ?? 0);
        $idShop = (int) $base['id_shop'];

        $row = [
            'status' => pSQL($base['status'] ?? 'draft'),
            'featured_id_image' => isset($base['featured_id_image']) && $base['featured_id_image'] ? (int) $base['featured_id_image'] : null,
            'author_id_employee' => isset($base['author_id_employee']) && $base['author_id_employee'] ? (int) $base['author_id_employee'] : null,
            'featured_products_icon' => !empty($base['featured_products_icon']) ? pSQL($base['featured_products_icon']) : null,
            'faq_icon' => !empty($base['faq_icon']) ? pSQL($base['faq_icon']) : null,
            'featured_products_max' => (int) ($base['featured_products_max'] ?? 0),
            'date_pub' => !empty($base['date_pub']) ? pSQL($base['date_pub']) : null,
            'date_upd' => $now,
        ];

        if ($idPost) {
            // `id_shop` w WHERE, nie tylko w SET: zapis spoza sklepu posta nie
            // trafia w nic, zamiast po cichu przenieść post do innego sklepu.
            $db->execute("UPDATE `{$p}quince_blog_post` SET " . self::buildSet($row) . " WHERE id_post = " . $idPost . " AND id_shop = " . $idShop);
        } else {
            $row['id_shop'] = $idShop;
            $row['date_add'] = $now;
            $db->insert('quince_blog_post', $row);
            $idPost = (int) $db->Insert_ID();
        }

        foreach ($byLang as $idLang => $fields) {
            $idLang = (int) $idLang;
            $slug = !empty($fields['slug']) ? self::slugify($fields['slug']) : self::slugify($fields['title'] ?? '');
            $slug = self::ensureUniquePostSlug($slug, $idShop, $idLang, $idPost);
            $robots = (string) ($fields['robots'] ?? 'index');

            $data = [
                'id_post' => $idPost,
                'id_lang' => $idLang,
                'id_shop' => $idShop,
                'title' => (string) ($fields['title'] ?? ''),
                'slug' => (string) $slug,
                'excerpt' => (string) ($fields['excerpt'] ?? ''),
                'content' => (string) ($fields['content'] ?? ''),
                'meta_title' => (string) ($fields['meta_title'] ?? ''),
                'meta_description' => (string) ($fields['meta_description'] ?? ''),
                'og_image_override' => !empty($fields['og_image_override']) ? (string) $fields['og_image_override'] : null,
                'featured_products_heading' => (string) ($fields['featured_products_heading'] ?? ''),
                'faq_heading' => (string) ($fields['faq_heading'] ?? ''),
                'robots' => in_array($robots, ['index', 'noindex'], true) ? $robots : 'index',
            ];
            // `id_shop` w WHERE, nie tylko w SET: bez niego ten UPDATE pisze
            // po id_post/id_lang bez względu na właściciela wiersza, a admin
            // jednego sklepu mógłby w ten sposób nadpisać treść posta innego
            // sklepu w tej samej instalacji wielosklepowej, podając jego id.
            $exists = (int) $db->getValue(
                "SELECT 1 FROM `{$p}quince_blog_post_lang` WHERE id_post = " . $idPost . " AND id_lang = " . $idLang . " AND id_shop = " . $idShop
            );
            if ($exists) {
                $db->execute("UPDATE `{$p}quince_blog_post_lang` SET " . self::buildSet($data, ['id_post', 'id_lang']) . " WHERE id_post = " . $idPost . " AND id_lang = " . $idLang . " AND id_shop = " . $idShop);
            } else {
                self::insertEscaped('quince_blog_post_lang', $data);
            }
        }

        return $idPost;
    }

    public static function bulkDeletePosts(array $ids, int $idShop): int
    {
        $count = 0;
        foreach ($ids as $idPost) {
            $idPost = (int) $idPost;
            if ($idPost > 0 && self::deletePost($idPost, $idShop)) {
                ++$count;
            }
        }

        return $count;
    }

    public static function bulkUpdateStatus(array $ids, string $status, int $idShop): int
    {
        if (!in_array($status, ['draft', 'published', 'scheduled'], true)) {
            return 0;
        }
        $clean = array_filter(array_map('intval', $ids), fn ($v) => $v > 0);
        if (empty($clean)) {
            return 0;
        }
        $idsIn = implode(',', $clean);
        Db::getInstance()->execute(
            "UPDATE `" . _DB_PREFIX_ . "quince_blog_post`
             SET status = '" . pSQL($status) . "', date_upd = '" . date('Y-m-d H:i:s') . "'
             WHERE id_post IN ({$idsIn}) AND id_shop = " . $idShop
        );

        return count($clean);
    }

    public static function deletePost(int $idPost, int $idShop): bool
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;

        $owned = (int) $db->getValue("SELECT 1 FROM `{$p}quince_blog_post` WHERE id_post = " . $idPost . " AND id_shop = " . $idShop);
        if (!$owned) {
            return false;
        }

        $images = $db->executeS("SELECT filename FROM `{$p}quince_blog_image` WHERE id_post = " . $idPost) ?: [];

        $db->execute("DELETE FROM `{$p}quince_blog_image`        WHERE id_post = " . $idPost);
        $db->execute("DELETE FROM `{$p}quince_blog_post_category` WHERE id_post = " . $idPost);
        $db->execute("DELETE FROM `{$p}quince_blog_post_tag`      WHERE id_post = " . $idPost);
        $db->execute("DELETE FROM `{$p}quince_blog_post_product`  WHERE id_post = " . $idPost);
        $db->execute("DELETE FROM `{$p}quince_blog_post_ps_tag`   WHERE id_post = " . $idPost);
        foreach ($db->executeS("SELECT id_faq FROM `{$p}quince_blog_post_faq` WHERE id_post = " . $idPost) ?: [] as $faq) {
            $db->execute("DELETE FROM `{$p}quince_blog_post_faq_lang` WHERE id_faq = " . (int) $faq['id_faq']);
        }
        $db->execute("DELETE FROM `{$p}quince_blog_post_faq`      WHERE id_post = " . $idPost);
        $db->execute("DELETE FROM `{$p}quince_blog_post_lang`     WHERE id_post = " . $idPost);
        $db->execute("DELETE FROM `{$p}quince_blog_post`          WHERE id_post = " . $idPost);

        foreach ($images as $img) {
            $f = _PS_IMG_DIR_ . 'blog' . DIRECTORY_SEPARATOR . basename($img['filename']);
            if (is_file($f)) {
                @unlink($f);
            }
        }

        return true;
    }

    public static function syncPostCategories(int $idPost, array $ids): void
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $db->execute("DELETE FROM `{$p}quince_blog_post_category` WHERE id_post = " . $idPost);
        foreach (array_unique(array_map('intval', $ids)) as $idCat) {
            if ($idCat > 0) {
                $db->insert('quince_blog_post_category', ['id_post' => $idPost, 'id_category' => $idCat]);
            }
        }
    }

    public static function syncPostTags(int $idPost, array $ids): void
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $db->execute("DELETE FROM `{$p}quince_blog_post_tag` WHERE id_post = " . $idPost);
        foreach (array_unique(array_map('intval', $ids)) as $idTag) {
            if ($idTag > 0) {
                $db->insert('quince_blog_post_tag', ['id_post' => $idPost, 'id_tag' => $idTag]);
            }
        }
    }

    public static function syncPostProducts(int $idPost, array $ids): void
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $db->execute("DELETE FROM `{$p}quince_blog_post_product` WHERE id_post = " . $idPost);
        $pos = 0;
        foreach (array_unique(array_map('intval', $ids)) as $idProduct) {
            if ($idProduct > 0) {
                $db->insert('quince_blog_post_product', [
                    'id_post' => $idPost,
                    'id_product' => $idProduct,
                    'position' => $pos++,
                ]);
            }
        }
    }

    public static function getRecentPosts(int $limit, int $idLang, int $idShop, int $excludeId = 0, ?int $idCategory = null): array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $now = date('Y-m-d H:i:s');
        $exclude = $excludeId > 0 ? ' AND p.id_post <> ' . $excludeId : '';
        $join = '';
        if ($idCategory) {
            $join = " JOIN `{$p}quince_blog_post_category` pc ON pc.id_post = p.id_post AND pc.id_category = " . (int) $idCategory;
        }

        return $db->executeS(
            "SELECT p.id_post, p.featured_id_image, p.date_pub,
                    pl.title, pl.slug, pl.excerpt
             FROM `{$p}quince_blog_post` p
             JOIN `{$p}quince_blog_post_lang` pl ON pl.id_post = p.id_post AND pl.id_lang = " . $idLang . "
             {$join}
             WHERE p.id_shop = " . $idShop . "
               AND p.status = 'published'
               AND (p.date_pub IS NULL OR p.date_pub <= '" . pSQL($now) . "')
               {$exclude}
             ORDER BY p.date_pub DESC, p.id_post DESC
             LIMIT " . max(1, min(20, $limit))
        ) ?: [];
    }

    /** @return array{id_post:int,title:string,slug:string}|null */
    public static function getAdjacentPost(int $idPost, array $categoryIds, int $idLang, int $idShop, string $direction, ?string $datePub): ?array
    {
        if (empty($categoryIds) || !in_array($direction, ['prev', 'next'], true)) {
            return null;
        }
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $idsIn = implode(',', array_map('intval', $categoryIds));
        $now = date('Y-m-d H:i:s');

        if ($direction === 'prev') {
            $cmp = '<';
            $order = 'DESC';
        } else {
            $cmp = '>';
            $order = 'ASC';
        }
        if ($datePub === null) {
            $where = "p.id_post {$cmp} " . $idPost;
        } else {
            $dq = pSQL($datePub);
            $where = "(p.date_pub {$cmp} '{$dq}' OR (p.date_pub = '{$dq}' AND p.id_post {$cmp} " . $idPost . '))';
        }

        return $db->getRow(
            "SELECT DISTINCT p.id_post, pl.title, pl.slug
             FROM `{$p}quince_blog_post` p
             JOIN `{$p}quince_blog_post_category` pc ON pc.id_post = p.id_post AND pc.id_category IN ({$idsIn})
             JOIN `{$p}quince_blog_post_lang` pl ON pl.id_post = p.id_post AND pl.id_lang = " . $idLang . "
             WHERE p.id_shop = " . $idShop . "
               AND p.status = 'published'
               AND (p.date_pub IS NULL OR p.date_pub <= '{$now}')
               AND p.id_post <> " . $idPost . "
               AND {$where}
             ORDER BY p.date_pub {$order}, p.id_post {$order}"
        ) ?: null;
    }

    // ---------------------------------------------------------------------
    // FAQ
    // ---------------------------------------------------------------------

    /** @param array<int,array{question:array<int,string>,answer:array<int,string>}> $items */
    public static function syncPostFaqs(int $idPost, array $items): void
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        foreach ($db->executeS("SELECT id_faq FROM `{$p}quince_blog_post_faq` WHERE id_post = " . $idPost) ?: [] as $row) {
            $db->execute("DELETE FROM `{$p}quince_blog_post_faq_lang` WHERE id_faq = " . (int) $row['id_faq']);
        }
        $db->execute("DELETE FROM `{$p}quince_blog_post_faq` WHERE id_post = " . $idPost);

        $pos = 0;
        foreach ($items as $item) {
            $questions = $item['question'] ?? [];
            $answers = $item['answer'] ?? [];
            $hasContent = false;
            foreach ($questions as $q) {
                if (trim((string) $q) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                continue;
            }

            $db->insert('quince_blog_post_faq', ['id_post' => $idPost, 'position' => $pos++]);
            $idFaq = (int) $db->Insert_ID();
            foreach ($questions as $lid => $q) {
                $lid = (int) $lid;
                $q = trim((string) $q);
                if ($q === '') {
                    continue;
                }
                self::insertEscaped('quince_blog_post_faq_lang', [
                    'id_faq' => $idFaq,
                    'id_lang' => $lid,
                    'question' => $q,
                    'answer' => (string) ($answers[$lid] ?? ''),
                ]);
            }
        }
    }

    /** @return array<int,array{id_faq:int,position:int,lang:array<int,array{question:string,answer:string}>}> */
    public static function getPostFaqs(int $idPost): array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $rows = $db->executeS(
            "SELECT id_faq, position FROM `{$p}quince_blog_post_faq`
             WHERE id_post = " . $idPost . ' ORDER BY position ASC, id_faq ASC'
        ) ?: [];
        if (empty($rows)) {
            return [];
        }

        $idsIn = implode(',', array_map(fn ($r) => (int) $r['id_faq'], $rows));
        $byId = [];
        foreach ($db->executeS("SELECT id_faq, id_lang, question, answer FROM `{$p}quince_blog_post_faq_lang` WHERE id_faq IN ({$idsIn})") ?: [] as $r) {
            $byId[(int) $r['id_faq']][(int) $r['id_lang']] = [
                'question' => (string) $r['question'],
                'answer' => (string) $r['answer'],
            ];
        }
        $out = [];
        foreach ($rows as $r) {
            $idFaq = (int) $r['id_faq'];
            $out[] = ['id_faq' => $idFaq, 'position' => (int) $r['position'], 'lang' => $byId[$idFaq] ?? []];
        }

        return $out;
    }

    /** @return array<int,array{question:string,answer:string}> */
    public static function getPostFaqsForLang(int $idPost, int $idLang): array
    {
        $rows = Db::getInstance()->executeS(
            "SELECT f.id_faq, fl.question, fl.answer
             FROM `" . _DB_PREFIX_ . "quince_blog_post_faq` f
             JOIN `" . _DB_PREFIX_ . "quince_blog_post_faq_lang` fl ON fl.id_faq = f.id_faq AND fl.id_lang = " . $idLang . '
             WHERE f.id_post = ' . $idPost . '
             ORDER BY f.position ASC, f.id_faq ASC'
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $q = trim((string) $r['question']);
            if ($q === '') {
                continue;
            }
            $out[] = ['question' => $q, 'answer' => (string) $r['answer']];
        }

        return $out;
    }

    /** @return int[] */
    public static function getPostPsTagIds(int $idPost): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_tag FROM `' . _DB_PREFIX_ . 'quince_blog_post_ps_tag` WHERE id_post = ' . $idPost
        ) ?: [];

        return array_map(fn ($r) => (int) $r['id_tag'], $rows);
    }

    /**
     * @param int[] $tagIds
     * @param int[] $excludeIds
     *
     * @return int[]
     */
    public static function getProductsByPsTags(array $tagIds, array $excludeIds, int $limit, int $idShop): array
    {
        if ($limit <= 0) {
            return [];
        }
        $tagIds = array_filter(array_map('intval', $tagIds), fn ($v) => $v > 0);
        if (empty($tagIds)) {
            return [];
        }
        $tagsIn = implode(',', $tagIds);
        $excludeSql = '';
        $excludeIds = array_filter(array_map('intval', $excludeIds), fn ($v) => $v > 0);
        if (!empty($excludeIds)) {
            $excludeSql = ' AND p.id_product NOT IN (' . implode(',', $excludeIds) . ')';
        }
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT p.id_product
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . "
             INNER JOIN `" . _DB_PREFIX_ . "product_tag` pt ON pt.id_product = p.id_product AND pt.id_tag IN ({$tagsIn})
             WHERE ps.active = 1
               AND ps.available_for_order = 1
               AND ps.visibility IN ('both', 'catalog')
               {$excludeSql}
             ORDER BY p.date_add DESC, p.id_product DESC
             LIMIT " . max(1, min(50, $limit))
        ) ?: [];

        return array_map(fn ($r) => (int) $r['id_product'], $rows);
    }

    /** @return int[] */
    public static function getPostProductIds(int $idPost): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'quince_blog_post_product`
             WHERE id_post = ' . $idPost . ' ORDER BY position ASC, id_product ASC'
        ) ?: [];

        return array_map(fn ($r) => (int) $r['id_product'], $rows);
    }

    public static function incrementViewCount(int $idPost): void
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'quince_blog_post` SET view_count = view_count + 1 WHERE id_post = ' . $idPost
        );
    }

    // ---------------------------------------------------------------------
    // KATEGORIE
    // ---------------------------------------------------------------------

    public static function listCategories(int $idLang, int $idShop, bool $onlyActive = false): array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $where = ['cl.id_lang = ' . $idLang, 'c.id_shop = ' . $idShop];
        if ($onlyActive) {
            $where[] = 'c.active = 1';
        }
        $sql = "SELECT c.id_category, c.id_parent, c.position, c.color, c.active,
                       cl.name, cl.slug, cl.description, cl.meta_title, cl.meta_description,
                       (SELECT COUNT(*) FROM `{$p}quince_blog_post_category` pc WHERE pc.id_category = c.id_category) AS post_count
                FROM `{$p}quince_blog_category` c
                JOIN `{$p}quince_blog_category_lang` cl ON cl.id_category = c.id_category
                WHERE " . implode(' AND ', $where) . '
                ORDER BY c.position ASC, cl.name ASC';

        return $db->executeS($sql) ?: [];
    }

    public static function getCategoryById(int $idCategory, int $idShop): ?array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $row = $db->getRow("SELECT * FROM `{$p}quince_blog_category` WHERE id_category = " . $idCategory . ' AND id_shop = ' . $idShop);
        if (!$row) {
            return null;
        }
        $row['lang'] = [];
        foreach ($db->executeS("SELECT * FROM `{$p}quince_blog_category_lang` WHERE id_category = " . $idCategory) ?: [] as $r) {
            $row['lang'][(int) $r['id_lang']] = $r;
        }

        return $row;
    }

    public static function findCategoryBySlug(string $slug, int $idLang, int $idShop): ?array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $row = $db->getRow(
            "SELECT c.*, cl.name, cl.description, cl.meta_title, cl.meta_description, cl.slug
             FROM `{$p}quince_blog_category` c
             JOIN `{$p}quince_blog_category_lang` cl ON cl.id_category = c.id_category
             WHERE cl.slug = '" . pSQL($slug) . "' AND cl.id_lang = " . $idLang . ' AND c.id_shop = ' . $idShop . ' AND c.active = 1'
        );

        return $row ?: null;
    }

    public static function saveCategory(array $base, array $byLang): int
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $now = date('Y-m-d H:i:s');
        $idCat = (int) ($base['id_category'] ?? 0);
        $idShop = (int) $base['id_shop'];

        $row = [
            'id_parent' => !empty($base['id_parent']) ? (int) $base['id_parent'] : null,
            'position' => (int) ($base['position'] ?? 0),
            'color' => !empty($base['color']) ? pSQL($base['color']) : null,
            'active' => !empty($base['active']) ? 1 : 0,
            'date_upd' => $now,
        ];
        if ($idCat) {
            $db->execute("UPDATE `{$p}quince_blog_category` SET " . self::buildSet($row) . ' WHERE id_category = ' . $idCat . ' AND id_shop = ' . $idShop);
        } else {
            $row['id_shop'] = $idShop;
            $row['date_add'] = $now;
            $db->insert('quince_blog_category', $row);
            $idCat = (int) $db->Insert_ID();
        }
        foreach ($byLang as $idLang => $fields) {
            $idLang = (int) $idLang;
            $slug = !empty($fields['slug']) ? self::slugify($fields['slug']) : self::slugify($fields['name'] ?? '');
            $slug = self::ensureUniqueCategorySlug($slug, $idShop, $idLang, $idCat);
            $data = [
                'id_category' => $idCat,
                'id_lang' => $idLang,
                'id_shop' => $idShop,
                'name' => (string) ($fields['name'] ?? ''),
                'slug' => (string) $slug,
                'description' => (string) ($fields['description'] ?? ''),
                'meta_title' => (string) ($fields['meta_title'] ?? ''),
                'meta_description' => (string) ($fields['meta_description'] ?? ''),
            ];
            // `id_shop` w WHERE: ten sam powód co w `savePost()`.
            $exists = (int) $db->getValue("SELECT 1 FROM `{$p}quince_blog_category_lang` WHERE id_category = " . $idCat . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop);
            if ($exists) {
                $db->execute("UPDATE `{$p}quince_blog_category_lang` SET " . self::buildSet($data, ['id_category', 'id_lang']) . ' WHERE id_category = ' . $idCat . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop);
            } else {
                self::insertEscaped('quince_blog_category_lang', $data);
            }
        }

        return $idCat;
    }

    public static function deleteCategory(int $idCategory, int $idShop): bool
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $owned = (int) $db->getValue("SELECT 1 FROM `{$p}quince_blog_category` WHERE id_category = " . $idCategory . ' AND id_shop = ' . $idShop);
        if (!$owned) {
            return false;
        }
        $db->execute("DELETE FROM `{$p}quince_blog_post_category` WHERE id_category = " . $idCategory);
        $db->execute("DELETE FROM `{$p}quince_blog_category_lang` WHERE id_category = " . $idCategory);
        $db->execute("DELETE FROM `{$p}quince_blog_category`      WHERE id_category = " . $idCategory);

        return true;
    }

    // ---------------------------------------------------------------------
    // TAGI
    // ---------------------------------------------------------------------

    public static function listTags(int $idLang, int $idShop): array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;

        return $db->executeS(
            "SELECT t.id_tag, tl.name, tl.slug,
                    (SELECT COUNT(*) FROM `{$p}quince_blog_post_tag` pt WHERE pt.id_tag = t.id_tag) AS post_count
             FROM `{$p}quince_blog_tag` t
             JOIN `{$p}quince_blog_tag_lang` tl ON tl.id_tag = t.id_tag
             WHERE tl.id_lang = " . $idLang . ' AND t.id_shop = ' . $idShop . '
             ORDER BY tl.name ASC'
        ) ?: [];
    }

    public static function getTagById(int $idTag, int $idShop): ?array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $row = $db->getRow("SELECT * FROM `{$p}quince_blog_tag` WHERE id_tag = " . $idTag . ' AND id_shop = ' . $idShop);
        if (!$row) {
            return null;
        }
        $row['lang'] = [];
        foreach ($db->executeS("SELECT * FROM `{$p}quince_blog_tag_lang` WHERE id_tag = " . $idTag) ?: [] as $r) {
            $row['lang'][(int) $r['id_lang']] = $r;
        }

        return $row;
    }

    public static function findTagBySlug(string $slug, int $idLang, int $idShop): ?array
    {
        $row = Db::getInstance()->getRow(
            'SELECT t.*, tl.name, tl.slug
             FROM `' . _DB_PREFIX_ . 'quince_blog_tag` t
             JOIN `' . _DB_PREFIX_ . 'quince_blog_tag_lang` tl ON tl.id_tag = t.id_tag
             WHERE tl.slug = \'' . pSQL($slug) . '\' AND tl.id_lang = ' . $idLang . ' AND t.id_shop = ' . $idShop
        );

        return $row ?: null;
    }

    public static function saveTag(array $base, array $byLang): int
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $idTag = (int) ($base['id_tag'] ?? 0);
        $idShop = (int) $base['id_shop'];
        if (!$idTag) {
            $db->insert('quince_blog_tag', ['id_shop' => $idShop, 'date_add' => date('Y-m-d H:i:s')]);
            $idTag = (int) $db->Insert_ID();
        }
        foreach ($byLang as $idLang => $fields) {
            $idLang = (int) $idLang;
            $slug = !empty($fields['slug']) ? self::slugify($fields['slug']) : self::slugify($fields['name'] ?? '');
            $slug = self::ensureUniqueTagSlug($slug, $idShop, $idLang, $idTag);
            $data = ['id_tag' => $idTag, 'id_lang' => $idLang, 'id_shop' => $idShop, 'name' => (string) ($fields['name'] ?? ''), 'slug' => (string) $slug];
            // `id_shop` w WHERE: ten sam powód co w `savePost()`.
            $exists = (int) $db->getValue("SELECT 1 FROM `{$p}quince_blog_tag_lang` WHERE id_tag = " . $idTag . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop);
            if ($exists) {
                $db->execute("UPDATE `{$p}quince_blog_tag_lang` SET " . self::buildSet($data, ['id_tag', 'id_lang']) . ' WHERE id_tag = ' . $idTag . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop);
            } else {
                self::insertEscaped('quince_blog_tag_lang', $data);
            }
        }

        return $idTag;
    }

    public static function deleteTag(int $idTag, int $idShop): bool
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $owned = (int) $db->getValue("SELECT 1 FROM `{$p}quince_blog_tag` WHERE id_tag = " . $idTag . ' AND id_shop = ' . $idShop);
        if (!$owned) {
            return false;
        }
        $db->execute("DELETE FROM `{$p}quince_blog_post_tag` WHERE id_tag = " . $idTag);
        $db->execute("DELETE FROM `{$p}quince_blog_tag_lang` WHERE id_tag = " . $idTag);
        $db->execute("DELETE FROM `{$p}quince_blog_tag`      WHERE id_tag = " . $idTag);

        return true;
    }

    public static function getPostCategories(int $idPost, int $idLang): array
    {
        return Db::getInstance()->executeS(
            'SELECT c.id_category, cl.name, cl.slug, c.color
             FROM `' . _DB_PREFIX_ . 'quince_blog_post_category` pc
             JOIN `' . _DB_PREFIX_ . 'quince_blog_category` c ON c.id_category = pc.id_category
             JOIN `' . _DB_PREFIX_ . 'quince_blog_category_lang` cl ON cl.id_category = c.id_category AND cl.id_lang = ' . $idLang . '
             WHERE pc.id_post = ' . $idPost . ' AND c.active = 1
             ORDER BY c.position ASC'
        ) ?: [];
    }

    public static function getRelatedPosts(int $idPost, int $idLang, int $idShop, int $limit = 3): array
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;

        return $db->executeS(
            "SELECT DISTINCT p.id_post, p.featured_id_image, p.date_pub,
                    pl.title, pl.slug, pl.excerpt
             FROM `{$p}quince_blog_post` p
             JOIN `{$p}quince_blog_post_lang` pl ON pl.id_post = p.id_post AND pl.id_lang = " . $idLang . "
             LEFT JOIN `{$p}quince_blog_post_category` pc ON pc.id_post = p.id_post
             LEFT JOIN `{$p}quince_blog_post_tag` pt ON pt.id_post = p.id_post
             WHERE p.id_shop = " . $idShop . "
               AND p.status = 'published'
               AND p.id_post <> " . $idPost . '
               AND (
                   pc.id_category IN (SELECT id_category FROM `' . $p . 'quince_blog_post_category` WHERE id_post = ' . $idPost . ')
                   OR pt.id_tag IN (SELECT id_tag FROM `' . $p . 'quince_blog_post_tag` WHERE id_post = ' . $idPost . ')
               )
             ORDER BY p.date_pub DESC
             LIMIT ' . $limit
        ) ?: [];
    }

    // ---------------------------------------------------------------------
    // OBRAZY
    // ---------------------------------------------------------------------

    public static function getImageById(int $idImage): ?array
    {
        $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'quince_blog_image` WHERE id_image = ' . $idImage);

        return $row ?: null;
    }

    /**
     * To samo co `getImageById()`, ale dla wielu zdjęć naraz: jedno
     * zapytanie zamiast jednego na pozycję listy wpisów.
     *
     * @param int[] $ids
     *
     * @return array<int,array<string,mixed>> zdjęcia pod kluczem id_image
     */
    public static function getImagesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'quince_blog_image` WHERE id_image IN (' . implode(',', $ids) . ')'
        ) ?: [];

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id_image']] = $row;
        }

        return $byId;
    }

    public static function insertImage(int $idPost, string $filename, ?int $width = null, ?int $height = null, int $position = 0, bool $isFeatured = false): int
    {
        Db::getInstance()->insert('quince_blog_image', [
            'id_post' => $idPost,
            'filename' => pSQL($filename),
            'width' => $width,
            'height' => $height,
            'position' => $position,
            'is_featured' => $isFeatured ? 1 : 0,
            'date_add' => date('Y-m-d H:i:s'),
        ]);

        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * Podpis pod zdjęciem, odczytany z `caption_json`, po jednej pozycji na
     * język. Brak kolumny albo pusty JSON zwraca pustą tablicę. Szablon
     * i panel traktują to jak brak podpisu, nie jak błąd.
     *
     * @return array<int,string>
     */
    public static function getImageCaptions(int $idImage): array
    {
        $json = (string) (Db::getInstance()->getValue(
            'SELECT caption_json FROM `' . _DB_PREFIX_ . 'quince_blog_image` WHERE id_image = ' . $idImage
        ) ?: '');
        $decoded = $json !== '' ? json_decode($json, true) : null;
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $idLang => $text) {
            $out[(int) $idLang] = (string) $text;
        }

        return $out;
    }

    /** @param array<int,string> $captionByLang */
    public static function updateImageCaption(int $idImage, array $captionByLang): void
    {
        $clean = [];
        foreach ($captionByLang as $idLang => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $clean[(int) $idLang] = $text;
            }
        }
        $json = $clean !== [] ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'quince_blog_image` SET caption_json = '
            . ($json !== null ? "'" . pSQL($json) . "'" : 'NULL')
            . ' WHERE id_image = ' . $idImage
        );
    }

    public static function deleteImage(int $idImage): bool
    {
        $img = self::getImageById($idImage);
        if (!$img) {
            return false;
        }
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'quince_blog_image` WHERE id_image = ' . $idImage);
        $f = _PS_IMG_DIR_ . 'blog' . DIRECTORY_SEPARATOR . basename($img['filename']);
        if (is_file($f)) {
            @unlink($f);
        }

        return true;
    }

    // ---------------------------------------------------------------------
    // POMOCNICZE
    // ---------------------------------------------------------------------

    public static function slugify(string $input): string
    {
        $s = Tools::str2url($input);

        return $s !== false && $s !== '' ? $s : strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $input) ?? 'post');
    }

    /** Slug unikalny w obrębie (sklep, język): dwa sklepy mogą mieć ten sam slug, dwa posty jednego sklepu nie. */
    private static function ensureUniquePostSlug(string $slug, int $idShop, int $idLang, int $idPost): string
    {
        return self::ensureUniqueSlug('quince_blog_post_lang', 'slug', $slug, $idShop, $idLang, 'id_post', $idPost);
    }

    private static function ensureUniqueCategorySlug(string $slug, int $idShop, int $idLang, int $idCategory): string
    {
        return self::ensureUniqueSlug('quince_blog_category_lang', 'slug', $slug, $idShop, $idLang, 'id_category', $idCategory);
    }

    private static function ensureUniqueTagSlug(string $slug, int $idShop, int $idLang, int $idTag): string
    {
        return self::ensureUniqueSlug('quince_blog_tag_lang', 'slug', $slug, $idShop, $idLang, 'id_tag', $idTag);
    }

    private static function ensureUniqueSlug(string $table, string $col, string $slug, int $idShop, int $idLang, string $pkCol, int $pkValue): string
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $base = $slug ?: 'post';
        $candidate = $base;
        $i = 1;
        while (true) {
            $hit = (int) $db->getValue(
                "SELECT COUNT(*) FROM `{$p}{$table}`
                 WHERE `{$col}` = '" . pSQL($candidate) . "'
                   AND id_lang = " . $idLang . '
                   AND id_shop = ' . $idShop . "
                   AND `{$pkCol}` <> " . $pkValue
            );
            if ($hit === 0) {
                return $candidate;
            }
            ++$i;
            $candidate = $base . '-' . $i;
        }
    }

    /**
     * `Db::insert()` w tej wersji core NIE escapuje wartości tekstowych:
     * owija je w cudzysłów wprost, bez `pSQL()` (w przeciwieństwie do
     * `buildSet()` używanego przy UPDATE). Apostrof w tytule, treści czy
     * odpowiedzi FAQ wywala wtedy zapytanie. Ta funkcja escapuje tekstowe
     * wartości TUŻ PRZED insertem, więc `$data` przekazywane też do
     * `buildSet()` (UPDATE) zostaje nietknięte. Podwójne escapowanie
     * zepsułoby apostrofy na UPDATE tak samo, jak ich brak psuje INSERT.
     */
    private static function insertEscaped(string $table, array $row): void
    {
        $escaped = [];
        foreach ($row as $col => $val) {
            $escaped[$col] = is_string($val) ? pSQL($val, true) : $val;
        }
        Db::getInstance()->insert($table, $escaped);
    }

    private static function buildSet(array $row, array $skip = []): string
    {
        $parts = [];
        foreach ($row as $col => $val) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            if ($val === null) {
                $parts[] = "`{$col}` = NULL";
            } elseif (is_int($val)) {
                $parts[] = "`{$col}` = " . $val;
            } else {
                $parts[] = "`{$col}` = '" . pSQL((string) $val, true) . "'";
            }
        }

        return implode(', ', $parts);
    }
}
