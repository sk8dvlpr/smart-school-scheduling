<?php

namespace App\Database\Migrations;

use App\Libraries\LabAssignment;
use CodeIgniter\Database\Migration;

class FixDuplicateSmkLabs extends Migration
{
    /** @var array<int, int> lab duplikat → lab kanonik */
    private array $reassign = [
        36 => 25, // LAB-TKJ → LAB-TKJ-1
        38 => 26, // LAB-TKR → LAB-TKR-1
        39 => 27, // LAB-AK → LAB-AX-1
        40 => 35, // LAB-AK-2 → LAB-AX-2
    ];

    public function up()
    {
        foreach ($this->reassign as $from => $to) {
            $exists = $this->db->table('ruangan')->where('id', $from)->countAllResults();
            if ($exists === 0) {
                continue;
            }
            $this->db->table('kelas_mapel')->where('lab_id', $from)->update(['lab_id' => $to]);
            $this->db->table('jadwal')->where('ruangan_id', $from)->update(['ruangan_id' => $to]);
            $this->db->table('ruangan')->where('id', $from)->delete();
        }

        $this->splitKelasMapelLabs();
    }

    public function down()
    {
        // ponytail: tidak restore duplikat — data sudah dipindah ke lab kanonik
    }

    private function splitKelasMapelLabs(): void
    {
        $labsByJurusan = [];
        foreach ($this->db->table('ruangan')->where('tipe', 'lab')->orderBy('kode', 'ASC')->get()->getResultArray() as $lab) {
            $labsByJurusan[(int) $lab['jurusan_id']][] = (int) $lab['id'];
        }

        $ta = $this->db->table('tahun_ajaran')->where('is_active', 1)->get()->getRow();
        if (! $ta) {
            return;
        }

        $rows = $this->db->table('kelas_mapel km')
            ->select('km.id, k.nama AS kelas, k.jurusan_id')
            ->join('kelas k', 'k.id = km.kelas_id')
            ->where('km.tahun_ajaran_id', $ta->id)
            ->where('km.butuh_lab', 1)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $labs = $labsByJurusan[(int) $row['jurusan_id']] ?? [];
            $labId = LabAssignment::pickLabId($row['kelas'], $labs);
            if ($labId === null) {
                continue;
            }
            $this->db->table('kelas_mapel')->where('id', $row['id'])->update(['lab_id' => $labId]);
        }
    }
}
