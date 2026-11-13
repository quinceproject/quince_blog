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
 * Quince Blog 0.2.0 → 0.3.0
 *
 * - quince_blog_post.reading_time usunięty: liczony przy każdym zapisie,
 *   ale nigdzie na froncie ani w panelu nie wyświetlany.
 *
 * Idempotentnie: sprawdza istnienie kolumny przed DROP.
 */
function upgrade_module_0_3_0($module)
{
    $db = Db::getInstance();
    $p = _DB_PREFIX_;

    $exists = (int) $db->getValue(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '" . pSQL($p . 'quince_blog_post') . "'
           AND COLUMN_NAME = 'reading_time'"
    );
    if ($exists > 0) {
        $db->execute("ALTER TABLE `{$p}quince_blog_post` DROP COLUMN `reading_time`");
    }

    return true;
}
