<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\LaporanGuruJamExporter;

/**
 * @internal
 */
final class JadwalLaporanTest extends CIUnitTestCase
{
    public function testGroupByGuruSumsJpPerGuru(): void
    {
        $rows = [
            ['guru_id' => 1, 'nip' => '001', 'nama_guru' => 'Budi', 'mapel_kode' => 'MTK', 'mapel_nama' => 'Matematika', 'total_jp' => 12],
            ['guru_id' => 1, 'nip' => '001', 'nama_guru' => 'Budi', 'mapel_kode' => 'FIS', 'mapel_nama' => 'Fisika', 'total_jp' => 8],
            ['guru_id' => 2, 'nip' => '002', 'nama_guru' => 'Ani', 'mapel_kode' => 'BIN', 'mapel_nama' => 'Bahasa Indonesia', 'total_jp' => 10],
        ];

        $grouped = LaporanGuruJamExporter::groupByGuru($rows);

        $this->assertCount(2, $grouped);
        $this->assertSame(20, $grouped[0]['subtotal']);
        $this->assertSame(10, $grouped[1]['subtotal']);
        $this->assertSame(30, $grouped[0]['subtotal'] + $grouped[1]['subtotal']);
    }

    public function testGroupByGuruPreservesMapelRows(): void
    {
        $rows = [
            ['guru_id' => 5, 'nip' => '005', 'nama_guru' => 'Citra', 'mapel_kode' => 'PJOK', 'mapel_nama' => 'PJOK', 'total_jp' => 4],
        ];

        $grouped = LaporanGuruJamExporter::groupByGuru($rows);

        $this->assertCount(1, $grouped[0]['rows']);
        $this->assertSame('PJOK', $grouped[0]['rows'][0]['mapel_kode']);
        $this->assertSame(4, $grouped[0]['subtotal']);
    }

    public function testGroupByGuruEmptyInput(): void
    {
        $this->assertSame([], LaporanGuruJamExporter::groupByGuru([]));
    }

    public function testOneJadwalRowEqualsOneJp(): void
    {
        // ponytail: documents business rule — aggregation uses COUNT rows, not guru_mapel cap
        $singleRow = [['guru_id' => 1, 'nip' => '001', 'nama_guru' => 'X', 'total_jp' => 1]];
        $grouped   = LaporanGuruJamExporter::groupByGuru($singleRow);

        $this->assertSame(1, $grouped[0]['subtotal']);
    }
}
