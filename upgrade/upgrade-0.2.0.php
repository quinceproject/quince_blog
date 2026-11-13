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
 * Quince Blog 0.1.0 → 0.2.0
 *
 * - quince_blog_image.caption_json (podpis pod zdjęciem wyróżnionym, na
 *   wpis, wielojęzyczny, ten sam zapis co `alt_json`)
 *
 * Idempotentnie: sprawdza istnienie kolumny przed ALTER, bezpieczne do
 * odpalenia na częściowo zmigrowanej bazie.
 */
function upgrade_module_0_2_0($module)
{
    $db = Db::getInstance();
    $p = _DB_PREFIX_;

    $colChecks = [
        ['table' => 'quince_blog_image', 'col' => 'caption_json', 'ddl' => "ADD COLUMN `caption_json` TEXT NULL DEFAULT NULL AFTER `alt_json`"],
    ];
    foreach ($colChecks as $c) {
        $exists = (int) $db->getValue(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '" . pSQL($p . $c['table']) . "'
               AND COLUMN_NAME = '" . pSQL($c['col']) . "'"
        );
        if ($exists === 0) {
            $db->execute("ALTER TABLE `{$p}{$c['table']}` " . $c['ddl']);
        }
    }

    return true;
}
