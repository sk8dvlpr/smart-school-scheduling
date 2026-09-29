<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NeutralizeAppSettingsDefaults extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('app_settings')) {
            return;
        }

        $this->db->table('app_settings')
            ->where('nama_sekolah', 'SMK Tunas Teknologi')
            ->update(['nama_sekolah' => 'Smart School Scheduling']);

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                "ALTER TABLE `app_settings` MODIFY `nama_sekolah` VARCHAR(150) NOT NULL DEFAULT 'Smart School Scheduling'"
            );
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('app_settings')) {
            return;
        }

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                "ALTER TABLE `app_settings` MODIFY `nama_sekolah` VARCHAR(150) NOT NULL DEFAULT 'SMK Tunas Teknologi'"
            );
        }
    }
}
