<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleConfigModel extends Model
{
    protected $table          = 'schedule_config';
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false; // No timestamps based on migration
    
    protected $allowedFields = [
        'tahun_ajaran_id', 
        'param_key',
        'param_value'
    ];
}
