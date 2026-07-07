<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHariTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'auto_increment' => true],
            'nama'   => ['type' => 'VARCHAR', 'constraint' => 10],
            'kode'   => ['type' => 'VARCHAR', 'constraint' => 3],
            'urutan' => ['type' => 'INT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->createTable('hari');
    }

    public function down()
    {
        $this->forge->dropTable('hari');
    }
}
