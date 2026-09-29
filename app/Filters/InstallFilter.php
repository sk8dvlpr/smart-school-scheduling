<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InstallFilter implements FilterInterface
{
    private const LOCK_FILE = WRITEPATH . 'installed.lock';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (is_cli()) {
            return;
        }

        $path   = trim($request->getUri()->getPath(), '/');
        $isInstall = $path === 'install' || str_starts_with($path, 'install/');
        $installed = is_file(self::LOCK_FILE);

        if (! $installed && ! $isInstall) {
            return redirect()->to('/install');
        }

        if ($installed && $isInstall) {
            if ($path === 'install/finish' && session()->get('install_just_finished')) {
                return;
            }

            return service('response')->setStatusCode(404)->setBody('Not Found');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
