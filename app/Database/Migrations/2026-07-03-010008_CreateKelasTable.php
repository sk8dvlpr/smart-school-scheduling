<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKelasTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'nama'            => ['type' => 'VARCHAR', 'constraint' => 20],
            'tingkat'         => ['type' => 'ENUM', 'constraint' => ['X', 'XI', 'XII']],
            'jurusan_id'      => ['type' => 'INT'],
            'ruangan_id'      => ['type' => 'INT'],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['nama', 'tahun_ajaran_id']);
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ruangan_id', 'ruangan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('kelas');
    }

    public function down()
    {
        $this->forge->dropTable('kelas');
    }
}
