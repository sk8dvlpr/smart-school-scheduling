<?php

namespace App\Libraries;

use App\Models\SettingModel;

class SettingsService
{
    private static ?array $cache = null;

    /**
     * Parse dotted key "group.subkey" into [group, key].
     *
     * @return array{0: string, 1: string}
     */
    public static function parseKey(string $dottedKey): array
    {
        $pos = strpos($dottedKey, '.');
        if ($pos === false) {
            return ['app', $dottedKey];
        }

        return [substr($dottedKey, 0, $pos), substr($dottedKey, $pos + 1)];
    }

    public static function get(string $dottedKey, mixed $default = null): mixed
    {
        self::ensureLoaded();

        if (array_key_exists($dottedKey, self::$cache ?? [])) {
            return self::$cache[$dottedKey];
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getGroup(string $group): array
    {
        self::ensureLoaded();
        $out = [];
        $prefix = $group . '.';
        foreach (self::$cache ?? [] as $full => $value) {
            if (str_starts_with($full, $prefix)) {
                $out[substr($full, strlen($prefix))] = $value;
            }
        }

        return $out;
    }

    public static function set(string $dottedKey, mixed $value, string $type = 'string', ?int $updatedBy = null): void
    {
        [$group, $key] = self::parseKey($dottedKey);
        $stored = self::serializeValue($value, $type);
        $now    = date('Y-m-d H:i:s');

        $model = new SettingModel();
        $row   = $model->findByCompositeKey($group, $key);
        $payload = [
            'group'      => $group,
            'key'        => $key,
            'value'      => $stored,
            'type'       => $type,
            'is_secret'  => 0,
            'updated_by' => $updatedBy,
            'updated_at' => $now,
        ];

        if ($row) {
            $model->update($row['id'], $payload);
        } else {
            $model->insert($payload);
        }

        self::$cache[$dottedKey] = self::castValue($stored, $type);
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    private static function ensureLoaded(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = [];
        try {
            $rows = (new SettingModel())->findAll();
            foreach ($rows as $row) {
                $full = $row['group'] . '.' . $row['key'];
                if ((int) ($row['is_secret'] ?? 0) === 1) {
                    continue;
                }
                self::$cache[$full] = self::castValue($row['value'] ?? null, (string) ($row['type'] ?? 'string'));
            }
        } catch (\Throwable $e) {
            // Table may not exist during install
        }
    }

    private static function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool', 'boolean' => $value ? '1' : '0',
            'int', 'integer'  => (string) (int) $value,
            'json'            => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '{}',
            default           => (string) $value,
        };
    }

    private static function castValue(?string $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'bool', 'boolean' => $value === '1' || strtolower($value) === 'true',
            'int', 'integer'  => (int) $value,
            'json'            => json_decode($value, true),
            default           => $value,
        };
    }
}
