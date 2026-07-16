<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Libraries\GuruProvisioningService;
use App\Models\GuruModel;
use App\Models\UserModel;
use RuntimeException;

class GuruController extends BaseController
{
    protected GuruModel $guruModel;
    protected UserModel $userModel;
    protected GuruProvisioningService $provisioning;

    public function __construct()
    {
        $this->guruModel    = new GuruModel();
        $this->userModel    = new UserModel();
        $this->provisioning = new GuruProvisioningService($this->userModel, $this->guruModel);
    }

    public function index(): string
    {
        $db = \Config\Database::connect();
        $guru = $db->table('guru')
            ->select('guru.*, users.nip, users.nama, users.email, users.no_telp, users.role')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.deleted_at IS NULL')
            ->where('users.deleted_at IS NULL')
            ->orderBy('guru.id', 'DESC')
            ->get()
            ->getResultArray();

        $existingUserIds = array_column($guru, 'user_id');
        $availableUsers  = $db->table('users')
            ->whereIn('role', ['guru', 'kurikulum'])
            ->where('deleted_at IS NULL')
            ->where('is_active', 1)
            ->where('is_admin', 0)
            ->orderBy('nama', 'ASC')
            ->get()
            ->getResultArray();
        $availableUsers = array_values(array_filter(
            $availableUsers,
            fn ($u) => ! in_array($u['id'], $existingUserIds, true),
        ));

        return view('kurikulum/guru/index', [
            'title'           => 'Manajemen Guru',
            'guru'            => $guru,
            'available_users' => $availableUsers,
        ]);
    }

    public function create()
    {
        $post = $this->request->getPost();

        try {
            if (($post['entry_mode'] ?? 'new') === 'existing') {
                $result = $this->provisioning->attachGuruToUser((int) ($post['user_id'] ?? 0));
            } else {
                $result = $this->provisioning->provisionNewGuru([
                    'email'   => $post['email'] ?? '',
                    'nama'    => $post['nama'] ?? '',
                    'nip'     => $post['nip'] ?? '',
                    'no_telp' => $post['no_telp'] ?? '',
                    'role'    => $post['role'] ?? 'guru',
                ]);
            }
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('errors', [$e->getMessage()]);
        }

        return redirect()->to('/kurikulum/guru/' . $result['guru_id'] . '/mapel')
            ->with('success', 'Guru berhasil ditambahkan. Password default: password123');
    }

    public function show(int $id)
    {
        $db   = \Config\Database::connect();
        $data = $db->table('guru')
            ->select('guru.*, users.nip, users.nama, users.email, users.no_telp, users.role')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.id', $id)
            ->get()
            ->getRowArray();

        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $post = $this->request->getPost();

        try {
            $this->provisioning->updateGuruWithUser($id, [
                'email'   => $post['email'] ?? '',
                'nama'    => $post['nama'] ?? '',
                'nip'     => $post['nip'] ?? '',
                'no_telp' => $post['no_telp'] ?? '',
                'role'    => $post['role'] ?? 'guru',
            ]);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('errors', [$e->getMessage()]);
        }

        return redirect()->to('/kurikulum/guru')->with('success', 'Profil guru berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $deleteUser = (bool) $this->request->getPost('delete_user');

        try {
            $this->provisioning->deleteGuru($id, $deleteUser);
        } catch (RuntimeException $e) {
            return redirect()->to('/kurikulum/guru')->with('error', $e->getMessage());
        }

        $msg = $deleteUser
            ? 'Profil guru dan akun login berhasil dihapus.'
            : 'Profil guru berhasil dihapus. Akun login tetap aktif.';

        return redirect()->to('/kurikulum/guru')->with('success', $msg);
    }

    public function import()
    {
        $file = $this->request->getFile('csv_file');
        if (! $file || ! $file->isValid()) {
            return redirect()->to('/kurikulum/guru')->with('error', 'File CSV tidak valid.');
        }

        $handle = fopen($file->getTempName(), 'r');
        if (! $handle) {
            return redirect()->to('/kurikulum/guru')->with('error', 'Gagal membaca file CSV.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return redirect()->to('/kurikulum/guru')->with('error', 'File CSV kosong.');
        }

        $header = array_map('strtolower', array_map('trim', $header));
        foreach (['email', 'nama'] as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);

                return redirect()->to('/kurikulum/guru')->with('error', "Kolom wajib tidak ditemukan: $col");
            }
        }

        $success = 0;
        $failed  = 0;
        $errors  = [];
        $line    = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), ''));
            if ($data === false) {
                $failed++;
                $errors[] = "Baris $line: format tidak valid.";

                continue;
            }

            $role = trim($data['role'] ?? 'guru');
            if (! in_array($role, ['guru', 'kurikulum'], true)) {
                $role = 'guru';
            }

            try {
                $this->provisioning->upsertByEmail([
                    'email'   => $data['email'] ?? '',
                    'nama'    => $data['nama'] ?? '',
                    'nip'     => $data['nip'] ?? '',
                    'role'    => $role,
                ]);
                $success++;
            } catch (RuntimeException $e) {
                $failed++;
                $errors[] = "Baris $line: " . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "$success baris berhasil diimport.";
        if ($failed > 0) {
            $msg .= " $failed baris gagal.";
        }

        $redirect = redirect()->to('/kurikulum/guru')->with('success', $msg);
        if ($errors) {
            $redirect = $redirect->with('import_errors', array_slice($errors, 0, 20));
        }

        return $redirect;
    }
}
