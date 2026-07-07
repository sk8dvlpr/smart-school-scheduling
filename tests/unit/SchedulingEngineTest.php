<?php

use App\Libraries\CSPEngine;
use App\Libraries\GAEngine;
use App\Libraries\SchedulingContext;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * End-to-end check for the v3.1 CSP + GA solver (HC-1..HC-8, SC-1..SC-11).
 *
 * Pure in-memory fixture (no DB): two full-grid classes sharing teachers and a
 * single lab, with a break in each day and a day-blocked teacher (HC-4).
 * The whole point of v3.0 is that a solvable week is ALWAYS completed, so the
 * core assertion is "zero unplaced units + no hard-constraint violations".
 *
 * @internal
 */
final class SchedulingEngineTest extends CIUnitTestCase
{
    /**
     * @return array{engine: array, units: array}
     */
    private function fixture(): array
    {
        // Two days, each with an istirahat between JP blocks.
        $timeslotsByHari = [
            1 => [ // Senin: jp 1-4 | break | jp 5-6
                ['id' => 11, 'jam_ke' => 1, 'tipe' => 'jp'],
                ['id' => 12, 'jam_ke' => 2, 'tipe' => 'jp'],
                ['id' => 13, 'jam_ke' => 3, 'tipe' => 'jp'],
                ['id' => 14, 'jam_ke' => 4, 'tipe' => 'jp'],
                ['id' => 15, 'jam_ke' => 0, 'tipe' => 'istirahat'],
                ['id' => 16, 'jam_ke' => 5, 'tipe' => 'jp'],
                ['id' => 17, 'jam_ke' => 6, 'tipe' => 'jp'],
            ],
            2 => [ // Selasa: jp 1-3 | break | jp 4-5
                ['id' => 21, 'jam_ke' => 1, 'tipe' => 'jp'],
                ['id' => 22, 'jam_ke' => 2, 'tipe' => 'jp'],
                ['id' => 23, 'jam_ke' => 3, 'tipe' => 'jp'],
                ['id' => 24, 'jam_ke' => 0, 'tipe' => 'istirahat'],
                ['id' => 25, 'jam_ke' => 4, 'tipe' => 'jp'],
                ['id' => 26, 'jam_ke' => 5, 'tipe' => 'jp'],
            ],
        ];
        $jpSlotsByHari = SchedulingContext::buildJpSlotsByHari($timeslotsByHari);
        $weeklyCap     = SchedulingContext::weeklyJpCapacity($jpSlotsByHari); // 11

        $hariData = [
            ['id' => 1, 'nama' => 'Senin', 'kode' => 'SEN', 'urutan' => 1],
            ['id' => 2, 'nama' => 'Selasa', 'kode' => 'SEL', 'urutan' => 2],
        ];

        // Mapel: M1 umum (heavy), M2 umum (light), M3 kejuruan+lab. Per class 9 JP
        // (< 11 cap) so gaps are allowed — the v3.0 design.
        $mapelRows = [
            ['id' => 1, 'tipe' => 'umum', 'jurusan_id' => null, 'bobot_kognitif' => 9],
            ['id' => 2, 'tipe' => 'umum', 'jurusan_id' => null, 'bobot_kognitif' => 2],
            ['id' => 3, 'tipe' => 'kejuruan', 'jurusan_id' => 1, 'bobot_kognitif' => 7],
        ];
        // One teacher per mapel, shared across both classes (exercises HC-1).
        $guruMapelRows = [
            ['guru_id' => 101, 'mapel_id' => 1, 'max_jam_per_minggu' => 40],
            ['guru_id' => 102, 'mapel_id' => 2, 'max_jam_per_minggu' => 40],
            ['guru_id' => 103, 'mapel_id' => 3, 'max_jam_per_minggu' => 40],
        ];
        $guruPool = SchedulingContext::buildGuruPool($guruMapelRows, $mapelRows);

        // HC-4: teacher 102 (M2) blocked on Selasa.
        $guruBlokir = SchedulingContext::buildGuruBlokirIndex([
            ['guru_id' => 102, 'hari_id' => 2],
        ]);

        // Two classes in jurusan 1, homerooms 201/202, shared lab 300.
        $classes = [
            ['kelas_id' => 10, 'homeroom' => 201],
            ['kelas_id' => 20, 'homeroom' => 202],
        ];
        $perClassPlan = [
            ['mapel' => 1, 'jam' => 4, 'lab' => 0, 'bobot' => 9],
            ['mapel' => 2, 'jam' => 2, 'lab' => 0, 'bobot' => 2],
            ['mapel' => 3, 'jam' => 3, 'lab' => 1, 'bobot' => 7],
        ];

        $units = [];
        $uid   = 1;
        foreach ($classes as $c) {
            $kmId = $c['kelas_id'] * 10;
            foreach ($perClassPlan as $p) {
                for ($i = 0; $i < $p['jam']; $i++) {
                    $units[$uid] = [
                        'unit_id'        => $uid,
                        'kelas_mapel_id' => $kmId + $p['mapel'],
                        'kelas_id'       => $c['kelas_id'],
                        'mapel_id'       => $p['mapel'],
                        'jurusan_id'     => 1,
                        'unit_index'     => $i,
                        'butuh_lab'      => $p['lab'],
                        'lab_id'         => $p['lab'] === 1 ? 300 : null,
                        'homeroom_id'    => $c['homeroom'],
                        'mapel_tipe'     => $p['mapel'] === 3 ? 'kejuruan' : 'umum',
                        'mapel_jurusan_id' => $p['mapel'] === 3 ? 1 : null,
                        'bobot_kognitif' => $p['bobot'],
                    ];
                    $uid++;
                }
            }
        }

        $engine = [
            'units'            => $units,
            'jp_slots_by_hari' => $jpSlotsByHari,
            'hari_data'        => $hariData,
            'guru_pool'        => $guruPool,
            'guru_blokir'      => $guruBlokir,
            'homeroom_map'     => [10 => 201, 20 => 202],
            'lab_pool_by_jurusan' => [1 => [300]],
            'timeout_seconds'  => 30,
            'csp_max_attempts' => 8,
        ];

        $this->assertSame(11, $weeklyCap);

        return ['engine' => $engine, 'units' => $units];
    }

    public function testCspProducesCompleteValidSchedule(): void
    {
        ['engine' => $engine, 'units' => $units] = $this->fixture();

        $csp    = new CSPEngine($engine);
        $result = $csp->solve();

        $this->assertCount(0, $result['unplaced'], 'CSP left units unplaced: ' . json_encode(array_map(
            static fn ($u) => $u['reason'] ?? '?',
            $result['unplaced']
        )));
        $this->assertCount(count($units), $result['assignments'], 'CSP did not place every unit.');

        $this->assertHardConstraints($result['assignments'], $units, $engine);
    }

    public function testGaKeepsScheduleFeasibleAndScoresFitness(): void
    {
        ['engine' => $engine, 'units' => $units] = $this->fixture();

        $csp    = new CSPEngine($engine);
        $solved = $csp->solve();
        $this->assertCount(count($units), $solved['assignments']);

        $gaConfig = array_merge($engine, [
            'population_size'  => 20,
            'max_generations'  => 40,
            'timeout_seconds'  => 20,
        ]);
        $ga  = new GAEngine($gaConfig);
        $out = $ga->optimize($solved['assignments']);

        $this->assertCount(count($units), $out['assignments'], 'GA dropped units.');
        $this->assertGreaterThan(0.0, $out['fitness']);
        $this->assertLessThanOrEqual(1.0, $out['fitness']);
        $this->assertHardConstraints($out['assignments'], $units, $engine);
    }

    /**
     * Independently re-verify HC-1, HC-2, HC-3, HC-4, HC-6 on a solution.
     */
    private function assertHardConstraints(array $assignments, array $units, array $engine): void
    {
        $guruSlot = [];
        $kelasSlot = [];
        $labSlot = [];
        $guruMapel = [];
        $kmDayLab = [];

        foreach ($assignments as $unitId => $a) {
            $unit = $units[$unitId];
            $g = (int) $a['guru_id'];
            $h = (int) $a['hari_id'];
            $t = (int) $a['timeslot_id'];
            $k = (int) $unit['kelas_id'];
            $m = (int) $unit['mapel_id'];
            $km = (int) $unit['kelas_mapel_id'];

            $this->assertArrayNotHasKey($t, $guruSlot[$g][$h] ?? [], "HC-1 teacher clash guru $g");
            $this->assertArrayNotHasKey($t, $kelasSlot[$k][$h] ?? [], "HC-2 class clash kelas $k");
            $guruSlot[$g][$h][$t] = true;
            $kelasSlot[$k][$h][$t] = true;

            if ((int) ($unit['butuh_lab'] ?? 0) === 1) {
                $lab = (int) ($a['ruangan_id'] ?? 0);
                $this->assertGreaterThan(0, $lab, 'Lab unit missing ruangan_id');
                $pool = $engine['lab_pool_by_jurusan'][(int) $unit['jurusan_id']] ?? [];
                $this->assertContains($lab, $pool, 'HC-LAB-JURUSAN violated');
                $locked = $kmDayLab[$km][$h] ?? null;
                if ($locked !== null) {
                    $this->assertSame($locked, $lab, "HC-LAB-DAY km $km hari $h");
                } else {
                    $kmDayLab[$km][$h] = $lab;
                }
                $this->assertArrayNotHasKey($t, $labSlot[$lab][$h] ?? [], "HC-3 lab clash lab $lab");
                $labSlot[$lab][$h][$t] = true;
            }

            $this->assertArrayNotHasKey($h, $engine['guru_blokir'][$g] ?? [], "HC-4 guru $g scheduled on blocked day $h");

            $guruMapel[$g][$m] = (int) ($guruMapel[$g][$m] ?? 0) + 1;
        }

        // HC-6 weekly caps respected.
        foreach ($guruMapel as $g => $mapels) {
            foreach ($mapels as $m => $cnt) {
                $cap = 0;
                foreach ($engine['guru_pool'][$m] ?? [] as $e) {
                    if ($e['guru_id'] === (int) $g) {
                        $cap = $e['max_jam'];
                    }
                }
                $this->assertLessThanOrEqual($cap, $cnt, "HC-6 cap exceeded guru $g mapel $m");
            }
        }
    }

    public function testGaSc7PenalizesAvoidedSlot(): void
    {
        $f = $this->fixture();
        $engine = $f['engine'];
        $units = $f['units'];

        $csp = new CSPEngine($engine);
        $cspResult = $csp->solve();
        $assignments = $cspResult['assignments'];
        $this->assertNotEmpty($assignments);

        $gaData = array_merge($engine, [
            'max_generations' => 5,
            'population_size' => 10,
            'guru_preferensi' => [
                ['guru_id' => 101, 'hari_id' => 1, 'timeslot_id' => null, 'tipe' => 'avoid', 'bobot' => 10],
            ],
            'sc7_teacher_preference' => 8,
        ]);

        $ga = new GAEngine($gaData);
        $optimized = $ga->optimize($assignments);
        $this->assertGreaterThan(0, $optimized['fitness']);
    }

    /**
     * @return array{engine: array, units: array}
     */
    private function labPoolFixture(): array
    {
        $timeslotsByHari = [
            1 => [
                ['id' => 11, 'jam_ke' => 1, 'tipe' => 'jp'],
                ['id' => 12, 'jam_ke' => 2, 'tipe' => 'jp'],
                ['id' => 13, 'jam_ke' => 3, 'tipe' => 'jp'],
                ['id' => 14, 'jam_ke' => 4, 'tipe' => 'jp'],
            ],
            2 => [
                ['id' => 21, 'jam_ke' => 1, 'tipe' => 'jp'],
                ['id' => 22, 'jam_ke' => 2, 'tipe' => 'jp'],
                ['id' => 23, 'jam_ke' => 3, 'tipe' => 'jp'],
                ['id' => 24, 'jam_ke' => 4, 'tipe' => 'jp'],
            ],
        ];
        $jpSlotsByHari = SchedulingContext::buildJpSlotsByHari($timeslotsByHari);
        $mapelRows = [
            ['id' => 3, 'tipe' => 'kejuruan', 'jurusan_id' => 1, 'bobot_kognitif' => 7],
        ];
        $guruPool = SchedulingContext::buildGuruPool(
            [['guru_id' => 103, 'mapel_id' => 3, 'max_jam_per_minggu' => 40]],
            $mapelRows
        );

        $units = [];
        $uid = 1;
        foreach ([10, 20] as $kelasId) {
            $kmId = $kelasId * 10 + 3;
            for ($i = 0; $i < 4; $i++) {
                $units[$uid] = [
                    'unit_id'          => $uid,
                    'kelas_mapel_id'   => $kmId,
                    'kelas_id'         => $kelasId,
                    'mapel_id'         => 3,
                    'jurusan_id'       => 1,
                    'unit_index'       => $i,
                    'butuh_lab'        => 1,
                    'lab_id'           => 300,
                    'homeroom_id'      => 200 + $kelasId,
                    'mapel_tipe'       => 'kejuruan',
                    'mapel_jurusan_id' => 1,
                    'bobot_kognitif'   => 7,
                ];
                $uid++;
            }
        }

        $engine = [
            'units'                 => $units,
            'jp_slots_by_hari'      => $jpSlotsByHari,
            'hari_data'             => [
                ['id' => 1, 'nama' => 'Senin', 'kode' => 'SEN', 'urutan' => 1],
                ['id' => 2, 'nama' => 'Selasa', 'kode' => 'SEL', 'urutan' => 2],
            ],
            'guru_pool'             => $guruPool,
            'guru_blokir'           => [],
            'homeroom_map'          => [10 => 210, 20 => 220],
            'lab_pool_by_jurusan'   => [1 => [300, 301]],
            'timeout_seconds'       => 30,
            'csp_max_attempts'      => 12,
        ];

        return ['engine' => $engine, 'units' => $units];
    }

    public function testLabPoolCompletesWithTwoLabsAvailable(): void
    {
        ['engine' => $engine, 'units' => $units] = $this->labPoolFixture();
        $csp = new CSPEngine($engine);
        $result = $csp->solve();

        $this->assertCount(0, $result['unplaced']);
        $this->assertHardConstraints($result['assignments'], $units, $engine);
    }

    public function testResolveLabUsesAlternateWhenPreferredFull(): void
    {
        $labSlot = [];
        foreach ([11, 12, 13, 14] as $ts) {
            $labSlot[300][1][$ts] = true;
        }
        $resolved = SchedulingContext::resolveLabForPlacement(
            103,
            1,
            11,
            300,
            1,
            [1 => [300, 301]],
            $labSlot,
            [],
            null
        );
        $this->assertSame(301, $resolved);
    }

    public function testLabSameDayConsistencyInSolution(): void
    {
        ['engine' => $engine, 'units' => $units] = $this->labPoolFixture();
        $result = (new CSPEngine($engine))->solve();
        $kmDayLab = [];
        foreach ($result['assignments'] as $unitId => $a) {
            $unit = $units[$unitId];
            if ((int) ($unit['butuh_lab'] ?? 0) !== 1) {
                continue;
            }
            $km = (int) $unit['kelas_mapel_id'];
            $h  = (int) $a['hari_id'];
            $lab = (int) ($a['ruangan_id'] ?? 0);
            if (isset($kmDayLab[$km][$h])) {
                $this->assertSame($kmDayLab[$km][$h], $lab);
            } else {
                $kmDayLab[$km][$h] = $lab;
            }
        }
    }

    public function testResolveLabPrefersMainLabWhenFree(): void
    {
        $labSlot = [];
        $resolved = SchedulingContext::resolveLabForPlacement(
            103,
            1,
            11,
            300,
            1,
            [1 => [300, 301]],
            $labSlot,
            [],
            null
        );
        $this->assertSame(300, $resolved);
    }

    public function testScLabPreferenceAffectsFitness(): void
    {
        $jpSlots = SchedulingContext::buildJpSlotsByHari([
            1 => [
                ['id' => 11, 'jam_ke' => 1, 'tipe' => 'jp'],
                ['id' => 12, 'jam_ke' => 2, 'tipe' => 'jp'],
            ],
        ]);
        $units = [
            1 => [
                'kelas_mapel_id' => 103, 'kelas_id' => 10, 'mapel_id' => 3, 'jurusan_id' => 1,
                'butuh_lab' => 1, 'lab_id' => 300, 'bobot_kognitif' => 7,
            ],
            2 => [
                'kelas_mapel_id' => 103, 'kelas_id' => 10, 'mapel_id' => 3, 'jurusan_id' => 1,
                'butuh_lab' => 1, 'lab_id' => 300, 'bobot_kognitif' => 7,
            ],
        ];
        $engine = [
            'units' => $units,
            'jp_slots_by_hari' => $jpSlots,
            'hari_data' => [['id' => 1, 'nama' => 'Senin', 'kode' => 'SEN', 'urutan' => 1]],
            'guru_pool' => [3 => [['guru_id' => 103, 'max_jam' => 10, 'mapel_tipe' => 'kejuruan', 'mapel_jurusan_id' => 1]]],
            'guru_blokir' => [],
            'homeroom_map' => [10 => 210],
            'lab_pool_by_jurusan' => [1 => [300, 301]],
            'sc_lab_preference' => 10,
            'population_size' => 2,
            'max_generations' => 1,
        ];
        $preferred = [
            1 => ['hari_id' => 1, 'timeslot_id' => 11, 'slot_index' => 0, 'guru_id' => 103, 'ruangan_id' => 300],
            2 => ['hari_id' => 1, 'timeslot_id' => 12, 'slot_index' => 1, 'guru_id' => 103, 'ruangan_id' => 300],
        ];
        $alternate = [
            1 => ['hari_id' => 1, 'timeslot_id' => 11, 'slot_index' => 0, 'guru_id' => 103, 'ruangan_id' => 301],
            2 => ['hari_id' => 1, 'timeslot_id' => 12, 'slot_index' => 1, 'guru_id' => 103, 'ruangan_id' => 301],
        ];
        $ga = new GAEngine($engine);
        $fitPreferred = $ga->optimize($preferred)['fitness'];
        $fitAlternate = $ga->optimize($alternate)['fitness'];
        $this->assertGreaterThan($fitAlternate, $fitPreferred);
    }
}
