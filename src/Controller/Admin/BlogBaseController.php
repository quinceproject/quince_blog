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

use Quince\Engine\Controller\Admin\QuinceController;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Wspólna podstawa ekranów modułu.
 *
 * Jest tu jedna rzecz: spis zakładek, wspólny dla wszystkich widoków. Ekran
 * pominięty w spisie nie ma zakładki i nie da się do niego wrócić.
 */
abstract class BlogBaseController extends QuinceController
{
    protected const DOMAIN = 'Modules.Quinceblog.Admin';

    /** @return array<int,array{label:string,route:string}> */
    protected function tabs(): array
    {
        return [
            ['label' => 'Posts', 'route' => 'quince_blog_posts'],
            ['label' => 'Categories', 'route' => 'quince_blog_categories'],
            ['label' => 'Tags', 'route' => 'quince_blog_tags'],
            ['label' => 'Teasers', 'route' => 'quince_blog_teasers'],
            ['label' => 'Settings', 'route' => 'quince_blog_settings'],
        ];
    }

    /**
     * Nagłówek, zakładki i podświetlenie pozycji w bocznym pasku.
     *
     * @return array<string,mixed>
     */
    protected function chrome(string $route): array
    {
        return [
            'layoutTitle' => 'Blog',
            'layoutTitleDomain' => self::DOMAIN,
            'quinceTabs' => $this->tabs(),
            'quinceTabsDomain' => self::DOMAIN,
            'quinceRoute' => $route,
            // Boczny pasek podświetla pozycję modułu, która wskazuje wpisy.
            // Reszta ekranów to jego zakładki, nie osobne pozycje w menu.
            'quinceNavRoute' => 'quince_blog_posts',
        ];
    }
}
