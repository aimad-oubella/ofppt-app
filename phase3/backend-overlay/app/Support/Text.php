<?php

namespace App\Support;

use Illuminate\Support\Str;

class Text
{
    /** Convertit une cellule en texte nettoyé (espaces normalisés). Retourne null si vide. */
    public static function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            $value = ($value == floor($value) && abs($value) < 1e15)
                ? (string) (int) $value
                : rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $s = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $s === '' ? null : $s;
    }

    /** Clé de comparaison : minuscules, sans accents ni ponctuation. */
    public static function key(?string $value): string
    {
        $original = trim((string) $value);
        $k = preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($original)));
        $k = trim((string) $k);

        // Texte non latin (arabe...) : on retombe sur le texte en minuscules.
        return $k === '' ? mb_strtolower($original) : $k;
    }
}
