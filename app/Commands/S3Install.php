<?php

namespace App\Commands;

use App\Libraries\InstallerService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class S3Install extends BaseCommand
{
    protected $group       = 'S3';
    protected $name        = 's3:install';
    protected $description = 'Install S3 via CLI (non-interactive flags or guided prompts).';
    protected $usage       = 's3:install [--hostname=] [--database=] [--username=] [--password=] [--port=3306] [--baseURL=] [--school=] [--admin-name=] [--admin-email=] [--admin-password=] [--no-interaction]';
    protected $options     = [
        '--hostname'       => 'MySQL host (default localhost)',
        '--database'       => 'Database name',
        '--username'       => 'MySQL user',
        '--password'       => 'MySQL password',
        '--port'           => 'MySQL port (default 3306)',
        '--baseURL'        => 'Application base URL',
        '--school'         => 'School display name',
        '--admin-name'     => 'Kurikulum admin full name',
        '--admin-email'    => 'Kurikulum admin email',
        '--admin-password' => 'Kurikulum admin password',
        '--no-interaction' => 'Fail if required values missing instead of prompting',
    ];

    public function run(array $params)
    {
        $installer = new InstallerService();
        if ($installer->isInstalled()) {
            CLI::error('Already installed (writable/installed.lock exists).');

            return EXIT_ERROR;
        }

        $noInteraction = CLI::getOption('no-interaction') !== null;

        $db = [
            'hostname' => CLI::getOption('hostname') ?? ($noInteraction ? null : CLI::prompt('MySQL hostname', 'localhost')),
            'database' => CLI::getOption('database') ?? ($noInteraction ? null : CLI::prompt('Database name', 'smart_school_scheduling')),
            'username' => CLI::getOption('username') ?? ($noInteraction ? null : CLI::prompt('MySQL username', 'root')),
            'password' => CLI::getOption('password') ?? ($noInteraction ? '' : CLI::prompt('MySQL password', null)),
            'port'     => (int) (CLI::getOption('port') ?? 3306),
        ];

        $baseUrl = CLI::getOption('baseURL') ?? ($noInteraction ? null : CLI::prompt('Base URL', 'http://localhost:8080'));
        $school  = CLI::getOption('school') ?? ($noInteraction ? null : CLI::prompt('School name', 'Smart School Scheduling'));
        $admin   = [
            'nama'     => CLI::getOption('admin-name') ?? ($noInteraction ? null : CLI::prompt('Admin name', 'Kurikulum Admin')),
            'email'    => CLI::getOption('admin-email') ?? ($noInteraction ? null : CLI::prompt('Admin email')),
            'password' => CLI::getOption('admin-password') ?? ($noInteraction ? null : CLI::prompt('Admin password')),
        ];

        $required = [
            'hostname'       => $db['hostname'],
            'database'       => $db['database'],
            'username'       => $db['username'],
            'baseURL'        => $baseUrl,
            'school'         => $school,
            'admin-email'    => $admin['email'],
            'admin-password' => $admin['password'],
        ];
        foreach ($required as $label => $val) {
            if ($val === null || $val === '') {
                if ($noInteraction) {
                    CLI::error("Missing required value: {$label}. Pass flags or omit --no-interaction.");

                    return EXIT_ERROR;
                }
            }
        }

        if (! $installer->testConnection($db)) {
            CLI::error('Database connection failed.');

            return EXIT_ERROR;
        }

        try {
            $key = InstallerService::generateEncryptionKey();
            $installer->writeEnv($db, (string) $baseUrl, $key);
            $installer->reloadEnvironment();

            $dbConfig = config('Database');
            $dbConfig->default['hostname'] = $db['hostname'];
            $dbConfig->default['database'] = $db['database'];
            $dbConfig->default['username'] = $db['username'];
            $dbConfig->default['password'] = $db['password'];
            $dbConfig->default['port']     = $db['port'];

            \Config\Database::connect(null, false);

            $installer->runMigrations();
            $installer->seedEmptyTemplate();
            $installer->saveSchoolProfile(['nama_sekolah' => (string) $school]);
            $installer->createAdmin($admin);
            $installer->writeLock();

            CLI::write('Installation complete.', 'green');
            CLI::write('Admin email: ' . $admin['email']);

            return EXIT_SUCCESS;
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }
    }
}
