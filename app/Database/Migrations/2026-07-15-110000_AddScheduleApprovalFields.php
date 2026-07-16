<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddScheduleApprovalFields extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('approval_status', 'schedule_logs')) {
            $this->forge->addColumn('schedule_logs', [
                'approval_status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['pending', 'approved', 'rejected'],
                    'null'       => true,
                    'after'      => 'published_by',
                ],
                'approved_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'approval_status',
                ],
                'approved_by' => [
                    'type' => 'INT',
                    'null' => true,
                    'after' => 'approved_at',
                ],
                'approval_note' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => true,
                    'after'      => 'approved_by',
                ],
            ]);
        }

        // Existing published logs: treat as approved so Guru visibility doesn't break after migrate
        $this->db->table('schedule_logs')
            ->where('published_at IS NOT NULL', null, false)
            ->where('approval_status', null)
            ->update([
                'approval_status' => 'approved',
                'approved_at'     => date('Y-m-d H:i:s'),
            ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('approval_status', 'schedule_logs')) {
            $this->forge->dropColumn('schedule_logs', ['approval_status', 'approved_at', 'approved_by', 'approval_note']);
        }
    }
}
