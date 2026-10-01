<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Baseline skema S3 v3.x — struktur kosong tanpa seed data.
 * Master data diisi lewat UI / wizard instalasi (kecuali daftar hari minimal).
 */
class CreateInitialSchema extends Migration
{
    public function up()
    {
        // 1. hari
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'auto_increment' => true],
            'nama'   => ['type' => 'VARCHAR', 'constraint' => 10],
            'kode'   => ['type' => 'VARCHAR', 'constraint' => 3],
            'urutan' => ['type' => 'INT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->createTable('hari');

        // 2. tahun_ajaran (FK published_schedule_log_id ditambah setelah schedule_logs)
        $this->forge->addField([
            'id'                        => ['type' => 'INT', 'auto_increment' => true],
            'nama'                      => ['type' => 'VARCHAR', 'constraint' => 50],
            'semester'                  => ['type' => 'ENUM', 'constraint' => ['ganjil', 'genap']],
            'is_active'                 => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'published_schedule_log_id' => ['type' => 'INT', 'null' => true],
            'tanggal_mulai'             => ['type' => 'DATE'],
            'tanggal_selesai'           => ['type' => 'DATE'],
            'created_at'                => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'                => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tahun_ajaran');

        // 3. jurusan
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'kode'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->createTable('jurusan');

        // 4. users
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'auto_increment' => true],
            'nip'                  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nama'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 100],
            'no_telp'              => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'password'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'                 => ['type' => 'ENUM', 'constraint' => ['guru', 'kurikulum', 'kepala_sekolah']],
            'is_admin'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users');

        // 5. ruangan
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'kode'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'tipe'       => ['type' => 'ENUM', 'constraint' => ['kelas', 'lab']],
            'kapasitas'  => ['type' => 'INT', 'default' => 40],
            'jurusan_id' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ruangan');

        // 6. guru
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'user_id'    => ['type' => 'INT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('guru');

        // 7. mapel
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'auto_increment' => true],
            'kode'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'tipe'           => ['type' => 'ENUM', 'constraint' => ['umum', 'kejuruan']],
            'jurusan_id'     => ['type' => 'INT', 'null' => true],
            'warna'          => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#0B1F3A'],
            'bobot_kognitif' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 5],
            'jam_per_minggu' => ['type' => 'INT', 'default' => 2],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('mapel');

        // 8. kelas
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'nama'            => ['type' => 'VARCHAR', 'constraint' => 20],
            'tingkat'         => ['type' => 'ENUM', 'constraint' => ['X', 'XI', 'XII']],
            'jurusan_id'      => ['type' => 'INT'],
            'ruangan_id'      => ['type' => 'INT'],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['nama', 'tahun_ajaran_id']);
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ruangan_id', 'ruangan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('kelas');

        // 9. timeslot
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'hari_id'       => ['type' => 'INT'],
            'jam_ke'        => ['type' => 'INT'],
            'waktu_mulai'   => ['type' => 'TIME'],
            'waktu_selesai' => ['type' => 'TIME'],
            'tipe'          => ['type' => 'ENUM', 'constraint' => ['jp', 'istirahat', 'kegiatan_khusus']],
            'keterangan'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('hari_id', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('timeslot');

        // 10. guru_mapel
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'auto_increment' => true],
            'guru_id'           => ['type' => 'INT'],
            'mapel_id'          => ['type' => 'INT'],
            'max_jam_per_minggu'=> ['type' => 'INT'],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['guru_id', 'mapel_id']);
        $this->forge->addForeignKey('guru_id', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('guru_mapel');

        // 11. guru_hari_blokir
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'guru_id'    => ['type' => 'INT'],
            'hari_id'    => ['type' => 'INT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['guru_id', 'hari_id']);
        $this->forge->addForeignKey('guru_id', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('guru_hari_blokir');

        // 12. kelas_mapel
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'kelas_id'        => ['type' => 'INT'],
            'mapel_id'        => ['type' => 'INT'],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'jam_per_minggu'  => ['type' => 'INT'],
            'butuh_lab'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'lab_id'          => ['type' => 'INT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['kelas_id', 'mapel_id', 'tahun_ajaran_id']);
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lab_id', 'ruangan', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('kelas_mapel');

        // 13. schedule_logs
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id'        => ['type' => 'INT'],
            'status'                 => ['type' => 'ENUM', 'constraint' => ['running', 'completed', 'failed', 'partial']],
            'fitness_score'          => ['type' => 'DECIMAL', 'constraint' => '5,4', 'null' => true],
            'generations_run'        => ['type' => 'INT', 'null' => true],
            'total_conflicts'        => ['type' => 'INT', 'null' => true],
            'execution_time'         => ['type' => 'INT', 'null' => true],
            'error_message'          => ['type' => 'TEXT', 'null' => true],
            'result_report'          => ['type' => 'TEXT', 'null' => true],
            'generated_by'           => ['type' => 'INT'],
            'started_at'             => ['type' => 'DATETIME'],
            'completed_at'           => ['type' => 'DATETIME', 'null' => true],
            'published_at'           => ['type' => 'DATETIME', 'null' => true],
            'published_by'           => ['type' => 'INT', 'null' => true],
            'approval_status'        => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected'], 'null' => true],
            'approved_at'            => ['type' => 'DATETIME', 'null' => true],
            'approved_by'            => ['type' => 'INT', 'null' => true],
            'approval_note'          => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'label'                  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'unplaced_report'        => ['type' => 'TEXT', 'null' => true],
            'parent_schedule_log_id' => ['type' => 'INT', 'null' => true],
            'generate_mode'          => ['type' => 'ENUM', 'constraint' => ['fresh', 'history_repair'], 'default' => 'fresh'],
            'repair_report'          => ['type' => 'TEXT', 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('generated_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('published_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('parent_schedule_log_id', 'schedule_logs', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('schedule_logs');

        // FK tahun_ajaran.published_schedule_log_id (circular — setelah schedule_logs ada)
        $this->db->query(
            'ALTER TABLE tahun_ajaran
             ADD CONSTRAINT fk_ta_published_log
             FOREIGN KEY (published_schedule_log_id) REFERENCES schedule_logs(id)
             ON UPDATE CASCADE ON DELETE SET NULL'
        );

        // 14. jadwal
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'schedule_log_id' => ['type' => 'INT'],
            'kelas_mapel_id'  => ['type' => 'INT'],
            'hari_id'         => ['type' => 'INT'],
            'timeslot_id'     => ['type' => 'INT'],
            'kelas_id'        => ['type' => 'INT'],
            'guru_id'         => ['type' => 'INT'],
            'mapel_id'        => ['type' => 'INT'],
            'ruangan_id'      => ['type' => 'INT'],
            'blok_group'      => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'is_manual'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['schedule_log_id', 'hari_id', 'timeslot_id', 'kelas_id'], 'jadwal_kelas_conflict');
        $this->forge->addUniqueKey(['schedule_log_id', 'hari_id', 'timeslot_id', 'guru_id'], 'jadwal_guru_conflict');
        $this->forge->addUniqueKey(['schedule_log_id', 'hari_id', 'timeslot_id', 'ruangan_id'], 'jadwal_ruangan_conflict');
        $this->forge->addKey(['tahun_ajaran_id', 'kelas_id'], false, false, 'idx_jadwal_ta_kelas');
        $this->forge->addKey(['tahun_ajaran_id', 'guru_id'], false, false, 'idx_jadwal_ta_guru');
        $this->forge->addKey(['tahun_ajaran_id', 'hari_id', 'timeslot_id'], false, false, 'idx_jadwal_ta_hari_slot');
        $this->forge->addKey('kelas_id', false, false, 'idx_jadwal_kelas_fk');
        $this->forge->addKey('guru_id', false, false, 'idx_jadwal_guru_fk');
        $this->forge->addKey('ruangan_id', false, false, 'idx_jadwal_ruangan_fk');
        $this->forge->addKey('hari_id', false, false, 'idx_jadwal_hari_fk');
        $this->forge->addKey('timeslot_id', false, false, 'idx_jadwal_timeslot_fk');
        $this->forge->addKey('schedule_log_id', false, false, 'idx_jadwal_schedule_log');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('schedule_log_id', 'schedule_logs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('kelas_mapel_id', 'kelas_mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('timeslot_id', 'timeslot', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mapel', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ruangan_id', 'ruangan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('jadwal');

        // 15. schedule_config
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id' => ['type' => 'INT'],
            'param_key'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'param_value'     => ['type' => 'TEXT'],
            'description'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('schedule_config');

        // 16. guru_preferensi
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'guru_id'     => ['type' => 'INT'],
            'hari_id'     => ['type' => 'INT', 'null' => true],
            'timeslot_id' => ['type' => 'INT', 'null' => true],
            'tipe'        => ['type' => 'ENUM', 'constraint' => ['prefer', 'avoid'], 'default' => 'prefer'],
            'bobot'       => ['type' => 'TINYINT', 'constraint' => 3, 'default' => 5],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('guru_id', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('timeslot_id', 'timeslot', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('guru_preferensi');

        // 17. schedule_jobs
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'auto_increment' => true],
            'tahun_ajaran_id'  => ['type' => 'INT'],
            'user_id'          => ['type' => 'INT'],
            'status'           => [
                'type'       => 'ENUM',
                'constraint' => ['queued', 'running', 'completed', 'failed', 'cancelled'],
                'default'    => 'queued',
            ],
            'progress'         => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'generation'       => ['type' => 'INT', 'null' => true],
            'best_fitness'     => ['type' => 'FLOAT', 'null' => true],
            'parent_log_id'    => ['type' => 'INT', 'null' => true],
            'generate_mode'    => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'fresh'],
            'schedule_log_id'  => ['type' => 'INT', 'null' => true],
            'error_message'    => ['type' => 'TEXT', 'null' => true],
            'cancel_requested' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'started_at'       => ['type' => 'DATETIME', 'null' => true],
            'finished_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'tahun_ajaran_id']);
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('parent_log_id', 'schedule_logs', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('schedule_log_id', 'schedule_logs', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('schedule_jobs');

        // 18. app_settings (kosong — diisi wizard instalasi)
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'nama_sekolah' => ['type' => 'VARCHAR', 'constraint' => 150],
            'logo_path'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('app_settings');

        // 19. settings (kosong — diisi wizard / SettingsService)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'group'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 128],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'string'],
            'is_secret'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['group', 'key']);
        $this->forge->createTable('settings');
    }

    public function down()
    {
        $tables = [
            'settings', 'app_settings', 'schedule_jobs', 'guru_preferensi', 'schedule_config',
            'jadwal', 'schedule_logs', 'kelas_mapel', 'guru_hari_blokir', 'guru_mapel',
            'timeslot', 'kelas', 'mapel', 'guru', 'ruangan', 'users', 'jurusan', 'tahun_ajaran', 'hari',
        ];

        // Drop FK tahun_ajaran → schedule_logs before dropping schedule_logs
        try {
            $this->db->query('ALTER TABLE tahun_ajaran DROP FOREIGN KEY fk_ta_published_log');
        } catch (\Throwable $e) {
            // ignore if already gone
        }

        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
