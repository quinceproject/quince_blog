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

use Quince\Blog\Settings\BlogLayoutSettings;
use Quince\Blog\Settings\SeoSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Ustawienia bloga: szerokość strony wpisu, powiązane wpisy oraz tytuł i opis
 * listy wpisów i stron tagów w wynikach wyszukiwania.
 */
class SettingsController extends BlogBaseController
{
    public function __construct(
        private readonly BlogLayoutSettings $settings,
        private readonly SeoSettings $seo
    ) {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/settings.html.twig', $this->chrome('quince_blog_settings') + [
            'engineColumns' => $this->settings->engineColumns(),
            'contentColumns' => $this->settings->contentColumns(),
            'imageColumns' => $this->settings->imageColumns(),
            'relatedCount' => $this->settings->relatedCount(),
            'languages' => $this->languages(),
            'seo' => $this->seo->all(),
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_settings_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_settings');
        }

        $this->settings->save($request->request->all());
        $this->seo->save($request->request->all(), array_column($this->languages(), 'id_lang'));
        $this->addFlash('success', $this->trans('Settings saved.', self::DOMAIN));

        return $this->redirectToRoute('quince_blog_settings');
    }
}
