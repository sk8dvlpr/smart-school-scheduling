<?php

namespace App\Libraries;

use App\Models\ScheduleJobModel;
use Config\Database;

class ScheduleJobService
{
    protected ScheduleJobModel $jobModel;

    public function __construct(?ScheduleJobModel $jobModel = null)
    {
        $this->jobModel = $jobModel ?? new ScheduleJobModel();
    }

    /**
     * @return array{success: bool, job_id?: int, message: string}
     */
    public function enqueue(
        int $tahunAjaranId,
        int $userId,
        ?int $parentLogId,
        string $generateMode = 'fresh'
    ): array {
        $db = Database::connect();
        $db->transStart();

        $active = $this->jobModel
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->whereIn('status', [ScheduleJobModel::STATUS_QUEUED, ScheduleJobModel::STATUS_RUNNING])
            ->first();

        if ($active !== null) {
            $db->transComplete();

            return [
                'success' => false,
                'message' => 'Generate sedang antrian atau berjalan untuk tahun ajaran ini. Tunggu selesai atau batalkan job aktif.',
            ];
        }

        $mode = $generateMode === 'history_repair' ? 'history_repair' : 'fresh';
        $now  = date('Y-m-d H:i:s');

        $jobId = $this->jobModel->insert([
            'tahun_ajaran_id' => $tahunAjaranId,
            'user_id'         => $userId,
            'status'          => ScheduleJobModel::STATUS_QUEUED,
            'progress'        => 0,
            'parent_log_id'   => $parentLogId,
            'generate_mode'   => $mode,
            'created_at'      => $now,
        ], true);

        $db->transComplete();

        if (! $db->transStatus() || ! $jobId) {
            return [
                'success' => false,
                'message' => 'Gagal menambahkan job generate ke antrian.',
            ];
        }

        return [
            'success' => true,
            'job_id'  => (int) $jobId,
            'message' => 'Generate dijadwalkan. Worker akan memproses job ini.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(int $id): ?array
    {
        $row = $this->jobModel->find($id);

        return $row ?: null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function requestCancel(int $id): array
    {
        $job = $this->getJob($id);
        if ($job === null) {
            return ['success' => false, 'message' => 'Job tidak ditemukan.'];
        }

        if (in_array($job['status'], [ScheduleJobModel::STATUS_COMPLETED, ScheduleJobModel::STATUS_FAILED, ScheduleJobModel::STATUS_CANCELLED], true)) {
            return ['success' => false, 'message' => 'Job sudah selesai dan tidak dapat dibatalkan.'];
        }

        $now = date('Y-m-d H:i:s');

        if ($job['status'] === ScheduleJobModel::STATUS_QUEUED) {
            $this->jobModel->update($id, [
                'status'           => ScheduleJobModel::STATUS_CANCELLED,
                'cancel_requested' => 1,
                'finished_at'      => $now,
                'error_message'    => 'Dibatalkan oleh pengguna.',
            ]);

            return ['success' => true, 'message' => 'Job dibatalkan.'];
        }

        $this->jobModel->update($id, ['cancel_requested' => 1]);

        return ['success' => true, 'message' => 'Permintaan pembatalan dikirim. Job akan berhenti sebelum eksekusi berikutnya.'];
    }

    /**
     * Atomically claim the oldest queued job.
     *
     * @return array<string, mixed>|null
     */
    public function claimNext(): ?array
    {
        $db = Database::connect();
        $db->transStart();

        $row = $db->query(
            'SELECT id FROM schedule_jobs WHERE status = ? ORDER BY id ASC LIMIT 1 FOR UPDATE',
            [ScheduleJobModel::STATUS_QUEUED]
        )->getRowArray();

        if ($row === null) {
            $db->transComplete();

            return null;
        }

        $id  = (int) $row['id'];
        $now = date('Y-m-d H:i:s');

        $updated = $db->table('schedule_jobs')
            ->where('id', $id)
            ->where('status', ScheduleJobModel::STATUS_QUEUED)
            ->update([
                'status'     => ScheduleJobModel::STATUS_RUNNING,
                'started_at' => $now,
                'progress'   => 1,
            ]);

        $db->transComplete();

        if (! $updated || ! $db->transStatus()) {
            return null;
        }

        return $this->getJob($id);
    }

    public function updateProgress(int $id, int $progress, ?int $generation = null, ?float $bestFitness = null): void
    {
        $progress = max(0, min(100, $progress));
        $data     = ['progress' => $progress];

        if ($generation !== null) {
            $data['generation'] = $generation;
        }
        if ($bestFitness !== null) {
            $data['best_fitness'] = $bestFitness;
        }

        $this->jobModel->update($id, $data);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function complete(int $id, ?int $scheduleLogId, array $meta = []): void
    {
        $generation   = isset($meta['generation']) ? (int) $meta['generation'] : null;
        $bestFitness  = isset($meta['best_fitness']) ? (float) $meta['best_fitness'] : null;

        $this->jobModel->update($id, [
            'status'          => ScheduleJobModel::STATUS_COMPLETED,
            'progress'        => 100,
            'schedule_log_id' => $scheduleLogId,
            'generation'      => $generation,
            'best_fitness'    => $bestFitness,
            'finished_at'     => date('Y-m-d H:i:s'),
            'error_message'   => null,
        ]);
    }

    public function fail(int $id, string $message): void
    {
        $this->jobModel->update($id, [
            'status'        => ScheduleJobModel::STATUS_FAILED,
            'progress'      => 100,
            'error_message' => $message,
            'finished_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function markCancelled(int $id, string $message = 'Dibatalkan.'): void
    {
        $this->jobModel->update($id, [
            'status'        => ScheduleJobModel::STATUS_CANCELLED,
            'error_message' => $message,
            'finished_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function isCancelRequested(int $id): bool
    {
        $job = $this->getJob($id);

        return $job !== null && (int) ($job['cancel_requested'] ?? 0) === 1;
    }

    /**
     * Run a single queued job (shared-hosting tick). Only the owning user may trigger.
     *
     * @return array{success: bool, message: string, processed?: bool, job_id?: int}
     */
    public function processOne(int $jobId, int $userId): array
    {
        $job = $this->getJob($jobId);
        if ($job === null) {
            return ['success' => false, 'message' => 'Job tidak ditemukan.'];
        }

        if ((int) $job['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Job bukan milik sesi ini.'];
        }

        if ($job['status'] === ScheduleJobModel::STATUS_QUEUED) {
            $db = Database::connect();
            $db->transStart();
            $claimed = $db->table('schedule_jobs')
                ->where('id', $jobId)
                ->where('status', ScheduleJobModel::STATUS_QUEUED)
                ->update([
                    'status'     => ScheduleJobModel::STATUS_RUNNING,
                    'started_at' => date('Y-m-d H:i:s'),
                    'progress'   => 1,
                ]);
            $db->transComplete();

            if (! $claimed) {
                return ['success' => true, 'message' => 'Job sudah diambil worker lain.', 'processed' => false];
            }

            $job = $this->getJob($jobId);
        }

        if ($job['status'] !== ScheduleJobModel::STATUS_RUNNING) {
            return ['success' => true, 'message' => 'Job tidak perlu diproses.', 'processed' => false];
        }

        $this->executeJob($job);

        return [
            'success'   => true,
            'message'   => 'Job diproses.',
            'processed' => true,
            'job_id'    => $jobId,
        ];
    }

    /**
     * @param array<string, mixed> $job
     */
    public function executeJob(array $job): void
    {
        $jobId = (int) $job['id'];

        if ($this->isCancelRequested($jobId)) {
            $this->markCancelled($jobId);

            return;
        }

        $this->updateProgress($jobId, 10, null, null);

        try {
            $generator = $this->createGenerator();
            $result    = $generator->generate(
                (int) $job['tahun_ajaran_id'],
                (int) $job['user_id'],
                $job['parent_log_id'] !== null ? (int) $job['parent_log_id'] : null,
                (string) ($job['generate_mode'] ?? 'fresh')
            );
        } catch (\Throwable $e) {
            log_message('error', 'Schedule job {id} exception: {msg}', [
                'id'  => $jobId,
                'msg' => $e->getMessage(),
            ]);
            $this->fail($jobId, 'Generate gagal: ' . $e->getMessage());

            return;
        }

        if ($this->isCancelRequested($jobId)) {
            $this->markCancelled($jobId, 'Dibatalkan setelah eksekusi dimulai.');

            return;
        }

        $gaStats = $result['report']['stats']['ga'] ?? null;
        if (is_array($gaStats)) {
            $this->updateProgress(
                $jobId,
                95,
                isset($gaStats['generations']) ? (int) $gaStats['generations'] : null,
                isset($gaStats['fitness']) ? (float) $gaStats['fitness'] : null
            );
        }

        if (! empty($result['success'])) {
            $logId = isset($result['schedule_log_id']) ? (int) $result['schedule_log_id'] : null;
            $this->complete($jobId, $logId, [
                'generation'   => isset($gaStats['generations']) ? (int) $gaStats['generations'] : null,
                'best_fitness' => isset($gaStats['fitness']) ? (float) $gaStats['fitness'] : null,
            ]);

            return;
        }

        $message = (string) ($result['message'] ?? $result['summary'] ?? 'Generate gagal.');
        log_message('error', 'Schedule job {id} failed: {msg}', [
            'id'  => $jobId,
            'msg' => $message,
        ]);
        $this->fail($jobId, $message);
    }

    /**
     * Hook for tests to inject a failing / fake generator.
     */
    protected function createGenerator(): ScheduleGenerator
    {
        return new ScheduleGenerator();
    }
}
