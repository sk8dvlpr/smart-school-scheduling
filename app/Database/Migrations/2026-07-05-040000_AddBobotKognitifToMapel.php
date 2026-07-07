<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menambah kolom bobot_kognitif (skala 1-10) untuk SC-4/SC-5:
 * mapel berat (kognitif tinggi) diprioritaskan di jam awal, mapel ringan di jam akhir.
 */
class AddBobotKognitifToMapel extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('bobot_kognitif', 'mapel')) {
            return;
        }

        $this->forge->addColumn('mapel', [
            'bobot_kognitif' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
                'default'    => 5,
                'after'      => 'warna',
                'comment'    => 'Beban kognitif 1-10 (tinggi = butuh konsentrasi, diutamakan pagi)',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('bobot_kognitif', 'mapel')) {
            $this->forge->dropColumn('mapel', 'bobot_kognitif');
        }
    }
}
