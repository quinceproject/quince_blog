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

use Quince\Blog\Repository;
use Quince\Blog\Settings\TeaserSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Umieszczenia zajawek bloga: lista dowolnej długości, każde pod WŁASNYM
 * hookiem (`TeaserSettings::HOOK_PREFIX` + klucz), bez żadnego parametru.
 * Lista i edycja to dwa osobne ekrany — tak samo jak Posts/Categories.
 */
class TeasersController extends BlogBaseController
{
    /** Sentinel w adresie `{key}/edit`, którego sanitizeKey() nigdy nie wyprodukuje (zaczyna się podkreślnikiem). */
    private const NEW = '_new';

    public function __construct(private readonly TeaserSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        $placements = array_map(
            static fn (array $p): array => $p + ['hook' => TeaserSettings::hookName($p['key'])],
            $this->settings->placements()
        );

        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/teasers.html.twig', $this->chrome('quince_blog_teasers') + [
            'placements' => $placements,
        ]);
    }

    public function editAction(string $key = self::NEW): Response
    {
        $isNew = $key === self::NEW;
        $placement = $isNew ? null : $this->settings->get($key);

        if (!$isNew && $placement === null) {
            $this->addFlash('error', $this->trans('That placement does not exist.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_teasers');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $idLang = (int) $this->getContext()->language->id;

        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/teaser-edit.html.twig', $this->chrome('quince_blog_teasers') + [
            'originalKey' => $isNew ? '' : $key,
            'placement' => $placement,
            'languages' => $this->languages(),
            'categories' => Repository::listCategories($idLang, $idShop),
            'hookPrefix' => TeaserSettings::HOOK_PREFIX,
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_teasers_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_teasers');
        }

        $originalKey = (string) $request->request->get('original_key', '');
        $this->settings->upsert($originalKey, $request->request->all());

        $this->addFlash('success', $this->trans('Placement saved.', self::DOMAIN));

        return $this->redirectToRoute('quince_blog_teasers');
    }

    public function deleteAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_teasers_delete', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_teasers');
        }

        $key = (string) $request->request->get('key', '');

        if ($key !== '') {
            $this->settings->delete($key);
            $this->addFlash('success', $this->trans('Placement removed.', self::DOMAIN));
        }

        return $this->redirectToRoute('quince_blog_teasers');
    }
}
