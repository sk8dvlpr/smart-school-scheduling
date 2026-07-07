<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGuruHariBlokirTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'guru_id'    => ['type' => 'INT'],
            'hari_id'    => ['type' => 'INT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['guru_id', 'hari_id']);
        $this->forge->addForeignKey('guru_id', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('guru_hari_blokir');
    }

    public function down()
    {
        $this->forge->dropTable('guru_hari_blokir');
    }
}
