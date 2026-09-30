<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class BenchmarkGenerate extends BaseCommand
{
    protected $group       = 'schedule';
    protected $name        = 'benchmark:generate';
    protected $description = 'Benchmark schedule generation timing (dry-run without full generate when DB unavailable).';
    protected $usage       = 'benchmark:generate [--classes=N] [--seed=S]';
    protected $options     = [
        '--classes' => 'Number of classes to simulate (default 10).',
        '--seed'    => 'Random seed for reproducibility (optional).',
    ];

    public function run(array $params)
    {
        $classes = max(1, (int) (CLI::getOption('classes') ?? ($params['classes'] ?? 10)));
        $seedOpt = CLI::getOption('seed') ?? ($params['seed'] ?? null);
        $seed    = $seedOpt !== null && $seedOpt !== '' ? (int) $seedOpt : null;

        if ($seed !== null) {
            mt_srand($seed);
        }

        CLI::write('Benchmark generate (Phase 3 stub)', 'green');
        CLI::write(sprintf('Options: classes=%d%s', $classes, $seed !== null ? ', seed=' . $seed : ''));

        try {
            $db = Database::connect();
            $db->query('SELECT 1');
        } catch (\Throwable $e) {
            CLI::write('Database not available — dry note only.', 'yellow');
            CLI::write(sprintf('Would benchmark CSP+GA for ~%d kelas worth of kelas_mapel units.', $classes));
            CLI::write('Run `php spark schedule:worker` with active TA and master data for a real timing run.');

            return;
        }

        $start = microtime(true);
        // Lightweight DB ping + simulated work stand-in (no ScheduleGenerator call in stub).
        usleep(1000);
        $elapsed = round(microtime(true) - $start, 4);

        CLI::write(sprintf('DB reachable. Stub elapsed: %ss', $elapsed), 'cyan');
        CLI::write('For end-to-end timing, enqueue generate via UI and watch schedule_jobs + schedule_logs.execution_time.');
    }
}
