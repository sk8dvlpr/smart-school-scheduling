<?php

namespace App\Controllers;

use App\Models\GuruModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    public function index(): RedirectResponse|string
    {
        if (session()->get('logged_in')) {
            if (session()->get('must_change_password')) {
                return redirect()->to('/auth/change-password');
            }

            return $this->redirectByRole(session()->get('role'));
        }

        return view('auth/login', [
            'branding' => \App\Libraries\BrandingService::get(),
        ]);
    }

    public function login(): RedirectResponse
    {
        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $password = $this->request->getPost('password');

        if (! $this->validate([
            'email'    => 'required|valid_email',
            'password' => 'required',
        ])) {
            return redirect()->back()->with('error', 'Email dan Password wajib diisi.');
        }

        $userModel = new UserModel();
        $user      = $userModel->where('email', $email)
            ->where('deleted_at IS NULL')
            ->where('is_active', 1)
            ->first();

        if (! $user || ! password_verify($password, $user['password'])) {
            return redirect()->back()->with('error', 'Email atau password salah.');
        }

        $guruId = null;
        $guru   = (new GuruModel())->where('user_id', $user['id'])
            ->where('deleted_at IS NULL')
            ->first();
        if ($guru) {
            $guruId = (int) $guru['id'];
        }

        $isAdmin = (int) ($user['is_admin'] ?? 0) === 1
            && ($user['role'] ?? '') === 'kurikulum';

        // Admin kurikulum never uses teaching session context.
        if ($isAdmin) {
            $guruId = null;
        }

        session()->regenerate();
        session()->set([
            'user_id'              => (int) $user['id'],
            'role'                 => $user['role'],
            'nama'                 => $user['nama'],
            'guru_id'              => $guruId,
            'is_admin'             => $isAdmin ? 1 : 0,
            'must_change_password' => (int) $user['must_change_password'],
            'logged_in'            => true,
        ]);

        if ((int) $user['must_change_password'] === 1) {
            return redirect()->to('/auth/change-password');
        }

        return $this->redirectByRole($user['role']);
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to('/auth/login');
    }

    public function changePasswordForm(): RedirectResponse|string
    {
        if (! session()->get('logged_in')) {
            return redirect()->to('/auth/login');
        }

        if (! session()->get('must_change_password')) {
            return $this->redirectByRole(session()->get('role'));
        }

        return view('auth/change_password');
    }

    public function changePassword(): RedirectResponse
    {
        if (! session()->get('logged_in')) {
            return redirect()->to('/auth/login');
        }

        if (! $this->validate([
            'password_baru'      => 'required|min_length[6]',
            'password_konfirmasi'=> 'required|matches[password_baru]',
        ])) {
            return redirect()->back()->withInput()->with('error', 'Validasi password gagal.');
        }

        $userModel = new UserModel();
        $userId    = (int) session()->get('user_id');

        $userModel->update($userId, [
            'password'             => (string) $this->request->getPost('password_baru'),
            'must_change_password' => 0,
        ]);

        session()->set('must_change_password', 0);

        return $this->redirectByRole(session()->get('role'))
            ->with('success', 'Password berhasil diubah.');
    }

    private function redirectByRole(?string $role): RedirectResponse
    {
        return match ($role) {
            'kurikulum'      => redirect()->to('/kurikulum/dashboard'),
            'guru'           => redirect()->to('/guru/dashboard'),
            'kepala_sekolah' => redirect()->to('/kepala-sekolah/dashboard'),
            default          => redirect()->to('/auth/login'),
        };
    }
}
