<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettingsTable extends Migration
{
    public function up()
    {
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

        if ($this->db->tableExists('app_settings')) {
            $row = $this->db->table('app_settings')->orderBy('id', 'ASC')->get()->getRowArray();
            if ($row) {
                $now = date('Y-m-d H:i:s');
                $pairs = [
                    ['group' => 'school', 'key' => 'name', 'value' => (string) ($row['nama_sekolah'] ?? ''), 'type' => 'string'],
                    ['group' => 'school', 'key' => 'logo_path', 'value' => $row['logo_path'] ?? null, 'type' => 'string'],
                ];
                foreach ($pairs as $p) {
                    $this->db->table('settings')->insert([
                        'group'      => $p['group'],
                        'key'        => $p['key'],
                        'value'      => $p['value'],
                        'type'       => $p['type'],
                        'is_secret'  => 0,
                        'updated_by' => null,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('settings');
    }
}
