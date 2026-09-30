<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'group',
        'key',
        'value',
        'type',
        'is_secret',
        'updated_by',
        'updated_at',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findByCompositeKey(string $group, string $key): ?array
    {
        return $this->where('group', $group)->where('key', $key)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByGroup(string $group): array
    {
        return $this->where('group', $group)->findAll();
    }
}
