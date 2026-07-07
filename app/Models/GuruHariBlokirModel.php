<?php

namespace App\Models;

use CodeIgniter\Model;

class GuruHariBlokirModel extends Model
{
    protected $table            = 'guru_hari_blokir';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $allowedFields    = ['guru_id', 'hari_id'];
    protected $validationRules  = [
        'guru_id' => 'required|integer|greater_than[0]',
        'hari_id' => 'required|integer|greater_than[0]',
    ];
}
