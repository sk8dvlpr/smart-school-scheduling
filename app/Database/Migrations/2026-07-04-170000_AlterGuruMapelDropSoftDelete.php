<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterGuruMapelDropSoftDelete extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('guru_mapel');

        if (! $this->db->fieldExists('deleted_at', 'guru_mapel')) {
            return;
        }

        $this->db->query("DELETE FROM {$table} WHERE deleted_at IS NOT NULL");
        $this->forge->dropColumn('guru_mapel', 'deleted_at');
    }

    public function down()
    {
        if ($this->db->fieldExists('deleted_at', 'guru_mapel')) {
            return;
        }

        $this->forge->addColumn('guru_mapel', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at'],
        ]);
    }
}
