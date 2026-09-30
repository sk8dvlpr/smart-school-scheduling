<?php

namespace App\Database\Seeds;

use App\Libraries\InstallerService;
use CodeIgniter\Database\Seeder;

/**
 * Seed default untuk instalasi manual: hanya hari + timeslot dasar.
 * Data sekolah (guru, kelas, mapel, dll.) diisi lewat UI / wizard /install.
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        (new InstallerService())->seedEmptyTemplate();
    }
}
