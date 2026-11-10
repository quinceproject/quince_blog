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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Szerokość strony pojedynczego wpisu, w kolumnach silnika (ta sama gałka
 * co w panelu Wygląd — Configuration::get('QUINCE_GRID_COLUMNS')). Treść i
 * zdjęcie dostają osobną liczbę kolumn: treść węziej, czytelniej; zdjęcie
 * może zająć tyle, ile silnik ma w ogóle kolumn (czyli pełny kontener).
 */
final class BlogLayoutSettings
{
    private const CONTENT_COLS = 'QUINCE_BLOG_CONTENT_COLS';
    private const IMAGE_COLS = 'QUINCE_BLOG_IMAGE_COLS';
    private const RELATED_COUNT = 'QUINCE_BLOG_RELATED_COUNT';

    /** Odstęp siatki silnika — stała w `MetricsGenerator::SPACE_UNIT`, nie ustawienie panelu. */
    private const SPACE_UNIT_PX = 20;

    /** Liczba kolumn ustawiona w silniku — górny limit obu poniższych. */
    public function engineColumns(): int
    {
        $n = (int) Configuration::get('QUINCE_GRID_COLUMNS');

        return in_array($n, [3, 4, 5], true) ? $n : 4;
    }

    public function contentColumns(): int
    {
        return $this->bounded(self::CONTENT_COLS, 3);
    }

    public function imageColumns(): int
    {
        return $this->bounded(self::IMAGE_COLS, $this->engineColumns());
    }

    /**
     * Ile powiązanych wpisów pokazać pod artykułem. Karty zawsze rozciągają
     * się na 100% szerokości kolumny treści — ta liczba jest jednocześnie
     * liczbą kolumn siatki zajawek (`--q-posts-cols`), więc rząd jest zawsze
     * pełny, niezależnie od wybranej wartości.
     */
    public function relatedCount(): int
    {
        $saved = Configuration::get(self::RELATED_COUNT);
        $n = $saved !== false && $saved !== '' ? (int) $saved : 2;

        return max(1, min(6, $n));
    }

    /**
     * Szerokość treści w pikselach, policzona z realnych ustawień silnika
     * — nie z CSS `calc()`. Dowiedzione w praktyce: zagnieżdżone `var()`
     * w `calc()` nie dawały w przeglądarce oczekiwanego wyniku, a liczba
     * w pikselach, wypisana wprost z PHP, nie zostawia tego do zgadywania.
     */
    public function contentWidthPx(): int
    {
        return $this->spanWidthPx($this->contentColumns());
    }

    public function coverWidthPx(): int
    {
        return $this->spanWidthPx($this->imageColumns());
    }

    private function spanWidthPx(int $cols): int
    {
        $container = (int) Configuration::get('QUINCE_CONTAINER_WIDTH');
        if ($container < 960 || $container > 1920) {
            $container = 1200;
        }
        $engineCols = $this->engineColumns();
        $gap = self::SPACE_UNIT_PX;

        $cardWidth = ($container - ($engineCols - 1) * $gap) / $engineCols;

        return (int) round($cols * $cardWidth + ($cols - 1) * $gap);
    }

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        $max = $this->engineColumns();
        $content = max(1, min($max, (int) ($values['content_cols'] ?? 3)));
        $image = max(1, min($max, (int) ($values['image_cols'] ?? $max)));
        $related = max(1, min(6, (int) ($values['related_count'] ?? 2)));

        \Quince\Engine\Settings\ConfigStore::set(self::CONTENT_COLS, (string) $content);
        \Quince\Engine\Settings\ConfigStore::set(self::IMAGE_COLS, (string) $image);
        \Quince\Engine\Settings\ConfigStore::set(self::RELATED_COUNT, (string) $related);
    }

    private function bounded(string $key, int $fallback): int
    {
        $max = $this->engineColumns();
        $saved = Configuration::get($key);
        $n = $saved !== false && $saved !== '' ? (int) $saved : $fallback;

        return max(1, min($max, $n));
    }
}
