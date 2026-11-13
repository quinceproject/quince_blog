<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Blog\Settings;

use Configuration;
use Context;
use Module;
use Quince\Blog\Repository;
use Quince\Blog\Seo;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Umieszczenia zajawek bloga — lista dowolnej długości, nie trzy sztywne
 * miejsca. Każde umieszczenie ma WŁASNY hook, bez żadnego parametru —
 * silnik (strona główna, listing kategorii) umie wstawić dowolny hook po
 * samej nazwie, więc zajawki muszą dać się wpiąć tą samą drogą:
 *
 *   {hook h='displayQuinceBlogTeasersKLUCZ'}
 *
 * Klucz NIE nazywa strony — nazywa sposób prezentacji (sklepikarz może
 * wstawić ten sam hook w dowolnym miejscu, nie tylko tam, gdzie go pierwszy
 * raz użył). Hook dla danego klucza rejestruje się dynamicznie przy zapisie
 * (patrz `save()` i `Quince_Blog::__call()` w głównym pliku modułu).
 *
 * Trzy układy z makiety (`makiety/zajawki-blogowe.html`):
 *   `hero`  — Układ 1, wyróżniony wpis + lista obok,
 *   `row`   — Układ 2, rząd równych kafli, liczba kolumn jak zajawki produktów,
 *   `strip` — Układ 3, taśma zwarta bez zajawki tekstowej, do wąskiej kolumny.
 */
final class TeaserSettings
{
    private const CONFIG_KEY = 'QUINCE_BLOG_TEASERS';
    private const LAYOUTS = ['hero', 'row', 'strip'];

    /** Przedrostek wspólny dla wszystkich hooków zajawek — reszta nazwy to klucz z `ucfirst()`. */
    public const HOOK_PREFIX = 'displayQuinceBlogTeasers';

    /** Pełna nazwa hooka dla danego klucza, do wstawienia w szablonie. */
    public static function hookName(string $key): string
    {
        return self::HOOK_PREFIX . ucfirst($key);
    }

    /**
     * Wszystkie umieszczenia, w kolejności zapisanej przez sklepikarza.
     *
     * @return array<int,array{key:string,enabled:bool,layout:string,count:int,id_category:int,title:array<int,string>}>
     */
    public function placements(): array
    {
        $decoded = json_decode((string) Configuration::get(self::CONFIG_KEY), true);
        $out = [];

        foreach (is_array($decoded) ? $decoded : [] as $row) {
            if (!is_array($row) || !isset($row['key']) || (string) $row['key'] === '') {
                continue;
            }

            $title = [];
            foreach (is_array($row['title'] ?? null) ? $row['title'] : [] as $idLang => $text) {
                $title[(int) $idLang] = (string) $text;
            }

            $out[] = [
                'key' => (string) $row['key'],
                'enabled' => !empty($row['enabled']),
                'layout' => in_array($row['layout'] ?? '', self::LAYOUTS, true) ? (string) $row['layout'] : 'row',
                'count' => max(1, min(12, (int) ($row['count'] ?? 4))),
                'id_category' => max(0, (int) ($row['id_category'] ?? 0)),
                'title' => $title,
            ];
        }

        return $out;
    }

    /**
     * Jedno umieszczenie po kluczu, albo `null`, gdy go nie ma.
     *
     * @return array{key:string,enabled:bool,layout:string,count:int,id_category:int,title:array<int,string>}|null
     */
    public function get(string $key): ?array
    {
        foreach ($this->placements() as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Zapisuje jedno umieszczenie — nowe (gdy `$originalKey` jest puste) albo
     * istniejące pod `$originalKey` (klucz dało się po drodze zmienić).
     * Zwraca ostateczny klucz, pod jakim trafiło do zapisu.
     *
     * @param array<string,mixed> $values dane z formularza ($request->request->all())
     */
    public function upsert(string $originalKey, array $values): string
    {
        $all = $this->placements();
        $used = array_values(array_filter(
            array_column($all, 'key'),
            static fn (string $k): bool => $k !== $originalKey
        ));

        $key = $this->sanitizeKey((string) ($values['key'] ?? ''), $used);
        if ($key === '') {
            $key = $originalKey !== '' ? $originalKey : $this->sanitizeKey('placement', $used);
        }

        $layout = (string) ($values['layout'] ?? 'row');

        $title = [];
        foreach ((array) ($values['title'] ?? []) as $idLang => $text) {
            $title[(int) $idLang] = trim((string) $text);
        }

        $row = [
            'key' => $key,
            'enabled' => !empty($values['enabled']),
            'layout' => in_array($layout, self::LAYOUTS, true) ? $layout : 'row',
            'count' => max(1, min(12, (int) ($values['count'] ?? 4))),
            'id_category' => max(0, (int) ($values['id_category'] ?? 0)),
            'title' => $title,
        ];

        $out = array_values(array_filter($all, static fn (array $p): bool => $p['key'] !== $originalKey));
        $out[] = $row;

        \Quince\Engine\Settings\ConfigStore::set(self::CONFIG_KEY, (string) json_encode($out), true);

        $this->ensureHooksRegistered([$key]);

        return $key;
    }

    public function delete(string $key): void
    {
        $out = array_values(array_filter(
            $this->placements(),
            static fn (array $p): bool => $p['key'] !== $key
        ));

        \Quince\Engine\Settings\ConfigStore::set(self::CONFIG_KEY, (string) json_encode($out), true);
    }

    /**
     * Rejestruje hooki dla wszystkich już zapisanych umieszczeń — wywoływane
     * przy install()/upgrade(), na wypadek ponownej instalacji modułu, gdy
     * `QUINCE_BLOG_TEASERS` w konfiguracji już coś zawiera.
     */
    public function registerAllHooks(): void
    {
        $this->ensureHooksRegistered(array_column($this->placements(), 'key'));
    }

    /**
     * Dane gotowe do przypisania do Smarty dla danego umieszczenia i języka —
     * `null`, gdy umieszczenie nie istnieje albo jest wyłączone (hook ma wtedy
     * nic nie wypisać).
     *
     * @return array<string,mixed>|null
     */
    public function forTemplate(string $placementKey, int $idLang): ?array
    {
        $placement = null;
        foreach ($this->placements() as $row) {
            if ($row['key'] === $placementKey) {
                $placement = $row;
                break;
            }
        }

        if ($placement === null || !$placement['enabled']) {
            return null;
        }

        $idShop = (int) Context::getContext()->shop->id;
        $layout = $placement['layout'];

        // Układ „hero" pokazuje wyróżniony wpis + trzy obok — cztery razem,
        // niezależnie od ustawionej liczby (tak jak w makiecie).
        $fetchCount = $layout === 'hero' ? 4 : $placement['count'];

        $posts = Repository::getRecentPosts($fetchCount, $idLang, $idShop, 0, $placement['id_category'] ?: null);
        if ($posts === []) {
            return null;
        }

        $context = Context::getContext();

        $title = $placement['title'][$idLang] ?? reset($placement['title']) ?: '';

        $posts = array_map(function (array $p) use ($idLang, $context): array {
            $categories = Repository::getPostCategories((int) $p['id_post'], $idLang);

            return [
                'title' => (string) $p['title'],
                'excerpt' => (string) ($p['excerpt'] ?? ''),
                'url' => Seo::postUrl((string) $p['slug'], $context, $idLang),
                'cover_url' => $this->coverUrl((int) ($p['featured_id_image'] ?? 0)),
                'date_pub' => $p['date_pub'] ?? null,
                'categories' => array_map(static fn (array $c): array => ['name' => (string) $c['name'], 'color' => (string) ($c['color'] ?? '')], $categories),
            ];
        }, $posts);

        return [
            'qbl_teasers_layout' => $layout,
            'qbl_teasers_title' => $title,
            'qbl_teasers_posts' => $posts,
            'qbl_teasers_columns' => $placement['count'],
            'qbl_teasers_all_url' => Seo::listUrl($context, $idLang),
        ];
    }

    private function coverUrl(int $idImage): string
    {
        if ($idImage <= 0) {
            return '';
        }
        $img = Repository::getImageById($idImage);
        if (!$img) {
            return '';
        }

        return rtrim(Context::getContext()->shop->getBaseURL(true), '/') . '/img/blog/' . $img['filename'];
    }

    /**
     * Klucz umieszczenia trafia dosłownie do nazwy hooka, więc musi być
     * poprawnym fragmentem identyfikatora PHP: litera na początku, dalej
     * tylko litery i cyfry (ten sam wzorzec, którego silnik wymaga od
     * własnych hooków wpisywanych ręcznie w panelu listingu — `hook:nazwa`).
     * Duplikat dostaje przyrostek liczbowy, bo dwa umieszczenia pod tym
     * samym kluczem dzieliłyby jeden hook i nadpisywały się nawzajem.
     *
     * @param string[] $used
     */
    private function sanitizeKey(string $raw, array $used): string
    {
        $key = strtolower(trim($raw));
        $key = (string) preg_replace('/[^a-z0-9]+/', '', $key);
        $key = ltrim($key, '0123456789');

        if ($key === '') {
            return '';
        }

        $base = $key;
        $i = 2;
        while (in_array($key, $used, true)) {
            $key = $base . $i;
            ++$i;
        }

        return $key;
    }

    /**
     * Rejestruje hook dla każdego klucza — nazwa jest dynamiczna (`HOOK_PREFIX`
     * + klucz), więc jej nie ma w kodzie z góry. `registerHook()` sam tworzy
     * brakujący hook i sam pomija to, co już jest zarejestrowane — bezpieczne
     * wywołanie przy każdym zapisie, nie tylko przy nowym kluczu.
     *
     * @param string[] $keys
     */
    private function ensureHooksRegistered(array $keys): void
    {
        $module = Module::getInstanceByName('quince_blog');
        if (!$module) {
            return;
        }

        foreach ($keys as $key) {
            $module->registerHook(self::hookName($key));
        }
    }
}
