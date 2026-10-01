<?php

namespace App\Database\Seeds;

use App\Libraries\InstallerService;
use CodeIgniter\Database\Seeder;

/**
 * Seed default untuk instalasi manual: hanya daftar hari.
 * Data sekolah (timeslot, ruangan, guru, kelas, mapel, dll.) diisi lewat UI /install.
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        (new InstallerService())->seedEmptyTemplate();
    }
}
