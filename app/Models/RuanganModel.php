<?php

namespace App\Models;

use CodeIgniter\Model;

class RuanganModel extends Model
{
    protected $table          = 'ruangan';
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    
    protected $allowedFields = [
        'kode', 
        'nama',
        'tipe',
        'kapasitas',
        'jurusan_id'
    ];

    protected $validationRules = [
        'id'         => 'permit_empty|is_natural_no_zero',
        'kode'       => 'required|max_length[20]|is_unique[ruangan.kode,id,{id}]',
        'nama'       => 'required|min_length[3]|max_length[100]',
        'tipe'       => 'required|in_list[kelas,lab]',
        'kapasitas'  => 'required|integer|greater_than[0]',
        'jurusan_id' => 'permit_empty|is_natural_no_zero',
    ];
}
