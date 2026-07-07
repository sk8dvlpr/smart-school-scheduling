<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleLogModel extends Model
{
    protected $table          = 'schedule_logs';
    protected $primaryKey     = 'id';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'tahun_ajaran_id',
        'status',
        'fitness_score',
        'generations_run',
        'total_conflicts',
        'execution_time',
        'error_message',
        'result_report',
        'generated_by',
        'started_at',
        'completed_at',
        'published_at',
        'published_by',
        'label',
        'unplaced_report',
        'parent_schedule_log_id',
        'generate_mode',
        'repair_report',
    ];
}
