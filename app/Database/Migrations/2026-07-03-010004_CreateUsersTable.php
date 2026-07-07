<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'auto_increment' => true],
            'nip'                  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nama'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 100],
            'no_telp'              => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'password'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'                 => ['type' => 'ENUM', 'constraint' => ['guru', 'kurikulum', 'kepala_sekolah']],
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
