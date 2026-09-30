<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleJobModel extends Model
{
    public const STATUS_QUEUED    = 'queued';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table         = 'schedule_jobs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'tahun_ajaran_id',
        'user_id',
        'status',
        'progress',
        'generation',
        'best_fitness',
        'parent_log_id',
        'generate_mode',
        'schedule_log_id',
        'error_message',
        'cancel_requested',
        'created_at',
        'started_at',
        'finished_at',
    ];
}
