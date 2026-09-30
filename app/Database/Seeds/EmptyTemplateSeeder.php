<?php

namespace App\Database\Seeds;

use App\Libraries\InstallerService;
use CodeIgniter\Database\Seeder;

/**
 * Template kosong: hari Senin–Sabtu + timeslot dasar (bukan data sekolah spesifik).
 */
class EmptyTemplateSeeder extends Seeder
{
    public function run()
    {
        (new InstallerService())->seedEmptyTemplate();
    }
}
