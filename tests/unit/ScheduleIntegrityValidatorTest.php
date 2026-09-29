<?php

use App\Libraries\ScheduleIntegrityValidator;
use App\Libraries\TemporaryPassword;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ScheduleIntegrityValidatorTest extends CIUnitTestCase
{
    public function testValidAssignmentHasNoViolations(): void
    {
        $units = [
            0 => ['kelas_id' => 1, 'mapel_id' => 10, 'kelas_mapel_id' => 100, 'butuh_lab' => 0, 'jurusan_id' => 1],
            1 => ['kelas_id' => 1, 'mapel_id' => 10, 'kelas_mapel_id' => 100, 'butuh_lab' => 0, 'jurusan_id' => 1],
        ];
        $assignments = [
            0 => ['guru_id' => 5, 'hari_id' => 1, 'timeslot_id' => 11, 'ruangan_id' => 1],
            1 => ['guru_id' => 5, 'hari_id' => 1, 'timeslot_id' => 12, 'ruangan_id' => 1],
        ];
        $engine = [
            'jp_slots_by_hari' => [
                1 => [
                    ['id' => 11, 'jam_ke' => 1],
                    ['id' => 12, 'jam_ke' => 2],
                ],
            ],
            'guru_blokir' => [],
            'guru_pool' => [
                10 => [['guru_id' => 5, 'max_jam' => 10]],
            ],
            'kelas_mapel_demand' => [100 => 2],
        ];

        $result = (new ScheduleIntegrityValidator())->validate($assignments, $units, $engine);
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['violations']);
    }

    public function testDetectsTeacherClashHc1(): void
    {
        $units = [
            0 => ['kelas_id' => 1, 'mapel_id' => 10, 'kelas_mapel_id' => 100, 'butuh_lab' => 0],
            1 => ['kelas_id' => 2, 'mapel_id' => 10, 'kelas_mapel_id' => 200, 'butuh_lab' => 0],
        ];
        $assignments = [
            0 => ['guru_id' => 5, 'hari_id' => 1, 'timeslot_id' => 11, 'ruangan_id' => 1],
            1 => ['guru_id' => 5, 'hari_id' => 1, 'timeslot_id' => 11, 'ruangan_id' => 2],
        ];
        $engine = [
            'guru_blokir' => [],
            'guru_pool'   => [10 => [['guru_id' => 5, 'max_jam' => 10]]],
        ];

        $result = (new ScheduleIntegrityValidator())->validate($assignments, $units, $engine);
        $this->assertFalse($result['ok']);
        $this->assertSame('HC-1', $result['violations'][0]['code']);
    }

    public function testTemporaryPasswordSanitize(): void
    {
        $this->assertSame("'=1+1", TemporaryPassword::sanitizeSpreadsheetValue('=1+1'));
        $this->assertSame('Matematika', TemporaryPassword::sanitizeSpreadsheetValue('Matematika'));
        $pwd = TemporaryPassword::generate(12);
        $this->assertSame(12, strlen($pwd));
    }
}
