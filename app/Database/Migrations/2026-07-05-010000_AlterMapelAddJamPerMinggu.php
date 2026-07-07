<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterMapelAddJamPerMinggu extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('jam_per_minggu', 'mapel')) {
            return;
        }

        $this->forge->addColumn('mapel', [
            'jam_per_minggu' => ['type' => 'INT', 'default' => 2, 'after' => 'warna'],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('jam_per_minggu', 'mapel')) {
            $this->forge->dropColumn('mapel', 'jam_per_minggu');
        }
    }
}
