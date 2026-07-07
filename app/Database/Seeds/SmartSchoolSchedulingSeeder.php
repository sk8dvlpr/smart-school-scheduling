<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Memuat data awal dari dump phpMyAdmin docs/database/smart_school_scheduling.sql.
 * Jalankan setelah migrate: php spark db:seed
 */
class SmartSchoolSchedulingSeeder extends Seeder
{
    private const SQL_PATH = ROOTPATH . 'docs/database/smart_school_scheduling.sql';

    /** @var list<string> Urutan FK — jangan ubah tanpa cek constraint */
    private const TABLES = [
        'hari',
        'tahun_ajaran',
        'jurusan',
        'ruangan',
        'mapel',
        'users',
        'guru',
        'guru_mapel',
        'guru_hari_blokir',
        'kelas',
        'kelas_mapel',
        'timeslot',
        'schedule_config',
        'jadwal',
        'schedule_logs',
    ];

    public function run()
    {
        if (! is_readable(self::SQL_PATH)) {
            throw new RuntimeException('SQL dump tidak ditemukan: ' . self::SQL_PATH);
        }

        $sql = file_get_contents(self::SQL_PATH);
        if ($sql === false) {
            throw new RuntimeException('Gagal membaca SQL dump.');
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS=0');

        foreach (array_reverse(self::TABLES) as $table) {
            $this->db->query("TRUNCATE TABLE `{$table}`");
        }

        foreach (self::TABLES as $table) {
            $statements = $this->extractInserts($sql, $table);
            foreach ($statements as $statement) {
                $this->db->query($statement);
            }

            $count = count($statements);
            echo $count > 0
                ? "Seeded {$table} ({$count} INSERT)\n"
                : "Skipped {$table} (kosong)\n";
        }

        foreach ($this->extractAutoIncrements($sql) as $table => $nextId) {
            if (! in_array($table, self::TABLES, true)) {
                continue;
            }
            $this->db->query("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS=1');

        echo "Data smart_school_scheduling.sql berhasil dimuat.\n";
    }

    /**
     * @return list<string>
     */
    private function extractInserts(string $sql, string $table): array
    {
        $statements = [];
        $needle     = 'INSERT INTO `' . $table . '`';
        $offset     = 0;

        while (($pos = strpos($sql, $needle, $offset)) !== false) {
            $end = $this->findStatementEnd($sql, $pos);
            if ($end === false) {
                break;
            }

            $statements[] = trim(substr($sql, $pos, $end - $pos + 1));
            $offset       = $end + 1;
        }

        return $statements;
    }

    private function findStatementEnd(string $sql, int $start): int|false
    {
        $len      = strlen($sql);
        $inString = false;
        $escape   = false;

        for ($i = $start; $i < $len; $i++) {
            $char = $sql[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inString) {
                if ($char === '\\') {
                    $escape = true;
                } elseif ($char === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
                continue;
            }

            if ($char === ';') {
                return $i;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    private function extractAutoIncrements(string $sql): array
    {
        $map = [];

        if (preg_match_all(
            '/AUTO_INCREMENT for table `(\w+)`\s+ALTER TABLE `\1`\s+MODIFY[^;]+AUTO_INCREMENT=(\d+);/s',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                if ($match[1] !== 'migrations') {
                    $map[$match[1]] = (int) $match[2];
                }
            }
        }

        return $map;
    }
}
