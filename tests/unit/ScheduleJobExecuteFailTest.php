<?php

use App\Libraries\ScheduleGenerator;
use App\Libraries\ScheduleJobService;
use App\Models\ScheduleJobModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ScheduleJobExecuteFailTest extends CIUnitTestCase
{
    public function testExecuteJobMarksFailedOnGeneratorException(): void
    {
        $jobModel = $this->getMockBuilder(ScheduleJobModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['update', 'find'])
            ->getMock();

        $statuses = [];
        $jobModel->method('update')->willReturnCallback(static function ($id, $data) use (&$statuses) {
            $statuses[] = $data;

            return true;
        });
        $jobModel->method('find')->willReturn([
            'id'               => 42,
            'cancel_requested' => 0,
            'status'           => ScheduleJobModel::STATUS_RUNNING,
        ]);

        $service = new class ($jobModel) extends ScheduleJobService {
            protected function createGenerator(): ScheduleGenerator
            {
                throw new RuntimeException('simulated OOM');
            }
        };

        $service->executeJob([
            'id'               => 42,
            'tahun_ajaran_id'  => 1,
            'user_id'          => 1,
            'parent_log_id'    => null,
            'generate_mode'    => 'fresh',
            'cancel_requested' => 0,
        ]);

        $failed = array_filter(
            $statuses,
            static fn (array $row): bool => ($row['status'] ?? '') === ScheduleJobModel::STATUS_FAILED
        );

        $this->assertNotEmpty($failed, 'Job must be marked failed, not left running');
        $last = array_values($failed)[0];
        $this->assertStringContainsString('simulated OOM', (string) ($last['error_message'] ?? ''));
    }
}
