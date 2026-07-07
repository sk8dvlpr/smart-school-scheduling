<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class KepalaSekolahFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            return redirect()->to('/auth/login');
        }

        if (session()->get('must_change_password')) {
            return redirect()->to('/auth/change-password');
        }

        if (session()->get('role') !== 'kepala_sekolah') {
            return redirect()->to('/auth/login')->with('error', 'Akses ditolak. Halaman khusus Kepala Sekolah.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
