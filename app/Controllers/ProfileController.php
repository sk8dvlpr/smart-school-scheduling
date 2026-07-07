<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class ProfileController extends BaseController
{
    public function index(): string
    {
        $user = (new UserModel())->find((int) session()->get('user_id'));

        return view('profile/index', [
            'title' => 'Profil',
            'user'  => $user,
        ]);
    }

    public function update(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');

        if (! $this->validate([
            'nama'    => 'required|min_length[3]|max_length[100]',
            'email'   => "required|valid_email|max_length[100]|is_unique[users.email,id,{$userId}]",
            'no_telp' => 'permit_empty|max_length[20]',
        ])) {
            return redirect()->back()->withInput()->with('error', 'Validasi profil gagal.');
        }

        $userModel = new UserModel();
        $userModel->update($userId, [
            'nama'    => $this->request->getPost('nama'),
            'email'   => strtolower(trim((string) $this->request->getPost('email'))),
            'no_telp' => $this->request->getPost('no_telp'),
        ]);

        session()->set('nama', $this->request->getPost('nama'));

        return redirect()->back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function changePassword(): RedirectResponse
    {
        $userId    = (int) session()->get('user_id');
        $userModel = new UserModel();
        $user      = $userModel->find($userId);

        if (! $user) {
            return redirect()->back()->with('error', 'User tidak ditemukan.');
        }

        if (! $this->validate([
            'password_lama'      => 'required',
            'password_baru'      => 'required|min_length[6]',
            'password_konfirmasi'=> 'required|matches[password_baru]',
        ])) {
            return redirect()->back()->with('error', 'Validasi password gagal.');
        }

        if (! password_verify((string) $this->request->getPost('password_lama'), $user['password'])) {
            return redirect()->back()->with('error', 'Password lama tidak sesuai.');
        }

        $userModel->update($userId, [
            'password'             => (string) $this->request->getPost('password_baru'),
            'must_change_password' => 0,
        ]);

        session()->set('must_change_password', 0);

        return redirect()->back()->with('success', 'Password berhasil diperbarui.');
    }
}
