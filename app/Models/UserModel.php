<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'nip',
        'nama',
        'email',
        'no_telp',
        'password',
        'role',
        'is_admin',
        'must_change_password',
        'is_active',
    ];
    protected $validationRules  = [
        'id'    => 'permit_empty|is_natural_no_zero',
        'nip'   => 'permit_empty|max_length[30]|is_unique[users.nip,id,{id}]',
        'nama'  => 'required|min_length[3]|max_length[100]',
        'email' => 'required|valid_email|max_length[100]|is_unique[users.email,id,{id}]',
        'role'  => 'required|in_list[guru,kurikulum,kepala_sekolah]',
    ];
    protected $beforeInsert     = ['hashPassword'];
    protected $beforeUpdate     = ['hashPassword'];

    public static function sessionIsKurikulumAdmin(): bool
    {
        return session()->get('role') === 'kurikulum'
            && (int) session()->get('is_admin') === 1;
    }

    /**
     * Kurikulum with is_admin=1 must not teach.
     */
    public function hasTeachingProfile(int $userId): bool
    {
        return $this->db->table('guru')
            ->where('user_id', $userId)
            ->where('deleted_at IS NULL')
            ->countAllResults() > 0;
    }

    protected function hashPassword(array $data): array
    {
        if (! empty($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['data']['password']);
        }

        return $data;
    }
}
