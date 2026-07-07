<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\UserModel;

class UserController extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index(): string
    {
        return view('kurikulum/users/index', [
            'title' => 'Manajemen User',
            'users' => $this->userModel->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function create()
    {
        $data = $this->normalizeUserPost($this->request->getPost());
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['password'] = 'password123';
        $data['must_change_password'] = 1;

        if (($data['role'] ?? '') === 'guru') {
            return redirect()->back()->withInput()->with(
                'error',
                'Untuk guru yang mengajar, gunakan modul Manajemen Guru.',
            );
        }

        if (! $this->userModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->userModel->errors());
        }

        $this->userModel->insert($data);

        return redirect()->to('/kurikulum/users')->with('success', 'User berhasil ditambahkan. Password default: password123');
    }

    public function show(int $id)
    {
        $data = $this->userModel->find($id);
        if ($data) {
            unset($data['password']);

            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $data = $this->normalizeUserPost($this->request->getPost());
        $data['id'] = $id;
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;

        $existing = $this->userModel->find($id);
        if ($existing && $existing['role'] === 'guru') {
            return redirect()->back()->withInput()->with(
                'error',
                'User dengan role guru dikelola melalui modul Manajemen Guru.',
            );
        }

        if (($data['role'] ?? '') === 'guru') {
            return redirect()->back()->withInput()->with(
                'error',
                'Untuk guru yang mengajar, gunakan modul Manajemen Guru.',
            );
        }

        if (! $this->userModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->userModel->errors());
        }

        $this->userModel->update($id, $data);

        return redirect()->to('/kurikulum/users')->with('success', 'User berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        if ((int) session()->get('user_id') === $id) {
            return redirect()->to('/kurikulum/users')->with('error', 'Tidak dapat menghapus akun yang sedang login.');
        }

        $db = \Config\Database::connect();
        $hasGuru = $db->table('guru')->where('user_id', $id)->where('deleted_at IS NULL')->countAllResults() > 0;

        if ($hasGuru) {
            return redirect()->to('/kurikulum/users')->with(
                'error',
                'User ini memiliki profil guru. Hapus dari modul Manajemen Guru (opsi hapus akun login).',
            );
        }

        $this->userModel->delete($id);

        return redirect()->to('/kurikulum/users')->with('success', 'User berhasil dihapus.');
    }

    public function resetPassword(int $id)
    {
        if ((int) session()->get('user_id') === $id) {
            return redirect()->to('/kurikulum/users')->with('error', 'Gunakan menu Profil untuk mengganti password sendiri.');
        }

        $user = $this->userModel->find($id);
        if (! $user) {
            return redirect()->to('/kurikulum/users')->with('error', 'User tidak ditemukan.');
        }

        $this->userModel->update($id, [
            'password'             => 'password123',
            'must_change_password' => 1,
        ]);

        return redirect()->to('/kurikulum/users')->with('success', 'Password user ' . $user['nama'] . ' direset ke password123.');
    }

    private function normalizeUserPost(array $data): array
    {
        $data['nip']   = trim($data['nip'] ?? '') ?: null;
        $data['email'] = strtolower(trim($data['email'] ?? ''));

        return $data;
    }
}
