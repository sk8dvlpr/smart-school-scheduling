<?php

namespace App\Models;

use CodeIgniter\Model;

class JadwalModel extends Model
{
    protected $table            = 'jadwal';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'tahun_ajaran_id',
        'schedule_log_id',
        'kelas_mapel_id',
        'hari_id',
        'timeslot_id',
        'kelas_id',
        'guru_id',
        'mapel_id',
        'ruangan_id',
        'blok_group',
        'is_manual',
    ];

    public function getByKelas(int $kelasId, int $tahunAjaranId, ?int $scheduleLogId = null): array
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return [];
        }

        return $this->getBaseQuery($tahunAjaranId, $logId)
            ->where('jadwal.kelas_id', $kelasId)
            ->get()->getResultArray();
    }

    public function getByGuru(int $guruId, int $tahunAjaranId, ?int $scheduleLogId = null): array
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return [];
        }

        return $this->getBaseQuery($tahunAjaranId, $logId)
            ->where('jadwal.guru_id', $guruId)
            ->get()->getResultArray();
    }

    public function getByRuangan(int $ruanganId, int $tahunAjaranId, ?int $scheduleLogId = null): array
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return [];
        }

        return $this->getBaseQuery($tahunAjaranId, $logId)
            ->where('jadwal.ruangan_id', $ruanganId)
            ->get()->getResultArray();
    }

    public function countJpByGuru(int $guruId, int $tahunAjaranId, ?int $scheduleLogId = null): int
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return 0;
        }

        return (int) $this->where('guru_id', $guruId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('schedule_log_id', $logId)
            ->countAllResults();
    }

    public function countByKelasMapel(int $kelasMapelId, int $tahunAjaranId, ?int $scheduleLogId = null): int
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return 0;
        }

        return (int) $this->where('kelas_mapel_id', $kelasMapelId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('schedule_log_id', $logId)
            ->countAllResults();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForManual(int $id, int $tahunAjaranId, ?int $scheduleLogId = null): ?array
    {
        $builder = $this->where('id', $id)->where('tahun_ajaran_id', $tahunAjaranId);
        if ($scheduleLogId !== null) {
            $builder->where('schedule_log_id', $scheduleLogId);
        }
        $row = $builder->first();

        return $row ?: null;
    }

    public function countDistinctGuruTerjadwal(int $tahunAjaranId, ?int $scheduleLogId = null): int
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return 0;
        }

        $row = $this->db->table($this->table)
            ->select('COUNT(DISTINCT guru_id) AS cnt', false)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('schedule_log_id', $logId)
            ->get()
            ->getRowArray();

        return (int) ($row['cnt'] ?? 0);
    }

    public function resolveScheduleLogId(int $tahunAjaranId, ?int $scheduleLogId = null): ?int
    {
        if ($scheduleLogId !== null && $scheduleLogId > 0) {
            return $scheduleLogId;
        }

        $ta = $this->db->table('tahun_ajaran')->where('id', $tahunAjaranId)->get()->getRowArray();
        $published = (int) ($ta['published_schedule_log_id'] ?? 0);

        return $published > 0 ? $published : null;
    }

    /**
     * Published schedule that has been approved by Kepala Sekolah (for Guru).
     */
    public function resolveApprovedScheduleLogId(int $tahunAjaranId, ?int $scheduleLogId = null): ?int
    {
        if ($scheduleLogId !== null && $scheduleLogId > 0) {
            return $scheduleLogId;
        }

        $published = $this->resolveScheduleLogId($tahunAjaranId);
        if ($published === null) {
            return null;
        }

        $log = $this->db->table('schedule_logs')
            ->select('id, approval_status')
            ->where('id', $published)
            ->get()
            ->getRowArray();

        if (! $log || ($log['approval_status'] ?? null) !== 'approved') {
            return null;
        }

        return (int) $log['id'];
    }

    /**
     * Latest draft or any log for kurikulum preview when nothing published.
     */
    public function resolveKurikulumLogId(int $tahunAjaranId, ?int $scheduleLogId = null): ?int
    {
        if ($scheduleLogId !== null && $scheduleLogId > 0) {
            return $scheduleLogId;
        }

        $published = $this->resolveScheduleLogId($tahunAjaranId);
        if ($published !== null) {
            return $published;
        }

        $row = $this->db->table('schedule_logs')
            ->select('id')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->whereIn('status', ['completed', 'partial'])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getJamMengajarReport(int $tahunAjaranId, ?int $guruId = null, ?int $mapelId = null, ?int $scheduleLogId = null): array
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return [];
        }

        $builder = $this->db->table($this->table)
            ->select('jadwal.guru_id, jadwal.mapel_id, users.nip, users.nama AS nama_guru,
                      mapel.kode AS mapel_kode, mapel.nama AS mapel_nama,
                      COUNT(jadwal.id) AS total_jp', false)
            ->join('guru', 'guru.id = jadwal.guru_id')
            ->join('users', 'users.id = guru.user_id')
            ->join('mapel', 'mapel.id = jadwal.mapel_id')
            ->where('jadwal.tahun_ajaran_id', $tahunAjaranId)
            ->where('jadwal.schedule_log_id', $logId)
            ->where('guru.deleted_at IS NULL')
            ->groupBy('jadwal.guru_id, jadwal.mapel_id, users.nip, users.nama, mapel.kode, mapel.nama')
            ->orderBy('users.nama', 'ASC')
            ->orderBy('mapel.nama', 'ASC');

        if ($guruId !== null && $guruId > 0) {
            $builder->where('jadwal.guru_id', $guruId);
        }
        if ($mapelId !== null && $mapelId > 0) {
            $builder->where('jadwal.mapel_id', $mapelId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getJamMengajarPerHari(int $tahunAjaranId, int $guruId, ?int $scheduleLogId = null): array
    {
        $logId = $this->resolveScheduleLogId($tahunAjaranId, $scheduleLogId);
        if ($logId === null) {
            return [];
        }

        return $this->db->table($this->table)
            ->select('jadwal.hari_id, hari.nama AS hari_nama, hari.urutan,
                      jadwal.mapel_id, mapel.kode AS mapel_kode, mapel.nama AS mapel_nama,
                      COUNT(jadwal.id) AS total_jp', false)
            ->join('hari', 'hari.id = jadwal.hari_id')
            ->join('mapel', 'mapel.id = jadwal.mapel_id')
            ->where('jadwal.tahun_ajaran_id', $tahunAjaranId)
            ->where('jadwal.schedule_log_id', $logId)
            ->where('jadwal.guru_id', $guruId)
            ->groupBy('jadwal.hari_id, hari.nama, hari.urutan, jadwal.mapel_id, mapel.kode, mapel.nama')
            ->orderBy('hari.urutan', 'ASC')
            ->orderBy('mapel.nama', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function getBaseQuery(int $tahunAjaranId, int $scheduleLogId)
    {
        return $this->select('jadwal.*,
                              mapel.nama as mapel_nama, mapel.warna as mapel_warna,
                              users.nama as guru_nama,
                              kelas.nama as kelas_nama,
                              ruangan.kode as ruangan_kode, ruangan.nama as ruangan_nama, ruangan.tipe as ruangan_tipe,
                              hari.nama as hari_nama, hari.kode as hari_kode,
                              timeslot.jam_ke, timeslot.waktu_mulai, timeslot.waktu_selesai, timeslot.tipe as timeslot_tipe')
            ->join('mapel', 'mapel.id = jadwal.mapel_id')
            ->join('guru', 'guru.id = jadwal.guru_id')
            ->join('users', 'users.id = guru.user_id')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->join('ruangan', 'ruangan.id = jadwal.ruangan_id', 'left')
            ->join('hari', 'hari.id = jadwal.hari_id')
            ->join('timeslot', 'timeslot.id = jadwal.timeslot_id')
            ->where('jadwal.tahun_ajaran_id', $tahunAjaranId)
            ->where('jadwal.schedule_log_id', $scheduleLogId)
            ->orderBy('hari.urutan', 'ASC')
            ->orderBy('timeslot.jam_ke', 'ASC');
    }
}
