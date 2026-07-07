<?php

namespace App\Models;

use CodeIgniter\Model;

class GuruPreferensiModel extends Model
{
    protected $table            = 'guru_preferensi';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'guru_id', 'hari_id', 'timeslot_id', 'tipe', 'bobot',
    ];
    protected $useTimestamps = true;

    public function getByGuru(int $guruId): array
    {
        return $this->where('guru_id', $guruId)
            ->orderBy('hari_id', 'ASC')
            ->orderBy('timeslot_id', 'ASC')
            ->findAll();
    }

    public function replaceForGuru(int $guruId, array $rows): void
    {
        $this->where('guru_id', $guruId)->delete();
        foreach ($rows as $row) {
            if (empty($row['hari_id']) && empty($row['timeslot_id'])) {
                continue;
            }
            $this->insert([
                'guru_id'     => $guruId,
                'hari_id'     => $row['hari_id'] ?: null,
                'timeslot_id' => $row['timeslot_id'] ?: null,
                'tipe'        => ($row['tipe'] ?? 'prefer') === 'avoid' ? 'avoid' : 'prefer',
                'bobot'       => max(1, min(10, (int) ($row['bobot'] ?? 5))),
            ]);
        }
    }
}
