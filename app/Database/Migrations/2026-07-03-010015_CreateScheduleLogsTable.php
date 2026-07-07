<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'status'          => ['type' => 'ENUM', 'constraint' => ['running', 'completed', 'failed', 'partial']],
            'fitness_score'   => ['type' => 'DECIMAL', 'constraint' => '5,4', 'null' => true],
            'generations_run' => ['type' => 'INT', 'null' => true],
            'total_conflicts' => ['type' => 'INT', 'null' => true],
            'execution_time'  => ['type' => 'INT', 'null' => true],
            'error_message'   => ['type' => 'TEXT', 'null' => true],
            'result_report'   => ['type' => 'TEXT', 'null' => true],
            'generated_by'    => ['type' => 'INT'],
            'started_at'      => ['type' => 'DATETIME'],
            'completed_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('generated_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('schedule_logs');
    }

    public function down()
    {
        $this->forge->dropTable('schedule_logs');
    }
}
