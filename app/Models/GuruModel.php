<?php

namespace App\Models;

use CodeIgniter\Model;

class GuruModel extends Model
{
    protected $table            = 'guru';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = ['user_id'];
    protected $validationRules  = [
        'id'      => 'permit_empty|is_natural_no_zero',
        'user_id' => 'required|integer|greater_than[0]|is_unique[guru.user_id,id,{id}]',
    ];
}
