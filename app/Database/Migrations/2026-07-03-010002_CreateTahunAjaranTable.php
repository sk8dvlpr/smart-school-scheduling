<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTahunAjaranTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'auto_increment' => true],
            'nama'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'semester'         => ['type' => 'ENUM', 'constraint' => ['ganjil', 'genap']],
            'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'tanggal_mulai'    => ['type' => 'DATE'],
            'tanggal_selesai'  => ['type' => 'DATE'],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tahun_ajaran');
    }

    public function down()
    {
        $this->forge->dropTable('tahun_ajaran');
    }
}
