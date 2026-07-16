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
            'title'    => 'Manajemen User',
            'users'    => $this->userModel->orderBy('id', 'DESC')->findAll(),
            'is_admin' => UserModel::sessionIsKurikulumAdmin(),
        ]);
    }

    public function create()
    {
        $data = $this->normalizeUserPost($this->request->getPost());
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['password'] = 'password123';
        $data['must_change_password'] = 1;
        $data['is_admin'] = $this->resolveIsAdminFlag($data['role'] ?? '', null);

        if (($data['role'] ?? '') === 'guru') {
            return redirect()->back()->withInput()->with(
                'error',
                'Untuk guru yang mengajar, gunakan modul Manajemen Guru.',
            );
        }

        if ((int) $data['is_admin'] === 1 && ($data['role'] ?? '') !== 'kurikulum') {
            return redirect()->back()->withInput()->with('error', 'Flag admin hanya untuk role Kurikulum.');
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
        if (! $existing) {
            return redirect()->to('/kurikulum/users')->with('error', 'User tidak ditemukan.');
        }

        if ($existing['role'] === 'guru') {
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

        $data['is_admin'] = $this->resolveIsAdminFlag($data['role'] ?? '', $existing);

        if ((int) $data['is_admin'] === 1) {
            if (($data['role'] ?? '') !== 'kurikulum') {
                return redirect()->back()->withInput()->with('error', 'Flag admin hanya untuk role Kurikulum.');
            }
            if ($this->userModel->hasTeachingProfile($id)) {
                return redirect()->back()->withInput()->with(
                    'error',
                    'Tidak dapat mengaktifkan admin: user ini sudah punya profil mengajar. Hapus profil guru terlebih dahulu.',
                );
            }
        }

        // Prevent demoting own admin flag (lock self out of pengaturan/reset).
        if ((int) session()->get('user_id') === $id
            && UserModel::sessionIsKurikulumAdmin()
            && (int) $data['is_admin'] !== 1
        ) {
            return redirect()->back()->withInput()->with(
                'error',
                'Tidak dapat menonaktifkan flag admin pada akun Anda sendiri.',
            );
        }

        if (! $this->userModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->userModel->errors());
        }

        $this->userModel->update($id, $data);

        // Keep current session in sync if editing self.
        if ((int) session()->get('user_id') === $id) {
            session()->set('is_admin', (int) $data['is_admin']);
        }

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
        if (! UserModel::sessionIsKurikulumAdmin()) {
            return redirect()->to('/kurikulum/users')->with('error', 'Hanya kurikulum admin yang dapat mereset password.');
        }

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

    /**
     * Only kurikulum admins can set/change is_admin. Non-admins keep existing value.
     *
     * @param array<string, mixed>|null $existing
     */
    private function resolveIsAdminFlag(string $role, ?array $existing): int
    {
        if ($role !== 'kurikulum') {
            return 0;
        }

        if (! UserModel::sessionIsKurikulumAdmin()) {
            return (int) ($existing['is_admin'] ?? 0);
        }

        return $this->request->getPost('is_admin') ? 1 : 0;
    }
}
