<?php

namespace App\Libraries;

/**
 * Generates one-time temporary passwords for provisioning / reset.
 */
class TemporaryPassword
{
    /**
     * Cryptographically strong temporary password (URL-safe, no ambiguous chars).
     */
    public static function generate(int $length = 12): string
    {
        $length = max(10, min(32, $length));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Neutralize spreadsheet formula injection for Excel cells.
     */
    public static function sanitizeSpreadsheetValue(mixed $value): string
    {
        $text = (string) $value;
        if ($text === '') {
            return $text;
        }

        $first = $text[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $text;
        }

        return $text;
    }
}
