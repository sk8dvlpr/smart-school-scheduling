<?php

namespace App\Database\Migrations;

use App\Libraries\LabAssignment;
use CodeIgniter\Database\Migration;

class AddSmkLabsAndSplitKelasMapel extends Migration
{
    /** @var list<array{kode: string, nama: string, jurusan: string}> */
    private array $labs = [
        ['kode' => 'LAB-TKJ-1', 'nama' => 'Lab Komputer TKJ 1',    'jurusan' => 'TKJ'],
        ['kode' => 'LAB-TKJ-2', 'nama' => 'Lab Komputer TKJ 2',    'jurusan' => 'TKJ'],
        ['kode' => 'LAB-TKJ-3', 'nama' => 'Lab Komputer TKJ 3',    'jurusan' => 'TKJ'],
        ['kode' => 'LAB-TKJ-4', 'nama' => 'Lab Komputer TKJ 4',    'jurusan' => 'TKJ'],
        ['kode' => 'LAB-TKJ-5', 'nama' => 'Lab Komputer TKJ 5',    'jurusan' => 'TKJ'],
        ['kode' => 'LAB-TKJ-6', 'nama' => 'Lab Komputer TKJ 6',    'jurusan' => 'TKJ'],
        ['kode' => 'LAB-TKR-1', 'nama' => 'Lab Otomotif TKR 1',    'jurusan' => 'TKR'],
        ['kode' => 'LAB-TKR-2', 'nama' => 'Lab Otomotif TKR 2',    'jurusan' => 'TKR'],
        ['kode' => 'LAB-TKR-3', 'nama' => 'Lab Otomotif TKR 3',    'jurusan' => 'TKR'],
        ['kode' => 'LAB-TKR-4', 'nama' => 'Lab Otomotif TKR 4',    'jurusan' => 'TKR'],
        ['kode' => 'LAB-AX-1',  'nama' => 'Lab Axiio 1',           'jurusan' => 'AX'],
        ['kode' => 'LAB-AX-2',  'nama' => 'Lab Axiio 2',           'jurusan' => 'AX'],
    ];

    public function up()
    {
        $now = date('Y-m-d H:i:s');
        $jurusanMap = [];
        foreach ($this->db->table('jurusan')->get()->getResultArray() as $j) {
            $jurusanMap[$j['kode']] = (int) $j['id'];
        }

        foreach ($this->labs as $lab) {
            $exists = $this->db->table('ruangan')->where('kode', $lab['kode'])->countAllResults();
            if ($exists > 0) {
                continue;
            }
            $jKode = $lab['jurusan'] === 'AX' ? 'AX' : $lab['jurusan'];
            $this->db->table('ruangan')->insert([
                'kode'       => $lab['kode'],
                'nama'       => $lab['nama'],
                'tipe'       => 'lab',
                'kapasitas'  => 36,
                'jurusan_id' => $jurusanMap[$jKode] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->splitKelasMapelLabs();
    }

    public function down()
    {
        $kodes = array_column($this->labs, 'kode');
        $this->db->table('ruangan')->whereIn('kode', $kodes)->delete();
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
