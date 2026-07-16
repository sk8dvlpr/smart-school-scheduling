<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsAdminToUsersTable extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_admin', 'users')) {
            $this->forge->addColumn('users', [
                'is_admin' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'role',
                ],
            ]);
        }

        // Promote existing kurikulum staff without teaching profile so they keep pengaturan/reset access.
        $this->db->query(
            'UPDATE users u
             SET u.is_admin = 1
             WHERE u.role = \'kurikulum\'
               AND u.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM guru g
                   WHERE g.user_id = u.id AND g.deleted_at IS NULL
               )'
        );
    }

    public function down()
    {
        if ($this->db->fieldExists('is_admin', 'users')) {
            $this->forge->dropColumn('users', 'is_admin');
        }
    }
}
