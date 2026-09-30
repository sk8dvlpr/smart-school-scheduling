<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleJobsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id'  => ['type' => 'INT'],
            'user_id'          => ['type' => 'INT'],
            'status'           => [
                'type'       => 'ENUM',
                'constraint' => ['queued', 'running', 'completed', 'failed', 'cancelled'],
                'default'    => 'queued',
            ],
            'progress'         => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'generation'       => ['type' => 'INT', 'null' => true],
            'best_fitness'     => ['type' => 'FLOAT', 'null' => true],
            'parent_log_id'    => ['type' => 'INT', 'null' => true],
            'generate_mode'    => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'fresh'],
            'schedule_log_id'  => ['type' => 'INT', 'null' => true],
            'error_message'    => ['type' => 'TEXT', 'null' => true],
            'cancel_requested' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'started_at'       => ['type' => 'DATETIME', 'null' => true],
            'finished_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'tahun_ajaran_id']);
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('parent_log_id', 'schedule_logs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('schedule_log_id', 'schedule_logs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('schedule_jobs');
    }

    public function down()
    {
        $this->forge->dropTable('schedule_jobs');
    }
}
