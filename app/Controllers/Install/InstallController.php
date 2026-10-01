<?php

namespace App\Controllers\Install;

use App\Controllers\BaseController;
use App\Libraries\InstallerService;
use CodeIgniter\HTTP\ResponseInterface;

class InstallController extends BaseController
{
    private InstallerService $installer;

    public function __construct()
    {
        $this->installer = new InstallerService();
    }

    /**
     * @return string
     */
    public function index()
    {
        $locale = $this->request->getGet('lang');
        if (in_array($locale, ['id', 'en'], true)) {
            session()->set('install_locale', $locale);
        }

        return view('install/welcome', [
            'title'  => 'Instalasi Smart School Scheduling',
            'step'   => 1,
            'locale' => session()->get('install_locale') ?? 'id',
        ]);
    }

    /**
     * @return string
     */
    public function requirements()
    {
        return view('install/requirements', [
            'title' => 'Persyaratan Sistem',
            'step'  => 2,
            'checks' => $this->runRequirementChecks(),
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function requirementsPost()
    {
        $checks = $this->runRequirementChecks();
        foreach ($checks as $check) {
            if (! $check['ok'] && empty($check['optional'])) {
                return redirect()->back()->with('error', 'Masih ada persyaratan wajib yang belum terpenuhi.');
            }
        }

        return redirect()->to('/install/database');
    }

    /**
     * @return string
     */
    public function database()
    {
        $wizard = session()->get('install') ?? [];

        return view('install/database', [
            'title'  => 'Database',
            'step'   => 3,
            'wizard' => $wizard,
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function databasePost()
    {
        $rules = [
            'hostname' => 'required|max_length[255]',
            'database' => 'required|max_length[64]|regex_match[/^[a-zA-Z0-9_]+$/]',
            'username' => 'required|max_length[64]',
            'password' => 'permit_empty|max_length[255]',
            'port'     => 'required|is_natural|less_than_equal_to[65535]',
            'baseURL'  => 'required|valid_url|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = [
            'hostname' => trim((string) $this->request->getPost('hostname')),
            'database' => trim((string) $this->request->getPost('database')),
            'username' => trim((string) $this->request->getPost('username')),
            'password' => (string) $this->request->getPost('password'),
            'port'     => (int) $this->request->getPost('port'),
        ];

        if (! $this->installer->testConnection($db)) {
            return redirect()->back()->withInput()->with('error', 'Koneksi database gagal. Periksa host, user, dan password.');
        }

        $wizard = session()->get('install') ?? [];
        $wizard['db']      = $db;
        $wizard['baseURL'] = rtrim((string) $this->request->getPost('baseURL'), '/');
        session()->set('install', $wizard);

        return redirect()->to('/install/setup');
    }

    public function testDatabase(): ResponseInterface
    {
        $db = [
            'hostname' => trim((string) $this->request->getPost('hostname')),
            'database' => trim((string) $this->request->getPost('database')),
            'username' => trim((string) $this->request->getPost('username')),
            'password' => (string) $this->request->getPost('password'),
            'port'     => (int) ($this->request->getPost('port') ?: 3306),
        ];

        $ok = $this->installer->testConnection($db);

        return $this->response->setJSON([
            'ok'   => $ok,
            'csrf' => [
                'name' => csrf_token(),
                'hash' => csrf_hash(),
            ],
        ]);
    }

    /**
     * @return string
     */
    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function setup()
    {
        $wizard = session()->get('install');
        if (empty($wizard['db'])) {
            return redirect()->to('/install/database');
        }

        return view('install/setup', [
            'title'  => 'Sekolah & Admin',
            'step'   => 4,
            'wizard' => $wizard,
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function setupPost()
    {
        $wizard = session()->get('install');
        if (empty($wizard['db'])) {
            return redirect()->to('/install/database');
        }

        $rules = [
            'nama_sekolah' => 'required|min_length[3]|max_length[150]',
            'admin_nama'   => 'required|min_length[3]|max_length[100]',
            'admin_email'  => 'required|valid_email|max_length[100]',
            'admin_password' => 'required|min_length[8]|max_length[72]',
            'admin_password_confirm' => 'required|matches[admin_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $wizard['school'] = [
            'nama_sekolah' => trim((string) $this->request->getPost('nama_sekolah')),
        ];
        $wizard['admin'] = [
            'nama'     => trim((string) $this->request->getPost('admin_nama')),
            'email'    => trim((string) $this->request->getPost('admin_email')),
            'password' => (string) $this->request->getPost('admin_password'),
        ];
        $wizard['template'] = 'empty';
        session()->set('install', $wizard);

        return redirect()->to('/install/run');
    }

    /**
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function run()
    {
        $wizard = session()->get('install');
        if (empty($wizard['db']) || empty($wizard['admin']) || empty($wizard['school'])) {
            return redirect()->to('/install');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->executeInstall($wizard);
        }

        return view('install/run', [
            'title'  => 'Menjalankan Instalasi',
            'step'   => 5,
            'wizard' => $wizard,
        ]);
    }

    /**
     * @return string
     */
    public function finish()
    {
        session()->remove('install_just_finished');
        $email = session()->getFlashdata('install_admin_email');

        return view('install/finish', [
            'title' => 'Instalasi Selesai',
            'step'  => 6,
            'email' => $email,
        ]);
    }

    /**
     * @param array<string, mixed> $wizard
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    private function executeInstall(array $wizard)
    {
        try {
            $key = InstallerService::generateEncryptionKey();
            $this->installer->writeEnv($wizard['db'], $wizard['baseURL'] ?? site_url(), $key);
            $this->installer->reloadEnvironment();

            /** @var \Config\Database $dbConfig */
            $dbConfig = config('Database');
            $dbConfig->default['hostname'] = $wizard['db']['hostname'];
            $dbConfig->default['database'] = $wizard['db']['database'];
            $dbConfig->default['username'] = $wizard['db']['username'];
            $dbConfig->default['password'] = $wizard['db']['password'];
            $dbConfig->default['port']     = (int) ($wizard['db']['port'] ?? 3306);

            \Config\Database::connect(null, false);

            $this->installer->runMigrations();
            $this->installer->seedEmptyTemplate();

            $this->installer->saveSchoolProfile($wizard['school']);
            $this->installer->createAdmin($wizard['admin']);
            $this->installer->writeLock();

            // Drop plaintext admin password from session immediately after success.
            session()->remove('install');
            session()->regenerate(true);
            session()->set('install_just_finished', true);

            return redirect()->to('/install/finish')
                ->with('install_admin_email', $wizard['admin']['email']);
        } catch (\Throwable $e) {
            log_message('error', 'Install failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/install/run')->with('error', 'Instalasi gagal: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array{label: string, ok: bool, hint: string, optional?: bool}>
     */
    private function runRequirementChecks(): array
    {
        $ext = static fn (string $name): bool => extension_loaded($name);

        return [
            [
                'label' => 'PHP 8.2+',
                'ok'    => version_compare(PHP_VERSION, '8.2.0', '>='),
                'hint'  => 'Versi saat ini: ' . PHP_VERSION,
            ],
            [
                'label' => 'Ekstensi intl',
                'ok'    => $ext('intl'),
                'hint'  => 'Diperlukan CodeIgniter 4',
            ],
            [
                'label' => 'Ekstensi mbstring',
                'ok'    => $ext('mbstring'),
                'hint'  => '',
            ],
            [
                'label' => 'Ekstensi mysqli',
                'ok'    => $ext('mysqli'),
                'hint'  => 'Koneksi MySQL/MariaDB',
            ],
            [
                'label'    => 'Ekstensi gd atau imagick (ekspor PDF/gambar)',
                'ok'       => $ext('gd') || $ext('imagick'),
                'hint'     => 'Disarankan untuk DomPDF / logo',
                'optional' => true,
            ],
            [
                'label'    => 'Ekstensi zip (ekspor Excel)',
                'ok'       => $ext('zip'),
                'hint'     => 'Disarankan untuk PhpSpreadsheet',
                'optional' => true,
            ],
            [
                'label' => 'Folder writable dapat ditulis',
                'ok'    => is_writable(WRITEPATH),
                'hint'  => WRITEPATH,
            ],
            [
                'label' => 'Folder public/uploads dapat ditulis',
                'ok'    => is_dir(FCPATH . 'uploads') ? is_writable(FCPATH . 'uploads') : @mkdir(FCPATH . 'uploads', 0755, true),
                'hint'  => FCPATH . 'uploads',
            ],
        ];
    }
}
