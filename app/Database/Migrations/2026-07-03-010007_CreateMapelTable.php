<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMapelTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'kode'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'tipe'        => ['type' => 'ENUM', 'constraint' => ['umum', 'kejuruan']],
            'jurusan_id'  => ['type' => 'INT', 'null' => true],
            'warna'          => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#3B82F6'],
            'bobot_kognitif' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'default'  => 5,
                'comment'  => 'Beban kognitif 1-10 (tinggi = butuh konsentrasi, diutamakan pagi)',
            ],
            'jam_per_minggu' => ['type' => 'INT', 'default' => 2],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('mapel');
    }

    public function down()
    {
        $this->forge->dropTable('mapel');
    }
}
