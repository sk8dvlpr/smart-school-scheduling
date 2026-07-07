<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJadwalTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'kelas_mapel_id'  => ['type' => 'INT'],
            'hari_id'         => ['type' => 'INT'],
            'timeslot_id'     => ['type' => 'INT'],
            'kelas_id'        => ['type' => 'INT'],
            'guru_id'         => ['type' => 'INT'],
            'mapel_id'        => ['type' => 'INT'],
            'ruangan_id'      => ['type' => 'INT'],
            'blok_group'      => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['hari_id', 'timeslot_id', 'kelas_id', 'tahun_ajaran_id'], 'jadwal_kelas_conflict');
        $this->forge->addUniqueKey(['hari_id', 'timeslot_id', 'guru_id', 'tahun_ajaran_id'], 'jadwal_guru_conflict');
        $this->forge->addUniqueKey(['hari_id', 'timeslot_id', 'ruangan_id', 'tahun_ajaran_id'], 'jadwal_ruangan_conflict');
        $this->forge->addKey(['tahun_ajaran_id', 'kelas_id'], false, false, 'idx_jadwal_ta_kelas');
        $this->forge->addKey(['tahun_ajaran_id', 'guru_id'], false, false, 'idx_jadwal_ta_guru');
        $this->forge->addKey(['tahun_ajaran_id', 'hari_id', 'timeslot_id'], false, false, 'idx_jadwal_ta_hari_slot');

        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('kelas_mapel_id', 'kelas_mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('timeslot_id', 'timeslot', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ruangan_id', 'ruangan', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('jadwal');
    }

    public function down()
    {
        $this->forge->dropTable('jadwal');
    }
}
