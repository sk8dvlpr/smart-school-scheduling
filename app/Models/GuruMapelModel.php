<?php

namespace App\Models;

use CodeIgniter\Model;

class GuruMapelModel extends Model
{
    protected $table            = 'guru_mapel';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $allowedFields    = ['guru_id', 'mapel_id', 'max_jam_per_minggu'];
    protected $validationRules  = [
        'guru_id'            => 'required|integer|greater_than[0]',
        'mapel_id'           => 'required|integer|greater_than[0]',
        'max_jam_per_minggu' => 'required|integer|greater_than[0]',
    ];
}
