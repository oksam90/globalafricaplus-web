<?php

namespace App\Support;

/**
 * Conversion d'un nombre entier en toutes lettres (français), pour le champ
 * « [MONTANT EN CHIFFRES ET LETTRES] » des conventions.
 *
 * Couvre 0 → 999 999 999 999. Règles d'accord usuelles (quatre-vingts, cent,
 * « et un »). Le centime n'est pas requis sur ces contrats (montants entiers).
 */
class MoneyToWords
{
    private const UNITS = [
        0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre',
        5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf',
        10 => 'dix', 11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze',
        15 => 'quinze', 16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf',
    ];

    private const TENS = [
        2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante',
        6 => 'soixante', 7 => 'soixante', 8 => 'quatre-vingt', 9 => 'quatre-vingt',
    ];

    /** "2 000 000 (deux millions)" — chiffres groupés + lettres entre parenthèses. */
    public static function figuresAndWords(float|int $amount, ?string $currencyWord = null): string
    {
        $int = (int) round($amount);
        $figures = self::groupThousands($int);
        $words = self::words($int);
        if ($currencyWord) {
            $words .= ' ' . $currencyWord;
        }
        return "{$figures} ({$words})";
    }

    /** "2 000 000" — séparateur de milliers en espace insécable fine. */
    public static function groupThousands(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }

    /** Le nombre en toutes lettres, ex. 2000000 → "deux millions". */
    public static function words(int $n): string
    {
        if ($n === 0) return self::UNITS[0];
        if ($n < 0) return 'moins ' . self::words(-$n);

        $parts = [];
        $scales = [
            1_000_000_000 => ['milliard', 'milliards'],
            1_000_000     => ['million', 'millions'],
            1_000         => ['mille', 'mille'], // « mille » invariable
            1             => ['', ''],
        ];

        $remaining = $n;
        foreach ($scales as $value => [$sing, $plur]) {
            if ($remaining < $value) continue;
            $count = intdiv($remaining, $value);
            $remaining %= $value;

            if ($value === 1) {
                $parts[] = self::chunk($count);
            } elseif ($value === 1_000) {
                // « mille » invariable ; on n'écrit pas « un mille ».
                $prefix = ($count === 1) ? '' : self::chunk($count) . ' ';
                $parts[] = trim($prefix . 'mille');
            } else {
                $label = ($count > 1) ? $plur : $sing;
                $parts[] = self::chunk($count) . ' ' . $label;
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /** 0..999 en lettres. */
    private static function chunk(int $n): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }

        if ($n < 100) {
            $tens = intdiv($n, 10);
            $unit = $n % 10;

            // 70-79 et 90-99 : base 60/80 + (10..19)
            if ($tens === 7 || $tens === 9) {
                $base = self::TENS[$tens]; // soixante / quatre-vingt
                $rest = self::UNITS[10 + $unit];
                // soixante et onze (71) — « et » uniquement pour 71
                if ($tens === 7 && $unit === 1) {
                    return $base . ' et ' . $rest;
                }
                return $base . '-' . $rest;
            }

            $word = self::TENS[$tens];
            if ($unit === 0) {
                // quatre-vingts prend un « s » quand il termine (80 pile)
                return ($tens === 8) ? $word . 's' : $word;
            }
            // 21,31,41,51,61 : « et un »
            if ($unit === 1 && in_array($tens, [2, 3, 4, 5, 6], true)) {
                return $word . ' et un';
            }
            return $word . '-' . self::UNITS[$unit];
        }

        // 100..999
        $hundreds = intdiv($n, 100);
        $rest = $n % 100;
        $h = ($hundreds === 1) ? 'cent' : self::UNITS[$hundreds] . ' cent';
        if ($rest === 0) {
            // « cents » pluriel quand multiplié et terminal (deux cents)
            return ($hundreds > 1) ? $h . 's' : $h;
        }
        return $h . ' ' . self::chunk($rest);
    }
}
