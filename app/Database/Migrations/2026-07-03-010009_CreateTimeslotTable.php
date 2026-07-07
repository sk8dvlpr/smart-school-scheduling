<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTimeslotTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'hari_id'       => ['type' => 'INT'],
            'jam_ke'        => ['type' => 'INT'],
            'waktu_mulai'   => ['type' => 'TIME'],
            'waktu_selesai' => ['type' => 'TIME'],
            'tipe'          => ['type' => 'ENUM', 'constraint' => ['jp', 'istirahat', 'kegiatan_khusus']],
            'keterangan'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('hari_id', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('timeslot');
    }

    public function down()
    {
        $this->forge->dropTable('timeslot');
    }
}
