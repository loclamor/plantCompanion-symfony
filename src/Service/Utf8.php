<?php

namespace App\Service;

/**
 * Nettoyage UTF-8 des données issues de la base legacy partagée : certaines
 * chaînes y sont encodées en latin1/Windows-1252 (octets invalides en UTF-8),
 * ce qui fait échouer json_encode. On récupère ces chaînes (conversion depuis
 * Windows-1252) tout en laissant l'UTF-8 déjà valide intact.
 *
 * Mesure de résilience en attendant la migration UTF-8 de la base.
 */
final class Utf8
{
    /**
     * Nettoie récursivement une valeur (chaîne, tableau imbriqué).
     */
    public static function clean(mixed $value): mixed
    {
        if (\is_string($value)) {
            return mb_check_encoding($value, 'UTF-8')
                ? $value
                : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        if (\is_array($value)) {
            return array_map([self::class, 'clean'], $value);
        }

        return $value;
    }
}
