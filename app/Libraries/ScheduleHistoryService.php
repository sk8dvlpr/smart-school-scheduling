<?php

namespace App\Libraries;

use App\Models\JadwalModel;
use App\Models\ScheduleLogModel;
use App\Models\TahunAjaranModel;

/**
 * Publish workflow, Kepsek approval, and published schedule resolution.
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

        // Unpublish previous + reset their approval
        $db->table('schedule_logs')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('published_at IS NOT NULL', null, false)
            ->update([
                'published_at'    => null,
                'published_by'    => null,
                'approval_status' => null,
                'approved_at'     => null,
                'approved_by'     => null,
                'approval_note'   => null,
            ]);

        $now = date('Y-m-d H:i:s');
        $logModel->update($scheduleLogId, [
            'published_at'    => $now,
            'published_by'    => $userId,
            'approval_status' => 'pending',
            'approved_at'     => null,
            'approved_by'     => null,
            'approval_note'   => null,
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
            $warning = "Jadwal dipublish dalam status partial ({$pct}% terisi). Menunggu persetujuan Kepala Sekolah.";
        }

        $result = [
            'success' => true,
            'message' => 'Jadwal berhasil dipublish. Menunggu persetujuan Kepala Sekolah sebelum tampil ke Guru.',
        ];
        if ($warning) {
            $result['warning'] = $warning;
        }

        return $result;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function approve(int $tahunAjaranId, int $userId, ?string $note = null): array
    {
        return $this->setApproval($tahunAjaranId, $userId, 'approved', $note);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function reject(int $tahunAjaranId, int $userId, ?string $note = null): array
    {
        return $this->setApproval($tahunAjaranId, $userId, 'rejected', $note);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function setApproval(int $tahunAjaranId, int $userId, string $status, ?string $note): array
    {
        $log = $this->getPublishedLog($tahunAjaranId);
        if ($log === null) {
            return ['success' => false, 'message' => 'Tidak ada jadwal yang dipublish untuk disetujui.'];
        }

        if (($log['approval_status'] ?? null) === $status) {
            $label = $status === 'approved' ? 'disetujui' : 'ditolak';

            return ['success' => true, 'message' => "Jadwal sudah berstatus {$label}."];
        }

        $note = $note !== null ? trim($note) : null;
        if ($note === '') {
            $note = null;
        }
        if ($note !== null && mb_strlen($note) > 500) {
            return ['success' => false, 'message' => 'Catatan maksimal 500 karakter.'];
        }

        (new ScheduleLogModel())->update((int) $log['id'], [
            'approval_status' => $status,
            'approved_at'     => date('Y-m-d H:i:s'),
            'approved_by'     => $userId,
            'approval_note'   => $note,
        ]);

        if ($status === 'approved') {
            return ['success' => true, 'message' => 'Jadwal disetujui. Sekarang tampil di akun Guru.'];
        }

        return ['success' => true, 'message' => 'Jadwal ditolak. Kurikulum dapat memperbaiki dan publish ulang.'];
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

    /**
     * Published AND approved — for Guru (and laporan resmi).
     */
    public function getApprovedLogId(int $tahunAjaranId): ?int
    {
        $log = $this->getPublishedLog($tahunAjaranId);
        if ($log === null) {
            return null;
        }

        if (($log['approval_status'] ?? null) !== 'approved') {
            return null;
        }

        return (int) $log['id'];
    }
}
