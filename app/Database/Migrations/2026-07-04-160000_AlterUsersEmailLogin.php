<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterUsersEmailLogin extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('users');

        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query(
                "UPDATE {$table} SET email = 'user_' || id || '@placeholder.local' WHERE email IS NULL OR email = ''"
            );
        } else {
            $this->db->query(
                "UPDATE {$table} SET email = CONCAT('user_', id, '@placeholder.local') WHERE email IS NULL OR email = ''"
            );
        }

        if ($this->hasUniqueOnColumn('users', 'nip')) {
            $this->dropUniqueOnColumn('users', 'nip');
        }

        $this->forge->modifyColumn('users', [
            'nip'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
        ]);

        if (! $this->hasUniqueOnColumn('users', 'email')) {
            $this->forge->addUniqueKey('email');
        }
    }

    public function down()
    {
        if ($this->hasUniqueOnColumn('users', 'email')) {
            $this->dropUniqueOnColumn('users', 'email');
        }

        $this->forge->modifyColumn('users', [
            'nip'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
            'email' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);

        if (! $this->hasUniqueOnColumn('users', 'nip')) {
            $this->forge->addUniqueKey('nip');
        }
    }

    private function hasUniqueOnColumn(string $table, string $column): bool
    {
        foreach ($this->db->getIndexData($table) as $index) {
            $fields = $index->fields ?? [];
            if (in_array($column, $fields, true) && ($index->type ?? '') === 'UNIQUE') {
                return true;
            }
        }

        return false;
    }

    private function dropUniqueOnColumn(string $table, string $column): void
    {
        foreach ($this->db->getIndexData($table) as $name => $index) {
            $fields = $index->fields ?? [];
            if (in_array($column, $fields, true) && ($index->type ?? '') === 'UNIQUE') {
                $this->forge->dropKey($table, is_string($name) ? $name : $column);

                return;
            }
        }
    }
}
