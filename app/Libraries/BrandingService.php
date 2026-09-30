<?php

namespace App\Libraries;

use App\Models\AppSettingModel;

class BrandingService
{
    private static ?array $cache = null;

    /**
     * @return array{nama_sekolah: string, logo_path: ?string, logo_url: ?string, logo_fs_path: ?string}
     */
    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $nama     = null;
        $logoPath = null;

        try {
            $fromSettings = trim((string) (SettingsService::get('school.name', '') ?? ''));
            if ($fromSettings !== '') {
                $nama = $fromSettings;
            }
            $logoFromSettings = SettingsService::get('school.logo_path');
            if (is_string($logoFromSettings) && $logoFromSettings !== '') {
                $logoPath = $logoFromSettings;
            }
        } catch (\Throwable $e) {
            // settings table may not exist yet
        }

        if ($nama === null || $logoPath === null) {
            try {
                $row = (new AppSettingModel())->orderBy('id', 'ASC')->first();
                if ($nama === null) {
                    $nama = trim((string) ($row['nama_sekolah'] ?? '')) ?: null;
                }
                if ($logoPath === null) {
                    $lp = $row['logo_path'] ?? null;
                    $logoPath = is_string($lp) && $lp !== '' ? $lp : null;
                }
            } catch (\Throwable $e) {
                // Table may not exist yet before migrate
            }
        }

        $nama = $nama ?: 'Smart School Scheduling';

        $fsPath  = null;
        $logoUrl = null;
        if ($logoPath !== null) {
            $candidate = FCPATH . $logoPath;
            if (is_file($candidate)) {
                $fsPath  = $candidate;
                $logoUrl = base_url($logoPath);
            }
        }

        self::$cache = [
            'nama_sekolah' => $nama,
            'logo_path'    => $logoPath,
            'logo_url'     => $logoUrl,
            'logo_fs_path' => $fsPath,
        ];

        return self::$cache;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
        SettingsService::clearCache();
    }

    /**
     * Data URI for DomPDF embedding, or null if no logo.
     */
    public static function logoDataUri(): ?string
    {
        $branding = self::get();
        $fs       = $branding['logo_fs_path'] ?? null;
        if ($fs === null || ! is_file($fs)) {
            return null;
        }

        $mime = mime_content_type($fs) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($fs));

        return 'data:' . $mime . ';base64,' . $data;
    }
}
