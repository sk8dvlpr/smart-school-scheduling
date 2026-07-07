<?php

namespace App\Models;

use CodeIgniter\Model;

class TahunAjaranModel extends Model
{
    protected $table          = 'tahun_ajaran';
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    
    protected $allowedFields = [
        'nama',
        'semester',
        'is_active',
        'tanggal_mulai',
        'tanggal_selesai',
        'published_schedule_log_id',
    ];

    protected $validationRules = [
        'nama'            => 'required|min_length[3]|max_length[50]',
        'semester'        => 'required|in_list[ganjil,genap]',
        'tanggal_mulai'   => 'required|valid_date',
        'tanggal_selesai' => 'required|valid_date',
    ];
}
