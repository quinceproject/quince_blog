<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Przepisane z quince_engine: autoloader ręczny, dopóki moduł nie ma
    // composerowego vendor/ — src/ zmiany widać od razu, bez `composer dump-autoload`.
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Quince\\Blog\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

/**
 * Blog Quince: wpisy, kategorie, tagi, zajawki do osadzenia gdziekolwiek.
 *
 * Przepisany z `flavor_blog` (dalej nietknięty — żadna z tych dwóch
 * instalacji się nie kasuje). Różnice
 * wobec pierwowzoru:
 *   - wielosklepowość: `id_shop` na wpisie, kategorii i tagu — tamten moduł
 *     dzielił treść między wszystkie sklepy instalacji, co przy kilku markach
 *     w jednej grupie sklepów pokazywałoby
 *     wszystkim ten sam blog,
 *   - panel: kontrolery Symfony + Twig (qc-*), jak reszta silnika Quince —
 *     tamten używał starego `ModuleAdminController` + Smarty,
 *   - front: szablony Smarty na tokenach Quince (q-*) zamiast `--flavor-*`,
 *   - zajawki: trzy układy z makiety `makiety/zajawki-blogowe.html`
 *     (wyróżniony+lista, rząd równych kafli, taśma zwarta) zamiast dwóch
 *     w pierwowzorze.
 */
class Quince_Blog extends Module
{
    public function __construct()
    {
        $this->name = 'quince_blog';
        $this->tab = 'front_office_features';
        $this->version = '0.4.1';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];
        $this->dependencies = ['quince_engine'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Quince Blog', [], 'Modules.Quinceblog.Admin');
        $this->description = $this->trans(
            'Multilingual, multistore blog: posts, categories, tags, teasers to embed anywhere.',
            [],
            'Modules.Quinceblog.Admin'
        );
        $this->confirmUninstall = $this->trans('Uninstall the module? All blog data will be removed.', [], 'Modules.Quinceblog.Admin');
    }

    // =========================================================================
    // INSTALL / UNINSTALL
    // =========================================================================

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        // Zajawki NIE mają jednego stałego hooka — każde umieszczenie z panelu
        // dostaje własny, dynamicznie nazwany hook (patrz TeaserSettings
        // i __call() niżej). Tu tylko dorejestrowanie tych, które już istnieją
        // w konfiguracji (ponowna instalacja modułu).
        (new \Quince\Blog\Settings\TeaserSettings())->registerAllHooks();

        return $this->registerHook([
            'moduleRoutes',
            'actionQuinceRegisterFeature',
            'actionFrontControllerSetMedia',
        ])
            && $this->installDb()
            && $this->installTabs()
            && $this->installImageDir()
            && $this->installDefaults()
            && $this->generateApiToken()
            && $this->installModuleGroups();
    }

    public function uninstall(): bool
    {
        $this->uninstallTabs();
        $this->uninstallDb();

        foreach ([
            'QUINCE_BLOG_API_TOKEN',
            'QUINCE_BLOG_POSTS_PER_PAGE',
            'QUINCE_BLOG_ROUTE_PREFIX',
            'QUINCE_BLOG_DEFAULT_OG_IMAGE',
            \Quince\Blog\Settings\SeoSettings::KEY,
        ] as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    // =========================================================================
    // BAZA DANYCH
    // =========================================================================

    private function installDb(): bool
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $sql = [];

        // POST — pola niezależne od języka. `id_shop`: wpis należy do JEDNEGO
        // sklepu — artykuł napisany dla jednej marki nie ma czego robić
        // w sklepie drugiej, inna marka, inny czytelnik.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post` (
            `id_post` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            `status` ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
            `featured_id_image` INT UNSIGNED NULL DEFAULT NULL,
            `author_id_employee` INT UNSIGNED NULL DEFAULT NULL,
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `featured_products_icon` VARCHAR(64) NULL DEFAULT NULL,
            `faq_icon` VARCHAR(64) NULL DEFAULT NULL,
            `featured_products_max` INT UNSIGNED NOT NULL DEFAULT 0,
            `date_pub` DATETIME NULL DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_post`),
            KEY `shop_status_pub` (`id_shop`, `status`, `date_pub`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // POST_LANG — pola tłumaczone. Slug unikalny per (sklep, język): PL i EN
        // mogą dzielić ciąg, ale dwa sklepy też, bo slug wchodzi do adresu razem
        // z prefiksem bloga tego sklepu.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post_lang` (
            `id_post` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL DEFAULT '',
            `slug` VARCHAR(255) NOT NULL DEFAULT '',
            `excerpt` TEXT NULL DEFAULT NULL,
            `content` LONGTEXT NULL DEFAULT NULL,
            `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_description` VARCHAR(512) NOT NULL DEFAULT '',
            `og_image_override` VARCHAR(255) NULL DEFAULT NULL,
            `featured_products_heading` VARCHAR(255) NOT NULL DEFAULT '',
            `faq_heading` VARCHAR(255) NOT NULL DEFAULT '',
            `robots` ENUM('index','noindex') NOT NULL DEFAULT 'index',
            PRIMARY KEY (`id_post`, `id_lang`),
            UNIQUE KEY `slug_shop_lang` (`slug`, `id_shop`, `id_lang`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // CATEGORY — hierarchia, parent_id puste = korzeń. Per sklep z tego
        // samego powodu co post: dwie marki nie dzielą jednego drzewa kategorii.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_category` (
            `id_category` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            `id_parent` INT UNSIGNED NULL DEFAULT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `color` VARCHAR(7) NULL DEFAULT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_category`),
            KEY `shop_parent` (`id_shop`, `id_parent`),
            KEY `position` (`position`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_category_lang` (
            `id_category` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL DEFAULT '',
            `slug` VARCHAR(255) NOT NULL DEFAULT '',
            `description` TEXT NULL DEFAULT NULL,
            `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_description` VARCHAR(512) NOT NULL DEFAULT '',
            PRIMARY KEY (`id_category`, `id_lang`),
            UNIQUE KEY `slug_shop_lang` (`slug`, `id_shop`, `id_lang`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // POST <-> CATEGORY (M2M). Bez własnego `id_shop` — obie strony już
        // należą do tego samego sklepu, bo kategoria spoza sklepu posta nie
        // trafi na listę wyboru w panelu.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post_category` (
            `id_post` INT UNSIGNED NOT NULL,
            `id_category` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_post`, `id_category`),
            KEY `id_category` (`id_category`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // TAG — per sklep, ten sam powód co kategoria: luźne słownictwo własne
        // jednej marki.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_tag` (
            `id_tag` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_tag`),
            KEY `id_shop` (`id_shop`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_tag_lang` (
            `id_tag` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `name` VARCHAR(128) NOT NULL DEFAULT '',
            `slug` VARCHAR(128) NOT NULL DEFAULT '',
            PRIMARY KEY (`id_tag`, `id_lang`),
            UNIQUE KEY `slug_shop_lang` (`slug`, `id_shop`, `id_lang`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post_tag` (
            `id_post` INT UNSIGNED NOT NULL,
            `id_tag` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_post`, `id_tag`),
            KEY `id_tag` (`id_tag`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // FAQ pod postem — akordeon pytań, wielojęzyczny per pozycja.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post_faq` (
            `id_faq` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_post` INT UNSIGNED NOT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_faq`),
            KEY `id_post` (`id_post`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post_faq_lang` (
            `id_faq` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `question` VARCHAR(500) NOT NULL DEFAULT '',
            `answer` TEXT NULL,
            PRIMARY KEY (`id_faq`, `id_lang`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // POST <-> natywny tag PS — dobór polecanych produktów po tagach
        // produktu. Ręczny wybór ma pierwszeństwo, te uzupełniają do limitu.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post_ps_tag` (
            `id_post` INT UNSIGNED NOT NULL,
            `id_tag` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_post`, `id_tag`),
            KEY `id_tag` (`id_tag`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // POST <-> PRODUCT (M2M) — ręcznie wybrane produkty-zajawki pod postem.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_post_product` (
            `id_post` INT UNSIGNED NOT NULL,
            `id_product` INT UNSIGNED NOT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_post`, `id_product`),
            KEY `id_product` (`id_product`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // Ślad importu WP — po nim budują się przekierowania 301 ze starych
        // adresów. Per sklep: ten sam adres WP mógłby trafić do dwóch sklepów
        // z osobnymi importami.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_import_map` (
            `id_map` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            `wp_id` INT UNSIGNED NOT NULL,
            `wp_slug` VARCHAR(255) NOT NULL,
            `wp_url` VARCHAR(500) NOT NULL,
            `id_post` INT UNSIGNED NOT NULL,
            `date_imported` DATETIME NOT NULL,
            PRIMARY KEY (`id_map`),
            UNIQUE KEY `wp_url_shop` (`wp_url`, `id_shop`),
            KEY `id_post` (`id_post`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // Galeria — bez własnego `id_shop`, dziedziczy przez `id_post`.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_blog_image` (
            `id_image` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_post` INT UNSIGNED NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `alt_json` TEXT NULL DEFAULT NULL,
            `caption_json` TEXT NULL DEFAULT NULL,
            `width` INT UNSIGNED NULL DEFAULT NULL,
            `height` INT UNSIGNED NULL DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_image`),
            KEY `id_post` (`id_post`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        foreach ($sql as $query) {
            if (!$db->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb(): bool
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $tables = [
            'quince_blog_image',
            'quince_blog_import_map',
            'quince_blog_post_product',
            'quince_blog_post_ps_tag',
            'quince_blog_post_faq_lang',
            'quince_blog_post_faq',
            'quince_blog_post_tag',
            'quince_blog_tag_lang',
            'quince_blog_tag',
            'quince_blog_post_category',
            'quince_blog_category_lang',
            'quince_blog_category',
            'quince_blog_post_lang',
            'quince_blog_post',
        ];

        foreach ($tables as $table) {
            if (!$db->execute("DROP TABLE IF EXISTS `{$p}{$table}`")) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // ZAKŁADKI (kontrolery Symfony pod Admin, trasy w config/routes.yml)
    // =========================================================================

    private function installTabs(): bool
    {
        // Jeden wspólny rodzic na uprawnienia, tak jak `AdminQuince` w silniku —
        // wszystkie trasy modułu wskazują ten sam `_legacy_controller`.
        if (Tab::getIdFromClassName('AdminQuinceBlog')) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminQuinceBlog';
        $tab->module = $this->name;
        $tab->id_parent = -1;
        $tab->active = 0;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Quince Blog';
        }

        return (bool) $tab->add();
    }

    private function uninstallTabs(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('AdminQuinceBlog');
        if (!$idTab) {
            return true;
        }

        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }

    // =========================================================================
    // PLIKI I USTAWIENIA DOMYŚLNE
    // =========================================================================

    private function installImageDir(): bool
    {
        $dir = _PS_ROOT_DIR_ . '/img/blog';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "php_flag engine off\n<FilesMatch \"\\.(php|phtml|php\\d)$\">\nDeny from all\n</FilesMatch>\n");
        }

        return true;
    }

    private function installDefaults(): bool
    {
        Configuration::updateValue('QUINCE_BLOG_POSTS_PER_PAGE', '12');
        Configuration::updateValue('QUINCE_BLOG_ROUTE_PREFIX', 'blog');

        return true;
    }

    private function generateApiToken(): bool
    {
        if ((string) Configuration::get('QUINCE_BLOG_API_TOKEN') !== '') {
            return true;
        }

        return Configuration::updateValue('QUINCE_BLOG_API_TOKEN', bin2hex(random_bytes(32)));
    }

    public function installModuleGroups(): bool
    {
        foreach (Shop::getShops(false, null, true) as $idShop) {
            $idGroup = (int) Db::getInstance()->getValue(
                'SELECT id_group FROM ' . _DB_PREFIX_ . 'group_shop WHERE id_shop = ' . (int) $idShop
            );
            if (!$idGroup) {
                continue;
            }

            $idModule = (int) $this->id;
            $exists = Db::getInstance()->getValue(
                "SELECT 1 FROM " . _DB_PREFIX_ . "module_group
                 WHERE id_module = {$idModule} AND id_shop = " . (int) $idShop . " AND id_group = {$idGroup}"
            );
            if ($exists) {
                continue;
            }

            Db::getInstance()->insert('module_group', [
                'id_module' => $idModule,
                'id_shop'   => (int) $idShop,
                'id_group'  => $idGroup,
            ]);
        }

        return true;
    }

    // =========================================================================
    // TRASA CZYSTYCH ADRESÓW
    // =========================================================================

    /**
     * `/{prefix}`, `/{prefix}/{slug}`, `/{prefix}/category/{slug}`,
     * `/{prefix}/tag/{slug}`, `/{prefix}/feed.xml` — prefiks konfigurowalny
     * w ustawieniach, osobno dla każdego sklepu i każdego jego języka (ten sam
     * mechanizm, co `/llms.txt` w silniku).
     */
    public function hookModuleRoutes(): array
    {
        $prefix = trim((string) Configuration::get('QUINCE_BLOG_ROUTE_PREFIX')) ?: 'blog';

        return [
            'module-quince_blog-list' => [
                'controller' => 'list',
                'rule' => $prefix,
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => 'quince_blog', 'controller' => 'list'],
            ],
            'module-quince_blog-feed' => [
                'controller' => 'feed',
                'rule' => $prefix . '/feed.xml',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => 'quince_blog', 'controller' => 'feed'],
            ],
            'module-quince_blog-category' => [
                'controller' => 'category',
                'rule' => $prefix . '/category/{slug}',
                'keywords' => ['slug' => ['regexp' => '[\w\-]+', 'param' => 'slug']],
                'params' => ['fc' => 'module', 'module' => 'quince_blog', 'controller' => 'category'],
            ],
            'module-quince_blog-tag' => [
                'controller' => 'tag',
                'rule' => $prefix . '/tag/{slug}',
                'keywords' => ['slug' => ['regexp' => '[\w\-]+', 'param' => 'slug']],
                'params' => ['fc' => 'module', 'module' => 'quince_blog', 'controller' => 'tag'],
            ],
            'module-quince_blog-post' => [
                'controller' => 'post',
                'rule' => $prefix . '/{slug}',
                'keywords' => ['slug' => ['regexp' => '[\w\-]+', 'param' => 'slug']],
                'params' => ['fc' => 'module', 'module' => 'quince_blog', 'controller' => 'post'],
            ],
        ];
    }

    // =========================================================================
    // ZGŁOSZENIE DO MENU QUINCE
    // =========================================================================

    /** @return array<int,array<string,string>> */
    public function hookActionQuinceRegisterFeature(): array
    {
        return [[
            'id' => 'quince_blog',
            'label' => $this->trans('Blog', [], 'Modules.Quinceblog.Admin'),
            'icon' => 'file',
            'route' => 'quince_blog_posts',
        ]];
    }

    // =========================================================================
    // ZASOBY FRONTU
    // =========================================================================

    public function hookActionFrontControllerSetMedia(): void
    {
        // CSS/JS stron bloga idzie przez `theme.yml` (klucze `blog-list`,
        // `blog-post` itd.), tak jak reszta motywu Quince — nie tutaj. Ten
        // hook zostaje jako miejsce na JS-def, gdyby front wyspy go potrzebowały.
    }

    // =========================================================================
    // ZAJAWKI — każde umieszczenie z panelu (Blog → Teasers) ma WŁASNY hook,
    // bez żadnego parametru: `displayQuinceBlogTeasers` + klucz, np.
    // `displayQuinceBlogTeasersRow4`. Nazwa jest dynamiczna (sklepikarz
    // dopisuje umieszczenia w panelu), więc PHP nie zna jej z góry — łapie ją
    // __call(), tak jak core PrestaShopa wprost przewiduje dla modułów
    // z dynamicznie nazwanymi hookami (zob. komentarz w Hook::callHookOn()).
    // =========================================================================

    /**
     * @param array<int,mixed> $arguments
     */
    public function __call(string $name, array $arguments)
    {
        $prefix = 'hook' . ucfirst(\Quince\Blog\Settings\TeaserSettings::HOOK_PREFIX);

        if (strncmp($name, $prefix, strlen($prefix)) !== 0) {
            return null;
        }

        $key = lcfirst(substr($name, strlen($prefix)));
        if ($key === '') {
            return null;
        }

        $settings = new \Quince\Blog\Settings\TeaserSettings();
        $data = $settings->forTemplate($key, (int) Context::getContext()->language->id);

        if ($data === null) {
            return '';
        }

        $this->context->smarty->assign($data);

        return $this->fetch('module:quince_blog/views/templates/hook/teasers.tpl');
    }
}
