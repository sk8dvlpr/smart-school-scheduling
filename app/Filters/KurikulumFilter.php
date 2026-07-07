<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class KurikulumFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            return redirect()->to('/auth/login');
        }

        if (session()->get('must_change_password')) {
            return redirect()->to('/auth/change-password');
        }

        if (session()->get('role') !== 'kurikulum') {
            return redirect()->to('/auth/login')->with('error', 'Akses ditolak. Halaman khusus Kurikulum.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
