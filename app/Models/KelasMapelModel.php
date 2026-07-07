<?php

namespace App\Models;

use CodeIgniter\Model;

class KelasMapelModel extends Model
{
    protected $table            = 'kelas_mapel';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'kelas_id',
        'mapel_id',
        'tahun_ajaran_id',
        'jam_per_minggu',
        'butuh_lab',
        'lab_id',
    ];
    protected $validationRules  = [
        'kelas_id'        => 'required|integer|greater_than[0]',
        'mapel_id'        => 'required|integer|greater_than[0]',
        'tahun_ajaran_id' => 'required|integer|greater_than[0]',
        'jam_per_minggu'  => 'required|integer|greater_than[0]',
        'butuh_lab'       => 'permit_empty|in_list[0,1]',
        'lab_id'          => 'permit_empty|integer|greater_than[0]',
    ];
}
