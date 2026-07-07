<?php

use App\Libraries\JadwalPlacementValidator;
use App\Libraries\SchedulingContext;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class JadwalPlacementValidatorTest extends CIUnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function baseFixture(): array
    {
        $timeslotsByHari = [
            1 => [
                ['id' => 11, 'jam_ke' => 1, 'tipe' => 'jp'],
                ['id' => 12, 'jam_ke' => 2, 'tipe' => 'jp'],
                ['id' => 13, 'jam_ke' => 0, 'tipe' => 'istirahat'],
                ['id' => 14, 'jam_ke' => 3, 'tipe' => 'jp'],
            ],
            2 => [
                ['id' => 21, 'jam_ke' => 1, 'tipe' => 'jp'],
                ['id' => 22, 'jam_ke' => 2, 'tipe' => 'jp'],
            ],
        ];
        $jpSlots = SchedulingContext::buildJpSlotsByHari($timeslotsByHari);

        return [
            'tahun_ajaran_id' => 1,
            'jp_slots_by_hari' => $jpSlots,
            'guru_pool' => [
                1 => [['guru_id' => 101, 'max_jam' => 10, 'mapel_tipe' => 'umum', 'mapel_jurusan_id' => null]],
                2 => [['guru_id' => 102, 'max_jam' => 10, 'mapel_tipe' => 'umum', 'mapel_jurusan_id' => null]],
            ],
            'guru_blokir' => [],
            'homeroom_map' => [10 => 201],
            'kelas_by_id' => [
                10 => ['id' => 10, 'jurusan_id' => 1, 'ruangan_id' => 201],
            ],
            'kelas_mapel_by_id' => [
                100 => ['id' => 100, 'kelas_id' => 10, 'mapel_id' => 1, 'jam_per_minggu' => 2, 'butuh_lab' => 0, 'lab_id' => null],
                200 => ['id' => 200, 'kelas_id' => 10, 'mapel_id' => 2, 'jam_per_minggu' => 1, 'butuh_lab' => 0, 'lab_id' => null],
            ],
            'kelas_mapel_by_kelas' => [
                10 => [
                    ['id' => 100, 'kelas_id' => 10, 'mapel_id' => 1, 'jam_per_minggu' => 2, 'butuh_lab' => 0, 'lab_id' => null],
                    ['id' => 200, 'kelas_id' => 10, 'mapel_id' => 2, 'jam_per_minggu' => 1, 'butuh_lab' => 0, 'lab_id' => null],
                ],
            ],
            'mapel_meta' => [
                1 => ['id' => 1, 'kode' => 'MTK', 'nama' => 'Matematika', 'tipe' => 'umum'],
                2 => ['id' => 2, 'kode' => 'BIN', 'nama' => 'Bahasa Indonesia', 'tipe' => 'umum'],
            ],
            'guru_names' => [101 => 'Guru A', 102 => 'Guru B'],
            'scheduled_by_kelas_mapel' => [],
            'jadwal_rows' => [],
        ];
    }

    public function testValidatePlaceOnEmptySlotIsValid(): void
    {
        $v = JadwalPlacementValidator::forTest($this->baseFixture());
        $result = $v->validatePlace([
            'kelas_id' => 10,
            'hari_id' => 1,
            'timeslot_id' => 11,
            'kelas_mapel_id' => 100,
            'guru_id' => 101,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(201, $result['ruangan_id']);
        $this->assertNotEmpty($result['blok_group']);
    }

    public function testRemainingJpBlocksWhenQuotaFull(): void
    {
        $ctx = $this->baseFixture();
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 200, 'hari_id' => 1, 'timeslot_id' => 12,
                'kelas_id' => 10, 'guru_id' => 102, 'mapel_id' => 2, 'blok_group' => 'bg1',
            ],
        ];
        $v = JadwalPlacementValidator::forTest($ctx);

        $this->assertSame(0, $v->remainingJp(200));

        $result = $v->validatePlace([
            'kelas_id' => 10,
            'hari_id' => 2,
            'timeslot_id' => 21,
            'kelas_mapel_id' => 200,
            'guru_id' => 102,
        ]);
        $this->assertFalse($result['valid']);
        $this->assertContains('HC-5', $result['violations']);
    }

    public function testTeacherConflictFailsHc1(): void
    {
        $ctx = $this->baseFixture();
        $ctx['kelas_by_id'][20] = ['id' => 20, 'jurusan_id' => 1, 'ruangan_id' => 202];
        $ctx['kelas_mapel_by_id'][300] = [
            'id' => 300, 'kelas_id' => 20, 'mapel_id' => 1, 'jam_per_minggu' => 2, 'butuh_lab' => 0, 'lab_id' => null,
        ];
        // Guru 101 sudah mengajar kelas lain di slot yang sama.
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 300, 'hari_id' => 1, 'timeslot_id' => 12,
                'kelas_id' => 20, 'guru_id' => 101, 'mapel_id' => 1, 'blok_group' => 'bg1',
            ],
        ];
        $v = JadwalPlacementValidator::forTest($ctx);

        $result = $v->validatePlace([
            'kelas_id' => 10,
            'hari_id' => 1,
            'timeslot_id' => 12,
            'kelas_mapel_id' => 100,
            'guru_id' => 101,
        ]);
        $this->assertFalse($result['valid']);
        $this->assertContains('HC-1', $result['violations']);
    }

    public function testMapelMaySpanBreakAfterHc8Removed(): void
    {
        $ctx = $this->baseFixture();
        // Mapel 1 di JP2 (index 1), coba tambah di JP3 (index 2) setelah istirahat — diizinkan.
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 100, 'hari_id' => 1, 'timeslot_id' => 12,
                'kelas_id' => 10, 'guru_id' => 101, 'mapel_id' => 1, 'blok_group' => 'bg1',
            ],
        ];
        $v = JadwalPlacementValidator::forTest($ctx);

        $options = $v->getEligibleMapelForSlot(10, 1, 14);
        $mapelIds = array_column($options, 'mapel_id');
        $this->assertContains(1, $mapelIds, 'MTK should appear across break after HC-8 removal');

        $result = $v->validatePlace([
            'kelas_id' => 10,
            'hari_id' => 1,
            'timeslot_id' => 14,
            'kelas_mapel_id' => 100,
            'guru_id' => 101,
        ]);
        $this->assertTrue($result['valid']);
    }

    public function testDeleteFreesRemainingJp(): void
    {
        $ctx = $this->baseFixture();
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 200, 'hari_id' => 1, 'timeslot_id' => 12,
                'kelas_id' => 10, 'guru_id' => 102, 'mapel_id' => 2, 'blok_group' => 'bg1',
            ],
        ];
        $v = JadwalPlacementValidator::forTest($ctx);
        $this->assertSame(0, $v->remainingJp(200));

        // Simulate delete: rebuild without the row.
        $ctx['jadwal_rows'] = [];
        $v2 = JadwalPlacementValidator::forTest($ctx);
        $this->assertSame(1, $v2->remainingJp(200));
    }

    public function testMapelSlotReportEligibleWhenAlternateLabFree(): void
    {
        $ctx = $this->baseFixture();
        $ctx['kelas_by_id'][20] = ['id' => 20, 'jurusan_id' => 1, 'ruangan_id' => 202, 'nama' => 'X TKJ 3'];
        $ctx['kelas_mapel_by_id'][300] = [
            'id' => 300, 'kelas_id' => 20, 'mapel_id' => 3, 'jam_per_minggu' => 8, 'butuh_lab' => 1, 'lab_id' => 501,
        ];
        $ctx['kelas_mapel_by_id'][400] = [
            'id' => 400, 'kelas_id' => 10, 'mapel_id' => 3, 'jam_per_minggu' => 12, 'butuh_lab' => 1, 'lab_id' => 501,
        ];
        $ctx['kelas_mapel_by_kelas'][10][] = $ctx['kelas_mapel_by_id'][400];
        $ctx['mapel_meta'][3] = ['id' => 3, 'kode' => 'K-TKJ', 'nama' => 'Produktif TKJ', 'tipe' => 'kejuruan', 'jurusan_id' => 1];
        $ctx['guru_pool'][3] = [
            ['guru_id' => 201, 'max_jam' => 24, 'mapel_tipe' => 'kejuruan', 'mapel_jurusan_id' => 1],
            ['guru_id' => 202, 'max_jam' => 24, 'mapel_tipe' => 'kejuruan', 'mapel_jurusan_id' => 1],
        ];
        $ctx['guru_names'][201] = 'Gugy';
        $ctx['guru_names'][202] = 'sony';
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 300, 'hari_id' => 2, 'timeslot_id' => 22,
                'kelas_id' => 20, 'guru_id' => 201, 'mapel_id' => 3, 'blok_group' => 'bg1',
                'ruangan_id' => 501,
            ],
        ];
        $ctx['lab_pool_by_jurusan'] = [1 => [501, 502]];
        $v = JadwalPlacementValidator::forTest($ctx);
        $report = $v->getMapelSlotReport(10, 2, 22);

        $prod = array_values(array_filter($report['eligible'], fn ($e) => $e['kelas_mapel_id'] === 400));
        $this->assertCount(1, $prod, 'Produktif kelas 10 eligible via lab jurusan alternatif');
    }

    public function testMapelSlotReportHc1WhenAllEligibleGurusBusy(): void
    {
        $ctx = $this->baseFixture();
        $ctx['kelas_by_id'][20] = ['id' => 20, 'jurusan_id' => 1, 'ruangan_id' => 202];
        $ctx['kelas_by_id'][21] = ['id' => 21, 'jurusan_id' => 1, 'ruangan_id' => 203];
        $ctx['kelas_mapel_by_id'][300] = [
            'id' => 300, 'kelas_id' => 20, 'mapel_id' => 1, 'jam_per_minggu' => 2, 'butuh_lab' => 0, 'lab_id' => null,
        ];
        $ctx['kelas_mapel_by_id'][301] = [
            'id' => 301, 'kelas_id' => 21, 'mapel_id' => 1, 'jam_per_minggu' => 2, 'butuh_lab' => 0, 'lab_id' => null,
        ];
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 300, 'hari_id' => 1, 'timeslot_id' => 11,
                'kelas_id' => 20, 'guru_id' => 101, 'mapel_id' => 1, 'blok_group' => 'bg1',
            ],
            [
                'id' => 2, 'kelas_mapel_id' => 301, 'hari_id' => 1, 'timeslot_id' => 11,
                'kelas_id' => 21, 'guru_id' => 102, 'mapel_id' => 1, 'blok_group' => 'bg2',
            ],
        ];
        $v = JadwalPlacementValidator::forTest($ctx);
        $report = $v->getMapelSlotReport(10, 1, 11);

        $blocked = array_values(array_filter($report['blocked'], fn ($b) => $b['mapel_id'] === 1));
        $this->assertNotEmpty($blocked);
        $this->assertContains('HC-1', $blocked[0]['codes']);
        $this->assertStringContainsString('bentrok', $blocked[0]['message']);
    }

    public function testMapelSlotReportListsHc5WhenQuotaFull(): void
    {
        $ctx = $this->baseFixture();
        $ctx['kelas_mapel_by_id'][200]['jam_per_minggu'] = 1;
        $ctx['kelas_mapel_by_kelas'][10][1]['jam_per_minggu'] = 1;
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 200, 'hari_id' => 1, 'timeslot_id' => 12,
                'kelas_id' => 10, 'guru_id' => 102, 'mapel_id' => 2, 'blok_group' => 'bg1',
            ],
        ];
        $v = JadwalPlacementValidator::forTest($ctx);
        $report = $v->getMapelSlotReport(10, 2, 21);
        $blocked = array_values(array_filter($report['blocked'], fn ($b) => $b['kelas_mapel_id'] === 200));

        $this->assertCount(1, $blocked);
        $this->assertContains('HC-5', $blocked[0]['codes']);
    }

    public function testManualPlaceInheritsDayLab(): void
    {
        $ctx = $this->baseFixture();
        $ctx['kelas_by_id'][10]['jurusan_id'] = 1;
        $ctx['kelas_mapel_by_id'][500] = [
            'id' => 500, 'kelas_id' => 10, 'mapel_id' => 3, 'jam_per_minggu' => 2,
            'butuh_lab' => 1, 'lab_id' => 501,
        ];
        $ctx['kelas_mapel_by_kelas'][10][] = $ctx['kelas_mapel_by_id'][500];
        $ctx['mapel_meta'][3] = ['id' => 3, 'kode' => 'PRD', 'nama' => 'Produktif', 'tipe' => 'kejuruan', 'jurusan_id' => 1];
        $ctx['guru_pool'][3] = [
            ['guru_id' => 201, 'max_jam' => 10, 'mapel_tipe' => 'kejuruan', 'mapel_jurusan_id' => 1],
        ];
        $ctx['guru_names'][201] = 'Guru Lab';
        $ctx['lab_pool_by_jurusan'] = [1 => [501, 502]];
        $ctx['jadwal_rows'] = [
            [
                'id' => 1, 'kelas_mapel_id' => 500, 'hari_id' => 1, 'timeslot_id' => 11,
                'kelas_id' => 10, 'guru_id' => 201, 'mapel_id' => 3, 'blok_group' => 'bg1',
                'ruangan_id' => 502,
            ],
        ];
        $v = JadwalPlacementValidator::forTest($ctx);
        $result = $v->validatePlace([
            'kelas_id' => 10, 'hari_id' => 1, 'timeslot_id' => 12,
            'kelas_mapel_id' => 500, 'guru_id' => 201,
        ]);
        $this->assertTrue($result['valid']);
        $this->assertSame(502, $result['ruangan_id']);
    }

    public function testManualPlacePrefersMainLabWhenFree(): void
    {
        $ctx = $this->baseFixture();
        $ctx['kelas_by_id'][10]['jurusan_id'] = 1;
        $ctx['kelas_mapel_by_id'][500] = [
            'id' => 500, 'kelas_id' => 10, 'mapel_id' => 3, 'jam_per_minggu' => 2,
            'butuh_lab' => 1, 'lab_id' => 501,
        ];
        $ctx['kelas_mapel_by_kelas'][10][] = $ctx['kelas_mapel_by_id'][500];
        $ctx['mapel_meta'][3] = ['id' => 3, 'kode' => 'PRD', 'nama' => 'Produktif', 'tipe' => 'kejuruan', 'jurusan_id' => 1];
        $ctx['guru_pool'][3] = [
            ['guru_id' => 201, 'max_jam' => 10, 'mapel_tipe' => 'kejuruan', 'mapel_jurusan_id' => 1],
        ];
        $ctx['guru_names'][201] = 'Guru Lab';
        $ctx['lab_pool_by_jurusan'] = [1 => [501, 502]];
        $v = JadwalPlacementValidator::forTest($ctx);
        $result = $v->validatePlace([
            'kelas_id' => 10, 'hari_id' => 2, 'timeslot_id' => 21,
            'kelas_mapel_id' => 500, 'guru_id' => 201,
        ]);
        $this->assertTrue($result['valid']);
        $this->assertSame(501, $result['ruangan_id']);
    }
}
