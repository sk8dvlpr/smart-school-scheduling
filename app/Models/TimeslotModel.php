<?php

namespace App\Models;

use CodeIgniter\Model;

class TimeslotModel extends Model
{
    protected $table            = 'timeslot';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'hari_id',
        'jam_ke',
        'waktu_mulai',
        'waktu_selesai',
        'tipe',
        'keterangan',
    ];
    protected $validationRules  = [
        'hari_id'       => 'required|integer|greater_than[0]',
        'jam_ke'        => 'required|integer',
        'waktu_mulai'   => 'required',
        'waktu_selesai' => 'required',
        'tipe'          => 'required|in_list[jp,istirahat,kegiatan_khusus]',
    ];

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function getGroupedByHari(): array
    {
        $rows = $this->orderBy('waktu_mulai', 'ASC')->findAll();
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['hari_id']][] = $row;
        }

        return $grouped;
    }
}
