<?php

namespace App\Models;

use CodeIgniter\Model;

class KelasModel extends Model
{
    protected $table          = 'kelas';
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    
    protected $allowedFields = [
        'nama', 
        'tingkat',
        'jurusan_id',
        'ruangan_id',
        'tahun_ajaran_id'
    ];

    protected $validationRules = [
        'nama'            => 'required|min_length[2]|max_length[50]',
        'tingkat'         => 'required|in_list[X,XI,XII]',
        'jurusan_id'      => 'required|is_natural_no_zero',
        'ruangan_id'      => 'required|is_natural_no_zero',
        'tahun_ajaran_id' => 'required|is_natural_no_zero',
    ];

    // Need custom validation for unique(nama + tahun_ajaran_id) 
    // It's handled in controller before save
}
