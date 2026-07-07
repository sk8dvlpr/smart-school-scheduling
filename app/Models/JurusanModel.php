<?php

namespace App\Models;

use CodeIgniter\Model;

class JurusanModel extends Model
{
    protected $table          = 'jurusan';
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    
    protected $allowedFields = [
        'kode', 
        'nama'
    ];

    protected $validationRules = [
        'id'   => 'permit_empty|is_natural_no_zero',
        'kode' => 'required|min_length[2]|max_length[10]|is_unique[jurusan.kode,id,{id}]',
        'nama' => 'required|min_length[3]|max_length[100]',
    ];
}
