<?php

namespace App\Models;

use CodeIgniter\Model;

class HariModel extends Model
{
    protected $table            = 'hari';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = ['nama', 'kode', 'urutan'];
    protected $validationRules  = [
        'id'     => 'permit_empty|is_natural_no_zero',
        'nama'   => 'required|max_length[10]',
        'kode'   => 'required|max_length[3]|is_unique[hari.kode,id,{id}]',
        'urutan' => 'required|integer|greater_than[0]',
    ];
}
