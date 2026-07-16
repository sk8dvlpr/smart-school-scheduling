<?php

namespace App\Libraries;

use App\Models\GuruModel;
use App\Models\UserModel;
use Config\Database;
use RuntimeException;

class GuruProvisioningService
{
    private UserModel $userModel;
    private GuruModel $guruModel;

    public function __construct(?UserModel $userModel = null, ?GuruModel $guruModel = null)
    {
        $this->userModel = $userModel ?? new UserModel();
        $this->guruModel = $guruModel ?? new GuruModel();
    }

    /**
     * @return array{nip: ?string, nama: string, email: string, no_telp: ?string, role: string}
     */
    public static function normalizeUserFields(array $data): array
    {
        $role = trim($data['role'] ?? 'guru');

        return [
            'nip'     => trim($data['nip'] ?? '') ?: null,
            'nama'    => trim($data['nama'] ?? ''),
            'email'   => strtolower(trim($data['email'] ?? '')),
            'no_telp' => trim($data['no_telp'] ?? '') ?: null,
            'role'    => in_array($role, ['guru', 'kurikulum'], true) ? $role : 'guru',
        ];
    }

    /**
     * @return array{guru_id: int, user_id: int}
     */
    public function provisionNewGuru(array $userFields): array
    {
        $userFields = self::normalizeUserFields($userFields);
        $this->assertUserIdentity($userFields);

        $db = Database::connect();
        $db->transStart();

        try {
            $userId = $this->insertUser($userFields);
            $guruId = $this->insertGuruProfile($userId);
            $this->completeTransaction($db);

            return ['guru_id' => $guruId, 'user_id' => $userId];
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{guru_id: int, user_id: int}
     */
    public function attachGuruToUser(int $userId): array
    {
        $user = $this->userModel->find($userId);
        if (! $user) {
            throw new RuntimeException('User tidak ditemukan.');
        }

        if (! in_array($user['role'], ['guru', 'kurikulum'], true)) {
            throw new RuntimeException('User harus berrole guru atau kurikulum.');
        }

        if ((int) ($user['is_admin'] ?? 0) === 1) {
            throw new RuntimeException('User kurikulum admin tidak boleh memiliki profil mengajar.');
        }

        if ($this->guruModel->where('user_id', $userId)->first()) {
            throw new RuntimeException('User ini sudah memiliki profil guru.');
        }

        $db = Database::connect();
        $db->transStart();

        try {
            $guruId = $this->insertGuruProfile($userId);
            $this->completeTransaction($db);

            return ['guru_id' => $guruId, 'user_id' => $userId];
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Import / upsert by email — creates or updates user then guru profile.
     *
     * @return array{guru_id: int, user_id: int}
     */
    public function upsertByEmail(array $userFields): array
    {
        $userFields = self::normalizeUserFields($userFields);
        $this->assertUserIdentity($userFields);

        $existingUser = $this->userModel->where('email', $userFields['email'])->first();

        if ($existingUser && (int) ($existingUser['is_admin'] ?? 0) === 1) {
            throw new RuntimeException('User kurikulum admin tidak boleh memiliki profil mengajar: ' . $userFields['email']);
        }

        $db = Database::connect();
        $db->transStart();

        try {
            if ($existingUser) {
                $userId = (int) $existingUser['id'];
                $this->updateUser($userId, $userFields);
            } else {
                $userId = $this->insertUser($userFields);
            }

            $existingGuru = $this->guruModel->where('user_id', $userId)->first();
            if ($existingGuru) {
                $guruId = (int) $existingGuru['id'];
            } else {
                $guruId = $this->insertGuruProfile($userId);
            }

            $this->completeTransaction($db);

            return ['guru_id' => $guruId, 'user_id' => $userId];
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function updateGuruWithUser(int $guruId, array $userFields): void
    {
        $guru = $this->guruModel->find($guruId);
        if (! $guru) {
            throw new RuntimeException('Profil guru tidak ditemukan.');
        }

        $linkedUser = $this->userModel->find((int) $guru['user_id']);
        if ($linkedUser && (int) ($linkedUser['is_admin'] ?? 0) === 1) {
            throw new RuntimeException('User kurikulum admin tidak boleh memiliki profil mengajar.');
        }

        $userFields = self::normalizeUserFields($userFields);
        $this->assertUserIdentity($userFields);

        $db = Database::connect();
        $db->transStart();

        try {
            $this->updateUser((int) $guru['user_id'], $userFields);
            $this->completeTransaction($db);
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function deleteGuru(int $guruId, bool $deleteUser = false): void
    {
        $guru = $this->guruModel->find($guruId);
        if (! $guru) {
            throw new RuntimeException('Profil guru tidak ditemukan.');
        }

        $db = Database::connect();
        $hasMapel  = $db->table('guru_mapel')->where('guru_id', $guruId)->countAllResults() > 0;
        $hasJadwal = $db->table('jadwal')->where('guru_id', $guruId)->countAllResults() > 0;

        if ($hasMapel || $hasJadwal) {
            throw new RuntimeException('Gagal menghapus! Guru ini memiliki kompetensi mapel atau jadwal aktif.');
        }

        $db->transStart();

        try {
            $this->guruModel->delete($guruId);

            if ($deleteUser) {
                $this->userModel->delete((int) $guru['user_id']);
            }

            $this->completeTransaction($db);
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function assertUserIdentity(array $userFields): void
    {
        if ($userFields['email'] === '' || $userFields['nama'] === '') {
            throw new RuntimeException('Email dan nama wajib diisi.');
        }
    }

    private function insertUser(array $userFields): int
    {
        $userData = array_merge($userFields, [
            'password'             => 'password123',
            'must_change_password' => 1,
            'is_active'            => 1,
            'is_admin'             => 0,
        ]);

        if (! $this->userModel->validate($userData)) {
            throw new RuntimeException(implode(', ', $this->userModel->errors()));
        }

        return (int) $this->userModel->insert($userData);
    }

    private function updateUser(int $userId, array $userFields): void
    {
        $userData = array_merge($userFields, ['id' => $userId]);

        if (! $this->userModel->validate($userData)) {
            throw new RuntimeException(implode(', ', $this->userModel->errors()));
        }

        $this->userModel->update($userId, $userFields);
    }

    private function insertGuruProfile(int $userId): int
    {
        $guruData = ['user_id' => $userId];

        if (! $this->guruModel->validate($guruData)) {
            throw new RuntimeException(implode(', ', $this->guruModel->errors()));
        }

        return (int) $this->guruModel->insert($guruData);
    }

    private function completeTransaction($db): void
    {
        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('Gagal menyimpan data guru.');
        }
    }
}
