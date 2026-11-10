<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Blog;

use ImageManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Obrazy wpisów — zdjęcie wyróżnione i galeria.
 *
 * Katalog `img/blog/` leży poza drzewem modułu z tego samego powodu, co
 * zdjęcia osób w `quince_contacts`: odinstalowanie albo aktualizacja modułu
 * nie ma prawa skasować zdjęć razem z kodem.
 */
final class ImageStore
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    private const MAX_BYTES = 5242880;

    public function dir(): string
    {
        return _PS_IMG_DIR_ . 'blog/';
    }

    public function url(string $filename): string
    {
        return $filename === '' ? '' : _PS_IMG_ . 'blog/' . $filename;
    }

    public function ensureDir(): bool
    {
        $dir = $this->dir();

        return is_dir($dir) || mkdir($dir, 0755, true);
    }

    /**
     * Przyjęcie wgranego pliku. Zwraca nazwę zapisanego pliku i jego wymiary,
     * albo null, gdy nic nie wgrano albo plik nie przeszedł sprawdzenia.
     * Sprawdzamy sam plik, nie jego nazwę ani deklarowany typ — rozszerzenie
     * i nagłówek da się dopisać do czegokolwiek.
     *
     * @return array{filename:string,width:int,height:int}|null
     */
    public function accept(?UploadedFile $file, int $idPost): ?array
    {
        if ($file === null || !$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            return null;
        }

        $extension = self::EXTENSIONS[$file->getMimeType() ?? ''] ?? null;
        if ($extension === null || !ImageManager::isRealImage($file->getPathname())) {
            return null;
        }

        if (!$this->ensureDir()) {
            return null;
        }

        // Nazwa z niepowtarzalnym członem: przeglądarka trzyma poprzednie
        // zdjęcie w pamięci podręcznej i bez zmiany nazwy pokazywałaby je
        // po podmianie.
        $filename = 'p' . $idPost . '-' . uniqid('', false) . '.' . $extension;

        // Plik nie trafia na dysk taki, jaki przyszedł — zostaje przepisany
        // piksel po pikselu. Wszystko, co ktoś doczepił do obrazu poza samym
        // obrazem, ginie po drodze.
        if (!ImageManager::resize($file->getPathname(), $this->dir() . $filename, null, null, $extension)) {
            return null;
        }

        [$width, $height] = @getimagesize($this->dir() . $filename) ?: [null, null];

        return ['filename' => $filename, 'width' => (int) $width, 'height' => (int) $height];
    }

    public function remove(string $filename): void
    {
        if ($filename === '') {
            return;
        }

        $path = $this->dir() . basename($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
