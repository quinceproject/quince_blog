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
use Quince\Engine\Settings\ConfigStore;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Tytuł i opis listy wpisów oraz tytuł strony tagu, osobno dla każdego języka.
 *
 * Wpis i kategoria mają własne pola meta w swojej edycji. Lista wpisów i tagi
 * nie mają rekordu, przy którym dałoby się je trzymać, więc leżą tutaj, w jednym
 * kluczu JSON: `{"list_title": {"1": "..."}, "list_description": {...}, "tag_title": {...}}`.
 */
final class SeoSettings
{
    public const KEY = 'QUINCE_BLOG_SEO';

    /** Pola formularza, w kolejności na ekranie. */
    public const FIELDS = ['list_title', 'list_description', 'tag_title'];

    /** W tytule strony tagu ten znacznik zamienia się na nazwę tagu. */
    public const TAG_PLACEHOLDER = '%tag%';

    /** @return array<string,array<int,string>> pole => [id_lang => tekst] */
    public function all(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::KEY), true);
        $out = [];

        foreach (self::FIELDS as $pole) {
            $out[$pole] = [];
            foreach ((array) ($zapisane[$pole] ?? []) as $idLang => $tekst) {
                $out[$pole][(int) $idLang] = (string) $tekst;
            }
        }

        return $out;
    }

    /** Pusty tekst znaczy: sklep pokazuje swój tekst domyślny. */
    public function get(string $pole, int $idLang): string
    {
        return trim($this->all()[$pole][$idLang] ?? '');
    }

    /**
     * Tytuł strony tagu z podstawioną nazwą tagu.
     *
     * Bez ustawienia: „#nazwa”, tak jak tag wygląda na wpisie.
     */
    public function tagTitle(int $idLang, string $tag): string
    {
        $wzor = $this->get('tag_title', $idLang);

        if ($wzor === '') {
            return '#' . $tag;
        }

        return str_contains($wzor, self::TAG_PLACEHOLDER)
            ? str_replace(self::TAG_PLACEHOLDER, $tag, $wzor)
            : $wzor . ' ' . $tag;
    }

    /**
     * @param array<string,mixed> $values dane formularza, pola `<pole>_<id_lang>`
     * @param int[] $idLangs
     */
    public function save(array $values, array $idLangs): void
    {
        $out = [];

        foreach (self::FIELDS as $pole) {
            foreach ($idLangs as $idLang) {
                $tekst = trim(strip_tags((string) ($values[$pole . '_' . $idLang] ?? '')));
                if ($tekst !== '') {
                    $out[$pole][(string) $idLang] = $tekst;
                }
            }
        }

        ConfigStore::set(self::KEY, (string) json_encode($out, JSON_UNESCAPED_UNICODE), true);
    }
}
