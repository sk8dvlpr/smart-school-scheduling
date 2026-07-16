<?php

namespace App\Libraries;

use App\Models\JadwalModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * Manual add/delete/swap jadwal rows (Kurikulum correction) scoped per schedule_log_id.
 */
class JadwalManualService
{
    protected JadwalModel $jadwalModel;

    public function __construct(?JadwalModel $jadwalModel = null)
    {
        $this->jadwalModel = $jadwalModel ?? new JadwalModel();
    }

    /**
     * @return array{success:bool, message:string, mapel_options?:array, blocked_mapel?:array, slot_label?:string, slot_codes?:array}
     */
    public function getOptions(int $tahunAjaranId, int $scheduleLogId, int $kelasId, int $hariId, int $timeslotId): array
    {
        $validator = new JadwalPlacementValidator($tahunAjaranId, $scheduleLogId);
        $report    = $validator->getMapelSlotReport($kelasId, $hariId, $timeslotId);

        if ($report['slot_codes'] !== []) {
            return [
                'success'       => true,
                'message'       => $report['slot_message'],
                'slot_label'    => $validator->slotLabel($hariId, $timeslotId),
                'mapel_options' => [],
                'blocked_mapel' => [],
                'slot_codes'    => $report['slot_codes'],
            ];
        }

        $eligible = $report['eligible'];
        $blocked  = $report['blocked'];

        return [
            'success'       => true,
            'message'       => $eligible === [] && $blocked === []
                ? 'Tidak ada mapel kurikulum untuk rombel ini.'
                : 'OK',
            'slot_label'    => $validator->slotLabel($hariId, $timeslotId),
            'mapel_options' => $eligible,
            'blocked_mapel' => $blocked,
            'slot_codes'    => [],
        ];
    }

    /**
     * @return array{success:bool, message:string, jadwal_id?:int}
     */
    public function place(
        int $tahunAjaranId,
        int $scheduleLogId,
        int $kelasId,
        int $hariId,
        int $timeslotId,
        int $kelasMapelId,
        int $guruId
    ): array {
        $validator = new JadwalPlacementValidator($tahunAjaranId, $scheduleLogId);
        $check = $validator->validatePlace([
            'kelas_id'       => $kelasId,
            'hari_id'        => $hariId,
            'timeslot_id'    => $timeslotId,
            'kelas_mapel_id' => $kelasMapelId,
            'guru_id'        => $guruId,
        ]);

        if (! $check['valid']) {
            return ['success' => false, 'message' => $check['message']];
        }

        try {
            $id = $this->jadwalModel->insert([
                'tahun_ajaran_id' => $tahunAjaranId,
                'schedule_log_id' => $scheduleLogId,
                'kelas_mapel_id'  => $kelasMapelId,
                'hari_id'         => $hariId,
                'timeslot_id'     => $timeslotId,
                'kelas_id'        => $kelasId,
                'guru_id'         => $guruId,
                'mapel_id'        => (int) $check['mapel_id'],
                'ruangan_id'      => (int) $check['ruangan_id'],
                'blok_group'      => $check['blok_group'],
                'is_manual'       => 1,
            ]);

            return [
                'success'   => true,
                'message'   => 'Jadwal berhasil ditambahkan.',
                'jadwal_id' => (int) $id,
            ];
        } catch (DatabaseException $e) {
            return [
                'success' => false,
                'message' => $this->friendlyDbError($e->getMessage()),
            ];
        }
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function delete(int $tahunAjaranId, int $scheduleLogId, int $jadwalId, int $kelasId): array
    {
        $row = $this->jadwalModel->findForManual($jadwalId, $tahunAjaranId, $scheduleLogId);
        if (! $row) {
            return ['success' => false, 'message' => 'Jadwal tidak ditemukan.'];
        }
        if ((int) $row['kelas_id'] !== $kelasId) {
            return ['success' => false, 'message' => 'Jadwal tidak termasuk rombel ini.'];
        }

        $this->jadwalModel->delete($jadwalId);

        return ['success' => true, 'message' => 'Jadwal berhasil dihapus.'];
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function swapSlots(int $tahunAjaranId, int $scheduleLogId, int $jadwalIdA, int $jadwalIdB, int $kelasId): array
    {
        return $this->performSwap($tahunAjaranId, $scheduleLogId, $jadwalIdA, $jadwalIdB, $kelasId, 'slots');
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function swapMapel(int $tahunAjaranId, int $scheduleLogId, int $jadwalIdA, int $jadwalIdB, int $kelasId): array
    {
        return $this->performSwap($tahunAjaranId, $scheduleLogId, $jadwalIdA, $jadwalIdB, $kelasId, 'mapel');
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function swapGuru(int $tahunAjaranId, int $scheduleLogId, int $jadwalIdA, int $jadwalIdB, int $kelasId): array
    {
        return $this->performSwap($tahunAjaranId, $scheduleLogId, $jadwalIdA, $jadwalIdB, $kelasId, 'guru');
    }

    /**
     * @return array{success:bool, message:string}
     */
    protected function performSwap(
        int $tahunAjaranId,
        int $scheduleLogId,
        int $jadwalIdA,
        int $jadwalIdB,
        int $kelasId,
        string $mode
    ): array {
        $rowA = $this->jadwalModel->findForManual($jadwalIdA, $tahunAjaranId, $scheduleLogId);
        $rowB = $this->jadwalModel->findForManual($jadwalIdB, $tahunAjaranId, $scheduleLogId);

        if (! $rowA || ! $rowB) {
            return ['success' => false, 'message' => 'Salah satu jadwal tidak ditemukan.'];
        }
        if ((int) $rowA['kelas_id'] !== $kelasId || (int) $rowB['kelas_id'] !== $kelasId) {
            return ['success' => false, 'message' => 'Kedua jadwal harus dari rombel yang sama.'];
        }

        $validator = new JadwalPlacementValidator($tahunAjaranId, $scheduleLogId);
        $check = match ($mode) {
            'slots' => $validator->validateSwapSlots($rowA, $rowB),
            'mapel' => $validator->validateSwapMapel($rowA, $rowB),
            'guru'  => $validator->validateSwapGuru($rowA, $rowB),
            default => ['valid' => false, 'message' => 'Mode swap tidak valid.'],
        };

        if (! $check['valid']) {
            return ['success' => false, 'message' => $check['message']];
        }

        $newA = $rowA;
        $newB = $rowB;
        if ($mode === 'slots') {
            $newA['hari_id']     = $rowB['hari_id'];
            $newA['timeslot_id'] = $rowB['timeslot_id'];
            $newB['hari_id']     = $rowA['hari_id'];
            $newB['timeslot_id'] = $rowA['timeslot_id'];
        } elseif ($mode === 'mapel') {
            foreach (['kelas_mapel_id', 'mapel_id', 'guru_id', 'ruangan_id'] as $f) {
                $tmp = $newA[$f];
                $newA[$f] = $newB[$f];
                $newB[$f] = $tmp;
            }
        } else {
            $tmp = $newA['guru_id'];
            $newA['guru_id'] = $newB['guru_id'];
            $newB['guru_id'] = $tmp;
        }

        $db = \Config\Database::connect();
        $db->transStart();
        $this->jadwalModel->update($jadwalIdA, [
            'hari_id'        => $newA['hari_id'],
            'timeslot_id'    => $newA['timeslot_id'],
            'kelas_mapel_id' => $newA['kelas_mapel_id'],
            'mapel_id'       => $newA['mapel_id'],
            'guru_id'        => $newA['guru_id'],
            'ruangan_id'     => $newA['ruangan_id'],
            'is_manual'      => 1,
        ]);
        $this->jadwalModel->update($jadwalIdB, [
            'hari_id'        => $newB['hari_id'],
            'timeslot_id'    => $newB['timeslot_id'],
            'kelas_mapel_id' => $newB['kelas_mapel_id'],
            'mapel_id'       => $newB['mapel_id'],
            'guru_id'        => $newB['guru_id'],
            'ruangan_id'     => $newB['ruangan_id'],
            'is_manual'      => 1,
        ]);
        $db->transComplete();

        if (! $db->transStatus()) {
            return ['success' => false, 'message' => 'Gagal menyimpan swap jadwal.'];
        }

        return ['success' => true, 'message' => 'Swap jadwal berhasil.'];
    }

    protected function friendlyDbError(string $msg): string
    {
        if (stripos($msg, 'jadwal_guru_conflict') !== false) {
            return 'Konflik guru: guru sudah mengajar di slot ini (HC-1).';
        }
        if (stripos($msg, 'jadwal_kelas_conflict') !== false) {
            return 'Konflik rombel: slot sudah terisi (HC-2).';
        }
        if (stripos($msg, 'jadwal_ruangan_conflict') !== false) {
            return 'Konflik ruangan: ruangan sudah dipakai di slot ini (HC-3).';
        }

        return 'Gagal menyimpan jadwal: ' . $msg;
    }
}
