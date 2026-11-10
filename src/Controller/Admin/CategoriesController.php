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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Kategorie bloga: lista, edycja, kasowanie. */
class CategoriesController extends BlogBaseController
{
    public function indexAction(): Response
    {
        $idShop = (int) $this->getContext()->shop->id;
        $idLang = (int) $this->getContext()->language->id;

        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/categories.html.twig', $this->chrome('quince_blog_categories') + [
            'rows' => Repository::listCategories($idLang, $idShop),
        ]);
    }

    public function editAction(int $id = 0): Response
    {
        $idShop = (int) $this->getContext()->shop->id;
        $idLang = (int) $this->getContext()->language->id;

        $category = $id > 0 ? Repository::getCategoryById($id, $idShop) : null;
        if ($id > 0 && $category === null) {
            $this->addFlash('error', $this->trans('That category does not exist in this shop.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_categories');
        }

        $all = Repository::listCategories($idLang, $idShop);
        // Kategoria nie może być rodzicem samej siebie — przy edycji odpada
        // z listy wyboru, żeby panel w ogóle nie dał zbudować pętli.
        $parents = array_values(array_filter($all, static fn (array $c): bool => (int) $c['id_category'] !== $id));

        // Wartości pól językowych: pole => [id_lang => tekst]. Składane tutaj, nie
        // w szablonie: Twigowe `merge` przy kluczach liczbowych numeruje tablicę
        // od zera, więc tekst języka 2 trafiał do pola języka 1, a zapis
        // formularza zamieniał języki miejscami.
        $languages = $this->languages();
        $values = [];
        foreach (['name', 'slug', 'description', 'meta_title', 'meta_description'] as $field) {
            foreach ($languages as $language) {
                $values[$field][$language['id_lang']] = (string) ($category['lang'][$language['id_lang']][$field] ?? '');
            }
        }

        return $this->renderPanel('@Modules/quince_blog/views/templates/admin/category-edit.html.twig', $this->chrome('quince_blog_categories') + [
            'category' => $category,
            'parents' => $parents,
            'languages' => $languages,
            'values' => $values,
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_categories_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_categories');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $values = $request->request->all();

        $base = [
            'id_category' => (int) ($values['id_category'] ?? 0) ?: null,
            'id_shop' => $idShop,
            'id_parent' => (int) ($values['id_parent'] ?? 0) ?: null,
            'position' => (int) ($values['position'] ?? 0),
            // `<input type="color">` nie umie być puste — przeglądarka zawsze
            // wysyła jakiś kod (domyślnie #000000), nawet gdy sprzedawca nigdy
            // nie dotknął próbnika. Bez osobnej zgody ("Use this colour") każdy
            // zapis formularza cicho ustawiał czarny kolor wszystkim kategoriom.
            //
            // Kształt `#rrggbb` wymuszony tu, nie tylko w `pSQL()` przy zapisie:
            // ta wartość wraca później wprost w atrybucie `style` plakietki na
            // zajawce, a żadne SQL-owe escapowanie samo w sobie nie gwarantuje,
            // że to, co w nim stoi, da się bezpiecznie wstawić w CSS.
            'color' => (!empty($values['color_enabled']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($values['color'] ?? '')))
                ? (string) $values['color']
                : '',
            'active' => !empty($values['active']),
        ];

        // Pola z makra `fields.multilang()`: `name_1`, `slug_1`, `description_1`…
        // Pola meta muszą tu być: zapis kategorii nadpisuje cały wiersz językowy,
        // więc pole pominięte w formularzu wraca do bazy puste.
        $byLang = [];
        foreach (['name', 'slug', 'description', 'meta_title', 'meta_description'] as $field) {
            foreach ($values as $key => $value) {
                if (preg_match('/^' . $field . '_(\d+)$/', (string) $key, $m)) {
                    $byLang[(int) $m[1]][$field] = (string) $value;
                }
            }
        }

        Repository::saveCategory($base, $byLang);
        $this->addFlash('success', $this->trans('Category saved.', self::DOMAIN));

        return $this->redirectToRoute('quince_blog_categories');
    }

    public function deleteAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_blog_categories_delete', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_blog_categories');
        }

        $idShop = (int) $this->getContext()->shop->id;
        $id = (int) $request->request->get('id', 0);

        if ($id > 0 && Repository::deleteCategory($id, $idShop)) {
            $this->addFlash('success', $this->trans('Category removed.', self::DOMAIN));
        } else {
            $this->addFlash('error', $this->trans('Could not remove that category.', self::DOMAIN));
        }

        return $this->redirectToRoute('quince_blog_categories');
    }
}
