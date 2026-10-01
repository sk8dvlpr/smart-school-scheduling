<?php

namespace App\Database\Seeds;

use App\Libraries\InstallerService;
use CodeIgniter\Database\Seeder;

/**
 * Template kosong: hanya daftar hari Senin–Sabtu.
 * Timeslot, ruangan, dan master data lain diisi lewat UI / wizard.
 */
class EmptyTemplateSeeder extends Seeder
{
    public function run()
    {
        (new InstallerService())->seedEmptyTemplate();
    }
}
