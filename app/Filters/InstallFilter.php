<?php

namespace App\Filters;

use App\Libraries\InstallerService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InstallFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (is_cli()) {
            return;
        }

        $path       = trim($request->getUri()->getPath(), '/');
        $isInstall  = $path === 'install' || str_starts_with($path, 'install/');
        $installed  = InstallerService::isAppInstalled();

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
