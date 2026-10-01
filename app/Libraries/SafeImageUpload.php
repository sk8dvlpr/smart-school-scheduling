<?php

namespace App\Libraries;

/**
 * Allowlisted image extensions for public web uploads (never trust client filename).
 */
class SafeImageUpload
{
    public const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * Resolve a safe extension from MIME-derived guess (preferred) or fallback.
     * Returns null if not in allowlist.
     */
    public static function resolveExtension(?string $guessed, ?string $fallback = null): ?string
    {
        foreach ([$guessed, $fallback] as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            $ext = strtolower(ltrim((string) $candidate, '.'));
            if (in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                return $ext;
            }
        }

        return null;
    }

    public static function isAllowedExtension(?string $ext): bool
    {
        return self::resolveExtension($ext) !== null;
    }
}
