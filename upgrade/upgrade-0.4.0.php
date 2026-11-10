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

/**
 * Quince Blog 0.3.0 → 0.4.0
 *
 * - quince_blog_category.featured_id_image usunięty: nigdy nie miał pola
 *   w panelu, a sklep zdecydował, że nie potrzebuje zdjęcia kategorii.
 *
 * Idempotentnie: sprawdza istnienie kolumny przed DROP.
 */
function upgrade_module_0_4_0($module)
{
    $db = Db::getInstance();
    $p = _DB_PREFIX_;

    $exists = (int) $db->getValue(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '" . pSQL($p . 'quince_blog_category') . "'
           AND COLUMN_NAME = 'featured_id_image'"
    );
    if ($exists > 0) {
        $db->execute("ALTER TABLE `{$p}quince_blog_category` DROP COLUMN `featured_id_image`");
    }

    return true;
}
