<?php

namespace App\Commands;

use App\Libraries\ScheduleJobService;
use App\Models\ScheduleJobModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ScheduleWorker extends BaseCommand
{
    protected $group       = 'schedule';
    protected $name        = 'schedule:worker';
    protected $description = 'Poll schedule_jobs queue and run ScheduleGenerator for each job.';
    protected $usage       = 'schedule:worker [--once] [--sleep=2]';
    protected $options     = [
        '--once'  => 'Process at most one job then exit.',
        '--sleep' => 'Seconds to wait when queue is empty (default 2).',
    ];

    public function run(array $params)
    {
        $once      = CLI::getOption('once') !== null;
        $sleep     = max(1, (int) (CLI::getOption('sleep') ?? 2));
        $service   = new ScheduleJobService();

        CLI::write('Schedule worker started.', 'green');

        do {
            $job = $service->claimNext();
            if ($job === null) {
                if ($once) {
                    CLI::write('No queued jobs.', 'yellow');
                    break;
                }
                sleep($sleep);
                continue;
            }

            CLI::write(sprintf('Running job #%d (TA %d)...', $job['id'], $job['tahun_ajaran_id']), 'cyan');

            $service->executeJob($job);

            $finished = $service->getJob((int) $job['id']);
            $status   = $finished['status'] ?? ScheduleJobModel::STATUS_FAILED;
            CLI::write(sprintf('Job #%d finished: %s', $job['id'], $status), $status === ScheduleJobModel::STATUS_COMPLETED ? 'green' : 'red');

            if ($once) {
                break;
            }
        } while (true);

        CLI::write('Schedule worker stopped.', 'green');
    }
}
