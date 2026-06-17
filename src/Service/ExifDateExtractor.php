<?php

namespace App\Service;

/**
 * Lit la date de prise de vue (DateTimeOriginal) d'une image via EXIF.
 * Tolérant : retourne null si l'extension exif est absente ou la donnée illisible.
 */
final class ExifDateExtractor
{
    public function extractDate(string $path): ?\DateTimeImmutable
    {
        if (!\function_exists('exif_read_data') || !is_file($path)) {
            return null;
        }

        $exif = @exif_read_data($path);
        if (false === $exif) {
            return null;
        }

        $raw = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $raw);

        return $date ?: null;
    }
}
