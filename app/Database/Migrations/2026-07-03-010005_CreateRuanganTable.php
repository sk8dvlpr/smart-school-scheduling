<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRuanganTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'kode'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'tipe'        => ['type' => 'ENUM', 'constraint' => ['kelas', 'lab']],
            'kapasitas'   => ['type' => 'INT', 'default' => 40],
            'jurusan_id'  => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ruangan');
    }

    public function down()
    {
        $this->forge->dropTable('ruangan');
    }
}
