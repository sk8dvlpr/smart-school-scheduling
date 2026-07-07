<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterKelasMapelDropSoftDelete extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('kelas_mapel');

        if (! $this->db->fieldExists('deleted_at', 'kelas_mapel')) {
            return;
        }

        $this->db->query("DELETE FROM {$table} WHERE deleted_at IS NOT NULL");
        $this->forge->dropColumn('kelas_mapel', 'deleted_at');
    }

    public function down()
    {
        if ($this->db->fieldExists('deleted_at', 'kelas_mapel')) {
            return;
        }

        $this->forge->addColumn('kelas_mapel', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at'],
        ]);
    }
}
