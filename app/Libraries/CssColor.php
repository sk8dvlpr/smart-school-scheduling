<?php

namespace App\Libraries;

/**
 * Sanitize mapel warna for safe use in CSS background-color.
 */
class CssColor
{
    /**
     * Allow only #RGB or #RRGGBB (case-insensitive). Returns empty string if invalid.
     */
    public static function hexOrEmpty(?string $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1) {
            return $value;
        }

        return '';
    }
}
