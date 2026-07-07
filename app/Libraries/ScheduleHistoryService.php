<?php

namespace App\Libraries;

use App\Models\JadwalModel;
use App\Models\ScheduleLogModel;
use App\Models\TahunAjaranModel;

/**
 * Publish workflow and published schedule resolution.
 */
class ScheduleHistoryService
{
    /**
     * @return array{success: bool, message: string, warning?: string}
     */
    public function publish(int $tahunAjaranId, int $scheduleLogId, int $userId): array
    {
        $logModel = new ScheduleLogModel();
        $log = $logModel->where('id', $scheduleLogId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->first();

        if (! $log) {
            return ['success' => false, 'message' => 'History generate tidak ditemukan.'];
        }

        if (! in_array($log['status'], ['completed', 'partial'], true)) {
            return ['success' => false, 'message' => 'Hanya history berstatus completed atau partial yang bisa dipublish.'];
        }

        $jadwalCount = (new JadwalModel())
            ->where('schedule_log_id', $scheduleLogId)
            ->countAllResults();

        if ($jadwalCount === 0) {
            return ['success' => false, 'message' => 'History ini tidak memiliki baris jadwal.'];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $db->table('schedule_logs')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('published_at IS NOT NULL', null, false)
            ->update(['published_at' => null, 'published_by' => null]);

        $now = date('Y-m-d H:i:s');
        $logModel->update($scheduleLogId, [
            'published_at' => $now,
            'published_by' => $userId,
        ]);

        $db->table('tahun_ajaran')
            ->where('id', $tahunAjaranId)
            ->update(['published_schedule_log_id' => $scheduleLogId]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return ['success' => false, 'message' => 'Gagal mempublikasikan jadwal.'];
        }

        $warning = null;
        if ($log['status'] === 'partial') {
            $report = json_decode($log['result_report'] ?? '{}', true);
            $placed = (int) ($report['stats']['placed_units'] ?? 0);
            $total  = (int) ($report['stats']['total_units'] ?? 0);
            $pct    = $total > 0 ? round(($placed / $total) * 100, 1) : 0;
            $warning = "Jadwal dipublish dalam status partial ({$pct}% terisi). Beberapa slot masih kosong.";
        }

        $result = ['success' => true, 'message' => 'Jadwal berhasil dipublish ke Guru dan Kepala Sekolah.'];
        if ($warning) {
            $result['warning'] = $warning;
        }

        return $result;
    }

    public function getPublishedLogId(int $tahunAjaranId): ?int
    {
        $ta = (new TahunAjaranModel())->find($tahunAjaranId);
        $id = (int) ($ta['published_schedule_log_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPublishedLog(int $tahunAjaranId): ?array
    {
        $logId = $this->getPublishedLogId($tahunAjaranId);
        if ($logId === null) {
            return null;
        }

        return (new ScheduleLogModel())->find($logId) ?: null;
    }
}
