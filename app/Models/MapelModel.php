<?php

namespace App\Models;

use CodeIgniter\Model;

class MapelModel extends Model
{
    protected $table          = 'mapel';
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    
    protected $allowedFields = [
        'kode',
        'nama',
        'tipe',
        'jurusan_id',
        'warna',
        'bobot_kognitif',
        'jam_per_minggu',
    ];

    protected $validationRules = [
        'id'             => 'permit_empty|is_natural_no_zero',
        'kode'           => 'required|max_length[20]|is_unique[mapel.kode,id,{id}]',
        'nama'           => 'required|min_length[3]|max_length[100]',
        'tipe'           => 'required|in_list[umum,kejuruan]',
        'jurusan_id'     => 'permit_empty|is_natural_no_zero',
        'warna'          => 'required|max_length[7]',
        'bobot_kognitif' => 'permit_empty|integer|greater_than[0]|less_than_equal_to[10]',
        'jam_per_minggu' => 'required|integer|greater_than[0]',
    ];
}
