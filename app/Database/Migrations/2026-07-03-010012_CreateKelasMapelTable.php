<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKelasMapelTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'kelas_id'        => ['type' => 'INT'],
            'mapel_id'        => ['type' => 'INT'],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'jam_per_minggu'  => ['type' => 'INT'],
            'butuh_lab'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'lab_id'          => ['type' => 'INT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['kelas_id', 'mapel_id', 'tahun_ajaran_id']);
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lab_id', 'ruangan', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('kelas_mapel');
    }

    public function down()
    {
        $this->forge->dropTable('kelas_mapel');
    }
}
