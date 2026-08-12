<?php

namespace App\Support;

class MoneyInput
{
    /**
     * Parse a user-typed rupiah amount (1.000.000, 1,000,000, or 1000000) into a float.
     */
    public static function parse(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $raw = preg_replace('/[^\d.,]/', '', trim((string) $value)) ?? '';
        if ($raw === '' || $raw === '.' || $raw === ',') {
            return null;
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            $raw = self::normalizeSingleSeparator($raw, ',');
        } elseif ($hasDot) {
            $raw = self::normalizeSingleSeparator($raw, '.');
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    private static function normalizeSingleSeparator(string $raw, string $separator): string
    {
        $count = substr_count($raw, $separator);

        if ($count > 1) {
            return str_replace($separator, '', $raw);
        }

        if (preg_match('/'.preg_quote($separator, '/').'\d{3}$/', $raw)) {
            return str_replace($separator, '', $raw);
        }

        return str_replace($separator, '.', $raw);
    }
}
