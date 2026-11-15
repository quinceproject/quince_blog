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

use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Quince\Blog\Repository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Tagi bloga: płaska lista, bez hierarchii. Dodawanie i edycja nazw na tym
 * samym ekranie, bo tag to tylko nazwa per język.
 */
class TagsController extends BlogBaseController
{
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(): Response
    {
        $idShop = (int) $this->getContext()->shop->id;
        $idLang = (int) $this->getContext()->language->id;

        $rows = [];
        foreach (Repository::listTags($idLang, $idShop) as $row) {
            $full = Repository::getTagById((int) $row['id_tag'], $idShop);
            $rows[] = ['id_tag' => $row['id_tag'], 'post_count' => $row['post_count'], 'lang' => $full['lang'] ?? []];
        }

        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/tags.html.twig', $this->chrome('quince_blog_tags') + [
            'rows' => $rows,
            'languages' => $this->languages(),
        ]);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to change this.')]
    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_tags_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_tags');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $values = $request->request->all();

        // Tag innego sklepu: bez tej kontroli zapis dopisałby mu wiersze
        // językowe z id_shop bieżącego sklepu.
        $idTag = (int) ($values['id_tag'] ?? 0);
        if ($idTag > 0 && Repository::getTagById($idTag, $idShop) === null) {
            $this->addFlash('error', $this->trans('That tag does not exist in this shop.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_tags');
        }

        $base = ['id_tag' => (int) ($values['id_tag'] ?? 0) ?: null, 'id_shop' => $idShop];

        $byLang = [];
        foreach ($this->languages() as $lang) {
            $idLang = (int) $lang['id_lang'];
            $byLang[$idLang] = (array) ($values['lang'][$idLang] ?? []);
        }

        Repository::saveTag($base, $byLang);
        $this->addFlash('success', $this->trans('Tag saved.', self::DOMAIN));

        return $this->redirectToRoute('quince_blog_tags');
    }

    #[AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message: 'You do not have permission to delete this.')]
    public function deleteAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_tags_delete', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_tags');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $id = (int) $request->request->get('id', 0);

        if ($id > 0 && Repository::deleteTag($id, $idShop)) {
            $this->addFlash('success', $this->trans('Tag removed.', self::DOMAIN));
        } else {
            $this->addFlash('error', $this->trans('Could not remove that tag.', self::DOMAIN));
        }

        return $this->redirectToRoute('quince_blog_tags');
    }
}
