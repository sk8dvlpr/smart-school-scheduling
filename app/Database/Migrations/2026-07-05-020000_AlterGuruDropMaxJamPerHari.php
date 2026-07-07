<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterGuruDropMaxJamPerHari extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('max_jam_per_hari', 'guru')) {
            $this->forge->dropColumn('guru', 'max_jam_per_hari');
        }
    }

    public function down()
    {
        if (! $this->db->fieldExists('max_jam_per_hari', 'guru')) {
            $this->forge->addColumn('guru', [
                'max_jam_per_hari' => ['type' => 'INT', 'default' => 8, 'after' => 'user_id'],
            ]);
        }
    }
}
