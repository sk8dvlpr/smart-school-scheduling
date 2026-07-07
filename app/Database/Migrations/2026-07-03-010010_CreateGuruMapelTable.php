<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGuruMapelTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'auto_increment' => true],
            'guru_id'           => ['type' => 'INT'],
            'mapel_id'          => ['type' => 'INT'],
            'max_jam_per_minggu'=> ['type' => 'INT'],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['guru_id', 'mapel_id']);
        $this->forge->addForeignKey('guru_id', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('guru_mapel');
    }

    public function down()
    {
        $this->forge->dropTable('guru_mapel');
    }
}
