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

class Quince_BlogFeedModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        // Pomija parent::initContent() budujące koszyk/layout — oddajemy surowy XML.
        $this->display();
    }

    public function display()
    {
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;
        $shopName = (string) Configuration::get('PS_SHOP_NAME');

        $posts = Repository::listPosts([
            'id_shop' => $idShop,
            'lang_id' => $idLang,
            'status' => 'published',
            'limit' => 50,
        ]);

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo "<channel>\n";
        echo '  <title>' . htmlspecialchars($shopName . ' — ' . $this->trans('Blog', [], 'Modules.Quinceblog.Shop'), ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n";
        echo '  <link>' . htmlspecialchars(Seo::listUrl($this->context), ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</link>\n";
        echo "  <description></description>\n";
        echo '  <language>' . htmlspecialchars($this->context->language->iso_code, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</language>\n";
        echo '  <atom:link href="' . htmlspecialchars(Seo::listUrl($this->context) . '/feed.xml', ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($posts as $p) {
            $url = Seo::postUrl($p['slug'], $this->context);
            $pubDate = !empty($p['date_pub']) ? date(DATE_RSS, strtotime($p['date_pub'])) : date(DATE_RSS);
            echo "  <item>\n";
            echo '    <title>' . htmlspecialchars((string) $p['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n";
            echo '    <link>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</link>\n";
            echo '    <guid isPermaLink="true">' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</guid>\n";
            echo '    <pubDate>' . $pubDate . "</pubDate>\n";
            $desc = trim(strip_tags((string) $p['excerpt']));
            if ($desc !== '') {
                echo '    <description><![CDATA[' . $desc . ']]></description>' . "\n";
            }
            echo "  </item>\n";
        }
        echo "</channel>\n</rss>\n";
        exit;
    }
}
