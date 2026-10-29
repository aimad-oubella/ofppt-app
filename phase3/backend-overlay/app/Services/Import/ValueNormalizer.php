<?php

namespace App\Services\Import;

use App\Support\Text;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/** Conversions de valeurs de cellules. Lève InvalidArgumentException avec un message en français. */
class ValueNormalizer
{
    private const DATE_FORMATS = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'd/m/y', 'd-m-y', 'Y/m/d', 'Y-m-d H:i:s'];

    public static function date(string $value): string
    {
        $v = trim($value);

        // Numéro de série Excel (cellule au format date)
        if (preg_match('/^\d+(\.\d+)?$/', $v)) {
            $n = (float) $v;
            if ($n >= 1 && $n < 100000) {
                $d = ExcelDate::excelToDateTimeObject($n);
                if (self::plausibleYear((int) $d->format('Y'))) {
                    return $d->format('Y-m-d');
                }
            }
            throw new InvalidArgumentException("Date de naissance illisible : « $value ».");
        }

        foreach (self::DATE_FORMATS as $format) {
            $d = \DateTimeImmutable::createFromFormat('!'.$format, $v);
            $err = \DateTimeImmutable::getLastErrors();
            $clean = ! $err || ($err['warning_count'] === 0 && $err['error_count'] === 0);
            if ($d !== false && $clean && self::plausibleYear((int) $d->format('Y'))) {
                return $d->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException("Date de naissance illisible ou invalide : « $value ».");
    }

    private static function plausibleYear(int $year): bool
    {
        return $year >= 1900 && $year <= (int) date('Y');
    }

    public static function gender(string $value): string
    {
        $k = Text::key($value);
        if (in_array($k, ['m', 'h', 'masculin', 'homme', 'garcon', 'male'], true)) {
            return 'M';
        }
        if (in_array($k, ['f', 'feminin', 'femme', 'fille', 'female'], true)) {
            return 'F';
        }
        throw new InvalidArgumentException("Sexe non reconnu : « $value ».");
    }

    /** Extrait le premier entier d'un texte (« 2ème année » donne 2) et vérifie l'intervalle. */
    public static function integerInRange(string $value, int $min, int $max, string $label): int
    {
        if (! preg_match('/\d+/', $value, $m)) {
            throw new InvalidArgumentException("$label illisible : « $value ».");
        }
        $n = (int) $m[0];
        if ($n < $min || $n > $max) {
            throw new InvalidArgumentException("$label hors limites ($min à $max) : « $value ».");
        }

        return $n;
    }
}
