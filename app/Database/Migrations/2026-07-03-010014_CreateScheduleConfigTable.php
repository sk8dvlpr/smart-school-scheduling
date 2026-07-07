<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleConfigTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'param_key'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'param_value'     => ['type' => 'TEXT'],
            'description'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('schedule_config');
    }

    public function down()
    {
        $this->forge->dropTable('schedule_config');
    }
}
