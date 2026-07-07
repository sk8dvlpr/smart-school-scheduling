<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddScheduleHistoryAndPublish extends Migration
{
    public function up()
    {
        // schedule_logs extensions (idempotent partial re-run)
        if (! $this->db->fieldExists('published_at', 'schedule_logs')) {
            $this->forge->addColumn('schedule_logs', [
                'published_at'           => ['type' => 'DATETIME', 'null' => true, 'after' => 'completed_at'],
                'published_by'           => ['type' => 'INT', 'null' => true, 'after' => 'published_at'],
                'label'                  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'published_by'],
                'unplaced_report'        => ['type' => 'TEXT', 'null' => true, 'after' => 'label'],
                'parent_schedule_log_id' => ['type' => 'INT', 'null' => true, 'after' => 'unplaced_report'],
                'generate_mode'          => ['type' => 'ENUM', 'constraint' => ['fresh', 'history_repair'], 'default' => 'fresh', 'after' => 'parent_schedule_log_id'],
                'repair_report'          => ['type' => 'TEXT', 'null' => true, 'after' => 'generate_mode'],
            ]);
        }
        $this->ensureForeignKey('schedule_logs', 'parent_schedule_log_id', 'fk_schedule_logs_parent', 'schedule_logs', 'id', 'SET NULL', 'CASCADE');
        $this->ensureForeignKey('schedule_logs', 'published_by', 'fk_schedule_logs_published_by', 'users', 'id', 'SET NULL', 'CASCADE');

        if (! $this->db->fieldExists('published_schedule_log_id', 'tahun_ajaran')) {
            $this->forge->addColumn('tahun_ajaran', [
                'published_schedule_log_id' => ['type' => 'INT', 'null' => true, 'after' => 'is_active'],
            ]);
        }
        $this->ensureForeignKey('tahun_ajaran', 'published_schedule_log_id', 'fk_ta_published_log', 'schedule_logs', 'id', 'SET NULL', 'CASCADE');

        if (! $this->db->fieldExists('schedule_log_id', 'jadwal')) {
            $this->forge->addColumn('jadwal', [
                'schedule_log_id' => ['type' => 'INT', 'null' => true, 'after' => 'tahun_ajaran_id'],
                'is_manual'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'blok_group'],
            ]);
        } elseif (! $this->db->fieldExists('is_manual', 'jadwal')) {
            $this->forge->addColumn('jadwal', [
                'is_manual' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'blok_group'],
            ]);
        }

        $this->migrateExistingJadwal();

        if ($this->indexAllowsNull('jadwal', 'schedule_log_id')) {
            $this->db->query('ALTER TABLE jadwal MODIFY schedule_log_id INT NOT NULL');
        }
        $this->ensureForeignKey('jadwal', 'schedule_log_id', 'fk_jadwal_schedule_log', 'schedule_logs', 'id', 'CASCADE', 'CASCADE');

        // Replace unique keys — drop all jadwal FKs except schedule_log, swap indexes, restore FKs
        $allFks = $this->db->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jadwal'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME != 'fk_jadwal_schedule_log'"
        )->getResultArray();

        foreach ($allFks as $fk) {
            $name = $fk['CONSTRAINT_NAME'];
            $this->db->query("ALTER TABLE jadwal DROP FOREIGN KEY `{$name}`");
        }

        $this->ensureIndex('jadwal', 'idx_jadwal_kelas_fk', 'kelas_id');
        $this->ensureIndex('jadwal', 'idx_jadwal_guru_fk', 'guru_id');
        $this->ensureIndex('jadwal', 'idx_jadwal_ruangan_fk', 'ruangan_id');
        $this->ensureIndex('jadwal', 'idx_jadwal_hari_fk', 'hari_id');
        $this->ensureIndex('jadwal', 'idx_jadwal_timeslot_fk', 'timeslot_id');

        if ($this->indexExists('jadwal', 'jadwal_kelas_conflict')) {
            $this->db->query('ALTER TABLE jadwal DROP INDEX jadwal_kelas_conflict');
        }
        if ($this->indexExists('jadwal', 'jadwal_guru_conflict')) {
            $this->db->query('ALTER TABLE jadwal DROP INDEX jadwal_guru_conflict');
        }
        if ($this->indexExists('jadwal', 'jadwal_ruangan_conflict')) {
            $this->db->query('ALTER TABLE jadwal DROP INDEX jadwal_ruangan_conflict');
        }

        if (! $this->indexExists('jadwal', 'jadwal_kelas_conflict')) {
            $this->db->query('ALTER TABLE jadwal ADD UNIQUE KEY jadwal_kelas_conflict (schedule_log_id, hari_id, timeslot_id, kelas_id)');
        }
        if (! $this->indexExists('jadwal', 'jadwal_guru_conflict')) {
            $this->db->query('ALTER TABLE jadwal ADD UNIQUE KEY jadwal_guru_conflict (schedule_log_id, hari_id, timeslot_id, guru_id)');
        }
        if (! $this->indexExists('jadwal', 'jadwal_ruangan_conflict')) {
            $this->db->query('ALTER TABLE jadwal ADD UNIQUE KEY jadwal_ruangan_conflict (schedule_log_id, hari_id, timeslot_id, ruangan_id)');
        }
        $this->ensureIndex('jadwal', 'idx_jadwal_schedule_log', 'schedule_log_id');

        $this->ensureForeignKey('jadwal', 'tahun_ajaran_id', 'jadwal_tahun_ajaran_id_foreign', 'tahun_ajaran', 'id', 'CASCADE', 'CASCADE');
        $this->ensureForeignKey('jadwal', 'kelas_mapel_id', 'jadwal_kelas_mapel_id_foreign', 'kelas_mapel', 'id', 'CASCADE', 'CASCADE');
        $this->ensureForeignKey('jadwal', 'hari_id', 'jadwal_hari_id_foreign', 'hari', 'id', 'CASCADE', 'CASCADE');
        $this->ensureForeignKey('jadwal', 'timeslot_id', 'jadwal_timeslot_id_foreign', 'timeslot', 'id', 'CASCADE', 'CASCADE');
        $this->ensureForeignKey('jadwal', 'kelas_id', 'jadwal_kelas_id_foreign', 'kelas', 'id', 'CASCADE', 'CASCADE');
        $this->ensureForeignKey('jadwal', 'guru_id', 'jadwal_guru_id_foreign', 'guru', 'id', 'CASCADE', 'CASCADE');
        $this->ensureForeignKey('jadwal', 'mapel_id', 'jadwal_mapel_id_foreign', 'mapel', 'id', 'CASCADE', 'CASCADE');
        $this->ensureForeignKey('jadwal', 'ruangan_id', 'jadwal_ruangan_id_foreign', 'ruangan', 'id', 'CASCADE', 'CASCADE');

        if (! $this->db->tableExists('guru_preferensi')) {
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
        }
    }

    public function down()
    {
        $this->forge->dropTable('guru_preferensi', true);

        $this->db->query('ALTER TABLE jadwal DROP INDEX jadwal_kelas_conflict');
        $this->db->query('ALTER TABLE jadwal DROP INDEX jadwal_guru_conflict');
        $this->db->query('ALTER TABLE jadwal DROP INDEX jadwal_ruangan_conflict');
        $this->forge->addUniqueKey(['hari_id', 'timeslot_id', 'kelas_id', 'tahun_ajaran_id'], 'jadwal_kelas_conflict');
        $this->forge->addUniqueKey(['hari_id', 'timeslot_id', 'guru_id', 'tahun_ajaran_id'], 'jadwal_guru_conflict');
        $this->forge->addUniqueKey(['hari_id', 'timeslot_id', 'ruangan_id', 'tahun_ajaran_id'], 'jadwal_ruangan_conflict');

        $this->forge->dropForeignKey('jadwal', 'fk_jadwal_schedule_log');
        $this->forge->dropColumn('jadwal', ['schedule_log_id', 'is_manual']);

        $this->forge->dropForeignKey('tahun_ajaran', 'fk_ta_published_log');
        $this->forge->dropColumn('tahun_ajaran', 'published_schedule_log_id');

        $this->forge->dropForeignKey('schedule_logs', 'fk_schedule_logs_parent');
        $this->forge->dropForeignKey('schedule_logs', 'fk_schedule_logs_published_by');
        $this->forge->dropColumn('schedule_logs', [
            'published_at', 'published_by', 'label', 'unplaced_report',
            'parent_schedule_log_id', 'generate_mode', 'repair_report',
        ]);
    }

    private function migrateExistingJadwal(): void
    {
        $taRows = $this->db->table('tahun_ajaran')->get()->getResultArray();
        foreach ($taRows as $ta) {
            $taId = (int) $ta['id'];
            $count = (int) $this->db->table('jadwal')->where('tahun_ajaran_id', $taId)->countAllResults();
            if ($count === 0) {
                continue;
            }

            $latestLog = $this->db->table('schedule_logs')
                ->where('tahun_ajaran_id', $taId)
                ->whereIn('status', ['completed', 'partial'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();

            if ($latestLog) {
                $logId = (int) $latestLog['id'];
            } else {
                $this->db->table('schedule_logs')->insert([
                    'tahun_ajaran_id' => $taId,
                    'status'          => 'completed',
                    'error_message'   => 'Migrated from legacy jadwal',
                    'generated_by'    => 1,
                    'started_at'      => date('Y-m-d H:i:s'),
                    'completed_at'    => date('Y-m-d H:i:s'),
                    'generate_mode'   => 'fresh',
                ]);
                $logId = (int) $this->db->insertID();
            }

            $this->db->table('jadwal')
                ->where('tahun_ajaran_id', $taId)
                ->where('schedule_log_id IS NULL', null, false)
                ->update(['schedule_log_id' => $logId]);

            $this->db->table('tahun_ajaran')
                ->where('id', $taId)
                ->update(['published_schedule_log_id' => $logId]);
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($indexName))->getResultArray();

        return $rows !== [];
    }

    private function ensureIndex(string $table, string $indexName, string $column): void
    {
        if (! $this->indexExists($table, $indexName)) {
            $this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        }
    }

    private function indexAllowsNull(string $table, string $column): bool
    {
        $row = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($column))->getRowArray();

        return ($row['Null'] ?? '') === 'YES';
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $fkName,
        string $refTable,
        string $refColumn,
        string $onDelete,
        string $onUpdate
    ): void {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }
        $exists = $this->db->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $fkName]
        )->getRowArray();

        if ($exists) {
            return;
        }

        $this->db->query(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$column}`)
             REFERENCES `{$refTable}` (`{$refColumn}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }
}
