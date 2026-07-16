<?php

namespace App\Libraries;

use App\Models\TimeslotModel;

/**
 * HC-1..HC-8 validation for manual jadwal placement (single row insert).
 */
class JadwalPlacementValidator
{
    protected int $tahunAjaranId;
    protected ?int $scheduleLogId = null;

    /** @var array<int, list<array{id:int,jam_ke:int,slot_index:int}>> */
    protected array $jpSlotsByHari = [];
    /** @var array<int, list<array{guru_id:int,max_jam:int,mapel_tipe:string,mapel_jurusan_id:?int}>> */
    protected array $guruPool = [];
    /** @var array<int, array<int, true>> */
    protected array $guruBlokir = [];
    /** @var array<int, int> */
    protected array $homeroomMap = [];
    /** @var array<int, array<string, mixed>> */
    protected array $kelasById = [];
    /** @var array<int, array<string, mixed>> */
    protected array $kelasMapelById = [];
    /** @var array<int, list<array<string, mixed>>> kelas_id => rows */
    protected array $kelasMapelByKelas = [];
    /** @var array<int, array<string, mixed>> */
    protected array $mapelMeta = [];
    /** @var array<int, string> guru_id => nama */
    protected array $guruNames = [];

    /** @var array<int, array<int, array<int, true>>> */
    protected array $guruSlot = [];
    /** @var array<int, array<int, array<int, true>>> */
    protected array $kelasSlot = [];
    /** @var array<int, array<int, array<int, true>>> */
    protected array $labSlot = [];
    /** @var array<int, array<int, array<int, array{kelas_nama:string,guru_nama:string}>>> lab/hari/timeslot */
    protected array $labOccupant = [];
    /** @var array<int, array<int, int>> */
    protected array $guruMapelAssigned = [];
    /** @var array<int, int> kelas_mapel_id => scheduled count */
    protected array $scheduledByKelasMapel = [];
    /** @var array<int, array<int, array<int, array<int, string>>>> kelas/hari/mapel/jam_ke => blok_group */
    protected array $blokByKelasHariMapelJam = [];
    /** @var array<int, list<int>> */
    protected array $labPoolByJurusan = [];
    /** @var array<int, array<int, int>> kelas_mapel_id => hari_id => ruangan_id */
    protected array $kmDayLabFromJadwal = [];

    public function __construct(int $tahunAjaranId, ?int $scheduleLogId = null, bool $loadFromDb = true)
    {
        $this->tahunAjaranId   = $tahunAjaranId;
        $this->scheduleLogId   = $scheduleLogId;
        if ($loadFromDb) {
            $this->loadFromDatabase();
        }
    }

    /**
     * ponytail: test-only factory — avoids DB for unit tests.
     *
     * @param array<string, mixed> $ctx
     */
    public static function forTest(array $ctx): self
    {
        $v = new self((int) ($ctx['tahun_ajaran_id'] ?? 1), $ctx['schedule_log_id'] ?? null, false);
        $v->jpSlotsByHari           = $ctx['jp_slots_by_hari'] ?? [];
        $v->guruPool                = $ctx['guru_pool'] ?? [];
        $v->guruBlokir              = $ctx['guru_blokir'] ?? [];
        $v->homeroomMap             = $ctx['homeroom_map'] ?? [];
        $v->kelasById               = $ctx['kelas_by_id'] ?? [];
        $v->kelasMapelById          = $ctx['kelas_mapel_by_id'] ?? [];
        $v->kelasMapelByKelas       = $ctx['kelas_mapel_by_kelas'] ?? [];
        $v->mapelMeta               = $ctx['mapel_meta'] ?? [];
        $v->guruNames               = $ctx['guru_names'] ?? [];
        $v->scheduledByKelasMapel   = $ctx['scheduled_by_kelas_mapel'] ?? [];
        $v->labPoolByJurusan        = $ctx['lab_pool_by_jurusan'] ?? [];
        $v->rebuildIndexFromJadwalRows($ctx['jadwal_rows'] ?? []);

        return $v;
    }

    protected function loadFromDatabase(): void
    {
        $db = \Config\Database::connect();

        $timeslotModel = new TimeslotModel();
        $this->jpSlotsByHari = SchedulingContext::buildJpSlotsByHari($timeslotModel->getGroupedByHari());

        foreach ($db->table('kelas')->where('tahun_ajaran_id', $this->tahunAjaranId)->get()->getResultArray() as $k) {
            $kid = (int) $k['id'];
            $this->kelasById[$kid] = $k;
            $this->homeroomMap[$kid] = (int) $k['ruangan_id'];
        }

        foreach ($db->table('mapel')->get()->getResultArray() as $m) {
            $this->mapelMeta[(int) $m['id']] = $m;
        }

        $guruMapelRows = $db->table('guru_mapel')->get()->getResultArray();
        $this->guruPool = SchedulingContext::buildGuruPool($guruMapelRows, array_values($this->mapelMeta));

        $this->guruBlokir = SchedulingContext::buildGuruBlokirIndex(
            $db->table('guru_hari_blokir')->get()->getResultArray()
        );

        foreach ($db->table('guru')->select('guru.id, users.nama')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.deleted_at IS NULL')
            ->get()->getResultArray() as $g) {
            $this->guruNames[(int) $g['id']] = $g['nama'];
        }

        foreach ($db->table('kelas_mapel')->where('tahun_ajaran_id', $this->tahunAjaranId)->get()->getResultArray() as $km) {
            $kmId = (int) $km['id'];
            $this->kelasMapelById[$kmId] = $km;
            $this->kelasMapelByKelas[(int) $km['kelas_id']][] = $km;
        }

        $ruanganRows = $db->table('ruangan')
            ->where('deleted_at IS NULL')
            ->where('tipe', 'lab')
            ->get()
            ->getResultArray();
        $this->labPoolByJurusan = SchedulingContext::buildLabPoolByJurusan($ruanganRows);

        $jadwalBuilder = $db->table('jadwal')->where('tahun_ajaran_id', $this->tahunAjaranId);
        if ($this->scheduleLogId !== null) {
            $jadwalBuilder->where('schedule_log_id', $this->scheduleLogId);
        }
        $jadwalRows = $jadwalBuilder->get()->getResultArray();
        $this->rebuildIndexFromJadwalRows($jadwalRows);
    }

    /**
     * @param list<array<string, mixed>> $jadwalRows
     */
    protected function rebuildIndexFromJadwalRows(array $jadwalRows): void
    {
        $this->guruSlot = [];
        $this->kelasSlot = [];
        $this->labSlot = [];
        $this->labOccupant = [];
        $this->guruMapelAssigned = [];
        $this->scheduledByKelasMapel = [];
        $this->blokByKelasHariMapelJam = [];
        $this->kmDayLabFromJadwal = [];

        foreach ($jadwalRows as $row) {
            $this->indexJadwalRow($row);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function indexJadwalRow(array $row): void
    {
        $kmId = (int) $row['kelas_mapel_id'];
        $this->scheduledByKelasMapel[$kmId] = (int) ($this->scheduledByKelasMapel[$kmId] ?? 0) + 1;

        $hariId     = (int) $row['hari_id'];
        $timeslotId = (int) $row['timeslot_id'];
        $guruId     = (int) $row['guru_id'];
        $kelasId    = (int) $row['kelas_id'];
        $mapelId    = (int) $row['mapel_id'];

        $slotMeta = SchedulingContext::slotMeta($hariId, $timeslotId, $this->jpSlotsByHari);
        if ($slotMeta === null) {
            return;
        }

        $this->guruSlot[$guruId][$hariId][$timeslotId]   = true;
        $this->kelasSlot[$kelasId][$hariId][$timeslotId] = true;

        $km = $this->kelasMapelById[$kmId] ?? null;
        if ($km && (int) ($km['butuh_lab'] ?? 0) === 1) {
            $ruanganId = (int) ($row['ruangan_id'] ?? 0);
            if ($ruanganId > 0) {
                $this->labSlot[$ruanganId][$hariId][$timeslotId] = true;
                $this->kmDayLabFromJadwal[$kmId][$hariId] = $ruanganId;
                $kelas = $this->kelasById[$kelasId] ?? [];
                $this->labOccupant[$ruanganId][$hariId][$timeslotId] = [
                    'kelas_nama' => (string) ($kelas['nama'] ?? ('Rombel #' . $kelasId)),
                    'guru_nama'  => $this->guruNames[$guruId] ?? ('Guru #' . $guruId),
                ];
            }
        }

        $jamKe = (int) $slotMeta['jam_ke'];
        $this->guruMapelAssigned[$guruId][$mapelId] = (int) ($this->guruMapelAssigned[$guruId][$mapelId] ?? 0) + 1;
        if (! empty($row['blok_group'])) {
            $this->blokByKelasHariMapelJam[$kelasId][$hariId][$mapelId][$jamKe] = (string) $row['blok_group'];
        }
    }

    public function remainingJp(int $kelasMapelId): int
    {
        $km = $this->kelasMapelById[$kelasMapelId] ?? null;
        if (! $km) {
            return 0;
        }
        $demand    = (int) ($km['jam_per_minggu'] ?? 0);
        $scheduled = (int) ($this->scheduledByKelasMapel[$kelasMapelId] ?? 0);

        return max(0, $demand - $scheduled);
    }

    /**
     * @return array{eligible:list<array<string,mixed>>,blocked:list<array<string,mixed>>,slot_codes:list<string>,slot_message:string}
     */
    public function getMapelSlotReport(int $kelasId, int $hariId, int $timeslotId): array
    {
        if (isset($this->kelasSlot[$kelasId][$hariId][$timeslotId])) {
            return [
                'eligible'      => [],
                'blocked'       => [],
                'slot_codes'    => ['HC-2'],
                'slot_message'  => 'Slot rombel ini sudah terisi.',
            ];
        }

        $slotMeta = SchedulingContext::slotMeta($hariId, $timeslotId, $this->jpSlotsByHari);
        if ($slotMeta === null) {
            return [
                'eligible'      => [],
                'blocked'       => [],
                'slot_codes'    => ['HC-8'],
                'slot_message'  => 'Slot ini bukan jam pelajaran (JP).',
            ];
        }

        $eligible  = [];
        $blocked   = [];

        foreach ($this->kelasMapelByKelas[$kelasId] ?? [] as $km) {
            $eval = $this->evaluateMapelForSlot($kelasId, $hariId, $timeslotId, $km);
            if ($eval['eligible']) {
                $eligible[] = $eval['item'];
                continue;
            }
            if ($eval['include_in_blocked']) {
                $blocked[] = $eval['item'];
            }
        }

        usort($eligible, fn ($a, $b) => strcmp($a['mapel_nama'], $b['mapel_nama']));
        usort($blocked, fn ($a, $b) => strcmp($a['mapel_nama'], $b['mapel_nama']));

        return [
            'eligible'      => $eligible,
            'blocked'       => $blocked,
            'slot_codes'    => [],
            'slot_message'  => '',
        ];
    }

    /**
     * @return list<array{kelas_mapel_id:int,mapel_id:int,mapel_kode:string,mapel_nama:string,remaining:int,butuh_lab:int,gurus:list<array{guru_id:int,nama:string,remaining_cap:int}>}>
     */
    public function getEligibleMapelForSlot(int $kelasId, int $hariId, int $timeslotId): array
    {
        return $this->getMapelSlotReport($kelasId, $hariId, $timeslotId)['eligible'];
    }

    /**
     * @param array<string, mixed> $km
     * @return array{eligible:bool,include_in_blocked:bool,item:array<string,mixed>}
     */
    protected function evaluateMapelForSlot(
        int $kelasId,
        int $hariId,
        int $timeslotId,
        array $km
    ): array {
        $kmId      = (int) $km['id'];
        $mapelId   = (int) $km['mapel_id'];
        $meta      = $this->mapelMeta[$mapelId] ?? [];
        $remaining = $this->remainingJp($kmId);
        $scheduled = (int) ($this->scheduledByKelasMapel[$kmId] ?? 0);
        $demand    = (int) ($km['jam_per_minggu'] ?? 0);

        $item = [
            'kelas_mapel_id' => $kmId,
            'mapel_id'       => $mapelId,
            'mapel_kode'     => $meta['kode'] ?? '',
            'mapel_nama'     => $meta['nama'] ?? '',
            'remaining'      => $remaining,
            'demand'         => $demand,
            'scheduled'      => $scheduled,
            'butuh_lab'      => (int) ($km['butuh_lab'] ?? 0),
            'codes'          => [],
            'message'        => '',
            'gurus'          => [],
        ];

        if ($remaining < 1) {
            $item['codes']   = ['HC-5'];
            $item['message'] = "Kuota JP sudah terpenuhi ({$scheduled}/{$demand} JP).";

            return ['eligible' => false, 'include_in_blocked' => true, 'item' => $item];
        }

        $butuhLab = (int) ($km['butuh_lab'] ?? 0) === 1;
        if ($butuhLab) {
            $ruanganId = $this->resolveRuanganForPlacement($km, $kelasId, $hariId, $timeslotId);
            if ($ruanganId <= 0) {
                $item['codes']   = ['HC-7-LAB'];
                $item['message'] = 'Tidak ada lab jurusan yang tersedia di slot ini.';

                return ['eligible' => false, 'include_in_blocked' => true, 'item' => $item];
            }
            if (isset($this->labSlot[$ruanganId][$hariId][$timeslotId])) {
                $occ = $this->labOccupant[$ruanganId][$hariId][$timeslotId] ?? null;
                $item['codes']   = ['HC-3'];
                $item['message'] = $occ
                    ? "Lab dipakai rombel {$occ['kelas_nama']} ({$occ['guru_nama']}) di slot ini."
                    : 'Lab sudah dipakai rombel lain di slot ini.';
                if ($occ) {
                    $item['detail'] = $occ;
                }

                return ['eligible' => false, 'include_in_blocked' => true, 'item' => $item];
            }
        }

        $gurus = $this->collectFreeGurusForSlot($kelasId, $hariId, $timeslotId, $km);
        if ($gurus !== []) {
            $item['gurus'] = $gurus;

            return ['eligible' => true, 'include_in_blocked' => false, 'item' => $item];
        }

        $guruBlock = $this->diagnoseGuruBlock($kelasId, $hariId, $timeslotId, $km);
        $item['codes']   = $guruBlock['codes'];
        $item['message'] = $guruBlock['message'];
        if (isset($guruBlock['detail'])) {
            $item['detail'] = $guruBlock['detail'];
        }

        return ['eligible' => false, 'include_in_blocked' => true, 'item' => $item];
    }

    /**
     * @param array<string, mixed> $kelasMapelRow
     * @return list<array{guru_id:int,nama:string,remaining_cap:int}>
     */
    protected function collectFreeGurusForSlot(
        int $kelasId,
        int $hariId,
        int $timeslotId,
        array $kelasMapelRow
    ): array {
        if (isset($this->kelasSlot[$kelasId][$hariId][$timeslotId])) {
            return [];
        }

        $butuhLab = (int) ($kelasMapelRow['butuh_lab'] ?? 0) === 1;
        if ($butuhLab) {
            $ruanganId = $this->resolveRuanganForPlacement($kelasMapelRow, $kelasId, $hariId, $timeslotId);
            if ($ruanganId <= 0 || isset($this->labSlot[$ruanganId][$hariId][$timeslotId])) {
                return [];
            }
        }

        $mapelId  = (int) $kelasMapelRow['mapel_id'];
        $unit     = $this->buildUnitFromKelasMapel($kelasMapelRow, $kelasId);
        $eligible = SchedulingContext::eligibleGurus(
            $unit,
            $hariId,
            $this->guruPool,
            $this->guruBlokir,
            $this->guruMapelAssigned
        );

        $out = [];
        foreach ($eligible as $guruId) {
            if (isset($this->guruSlot[$guruId][$hariId][$timeslotId])) {
                continue;
            }
            $out[] = [
                'guru_id'       => $guruId,
                'nama'          => $this->guruNames[$guruId] ?? ('Guru #' . $guruId),
                'remaining_cap' => SchedulingContext::remainingCap($guruId, $mapelId, $this->guruPool, $this->guruMapelAssigned),
            ];
        }

        usort($out, fn ($a, $b) => ($b['remaining_cap'] <=> $a['remaining_cap']) ?: strcmp($a['nama'], $b['nama']));

        return $out;
    }

    /**
     * @param array<string, mixed> $kelasMapelRow
     * @return array{codes:list<string>,message:string,detail?:array<string,int>}
     */
    protected function diagnoseGuruBlock(
        int $kelasId,
        int $hariId,
        int $timeslotId,
        array $kelasMapelRow
    ): array {
        $mapelId = (int) $kelasMapelRow['mapel_id'];
        $pool    = $this->guruPool[$mapelId] ?? [];

        if ($pool === []) {
            return [
                'codes'   => ['HC-6'],
                'message' => 'Tidak ada guru terdaftar untuk mapel ini.',
            ];
        }

        $unit     = $this->buildUnitFromKelasMapel($kelasMapelRow, $kelasId);
        $eligible = SchedulingContext::eligibleGurus(
            $unit,
            $hariId,
            $this->guruPool,
            $this->guruBlokir,
            $this->guruMapelAssigned
        );

        if ($eligible === []) {
            $blocked = 0;
            $capped  = 0;
            $jurusan = 0;
            foreach ($pool as $entry) {
                $guruId = $entry['guru_id'];
                if (isset($this->guruBlokir[$guruId][$hariId])) {
                    $blocked++;
                    continue;
                }
                if ($entry['mapel_tipe'] === 'kejuruan') {
                    $mj = $entry['mapel_jurusan_id'];
                    if ($mj !== null && $mj !== (int) $unit['jurusan_id']) {
                        $jurusan++;
                        continue;
                    }
                }
                $assigned = (int) ($this->guruMapelAssigned[$guruId][$mapelId] ?? 0);
                if ($assigned >= $entry['max_jam']) {
                    $capped++;
                }
            }
            $n = count($pool);
            if ($blocked === $n) {
                return ['codes' => ['HC-4'], 'message' => 'Semua guru diblokir pada hari ini.'];
            }
            if ($jurusan === $n) {
                return ['codes' => ['HC-7'], 'message' => 'Mapel tidak sesuai jurusan rombel.'];
            }
            if ($capped >= $n - $blocked - $jurusan) {
                return ['codes' => ['HC-6'], 'message' => 'Cap mingguan semua guru eligible sudah habis.'];
            }

            return ['codes' => ['HC-6'], 'message' => 'Tidak ada guru eligible untuk mapel ini.'];
        }

        $busy = 0;
        foreach ($eligible as $guruId) {
            if (isset($this->guruSlot[$guruId][$hariId][$timeslotId])) {
                $busy++;
            }
        }
        $total = count($eligible);

        return [
            'codes'   => ['HC-1'],
            'message' => "Semua guru eligible bentrok di slot ini ({$busy}/{$total} sibuk).",
            'detail'  => ['busy' => $busy, 'eligible' => $total],
        ];
    }

    /**
     * @param array<string, mixed> $kelasMapelRow
     * @return list<array{guru_id:int,nama:string,remaining_cap:int}>
     */
    public function getEligibleGurusForPlacement(
        int $kelasId,
        int $hariId,
        int $timeslotId,
        array $kelasMapelRow
    ): array {
        return $this->collectFreeGurusForSlot($kelasId, $hariId, $timeslotId, $kelasMapelRow);
    }

    /**
     * @param array{kelas_id:int,hari_id:int,timeslot_id:int,kelas_mapel_id:int,guru_id:int} $payload
     * @return array{valid:bool, violations:list<string>, message:string, ruangan_id?:int, blok_group?:string, mapel_id?:int}
     */
    public function validatePlace(array $payload): array
    {
        $kelasId      = (int) ($payload['kelas_id'] ?? 0);
        $hariId       = (int) ($payload['hari_id'] ?? 0);
        $timeslotId   = (int) ($payload['timeslot_id'] ?? 0);
        $kelasMapelId = (int) ($payload['kelas_mapel_id'] ?? 0);
        $guruId       = (int) ($payload['guru_id'] ?? 0);

        $violations = [];

        $km = $this->kelasMapelById[$kelasMapelId] ?? null;
        if (! $km || (int) $km['kelas_id'] !== $kelasId) {
            return ['valid' => false, 'violations' => ['HC-5'], 'message' => 'Mata pelajaran tidak termasuk kurikulum rombel ini.'];
        }

        $slotMeta = SchedulingContext::slotMeta($hariId, $timeslotId, $this->jpSlotsByHari);
        if ($slotMeta === null) {
            $violations[] = 'HC-8';
        }

        if ($this->remainingJp($kelasMapelId) < 1) {
            $violations[] = 'HC-5';
        }

        if (isset($this->kelasSlot[$kelasId][$hariId][$timeslotId])) {
            $violations[] = 'HC-2';
        }

        if (isset($this->guruSlot[$guruId][$hariId][$timeslotId])) {
            $violations[] = 'HC-1';
        }

        $butuhLab = (int) ($km['butuh_lab'] ?? 0) === 1;
        if ($butuhLab) {
            $ruanganId = $this->resolveRuanganForPlacement($km, $kelasId, $hariId, $timeslotId);
            if ($ruanganId <= 0) {
                $violations[] = 'HC-7-LAB';
            } elseif (isset($this->labSlot[$ruanganId][$hariId][$timeslotId])) {
                $violations[] = 'HC-3';
            }
        }

        if (isset($this->guruBlokir[$guruId][$hariId])) {
            $violations[] = 'HC-4';
        }

        $unit = $this->buildUnitFromKelasMapel($km, $kelasId);
        $mapelId = (int) $km['mapel_id'];
        $eligible = SchedulingContext::eligibleGurus($unit, $hariId, $this->guruPool, $this->guruBlokir, $this->guruMapelAssigned);
        if (! in_array($guruId, $eligible, true)) {
            $violations[] = 'HC-6';
        }

        if ($violations !== []) {
            return [
                'valid'      => false,
                'violations' => $violations,
                'message'    => $this->violationMessage($violations),
            ];
        }

        return [
            'valid'       => true,
            'violations'  => [],
            'message'     => 'Valid',
            'ruangan_id'  => $this->resolveRuanganForPlacement($km, $kelasId, $hariId, $timeslotId),
            'blok_group'  => $this->resolveBlokGroup($kelasId, $hariId, $timeslotId, $mapelId),
            'mapel_id'    => $mapelId,
        ];
    }

    /**
     * Resolve ruangan for placement (homeroom or lab pool with HC-LAB-DAY lock).
     *
     * @param array<string, mixed> $kelasMapel
     */
    public function resolveRuanganForPlacement(array $kelasMapel, int $kelasId, int $hariId, int $timeslotId): int
    {
        if ((int) ($kelasMapel['butuh_lab'] ?? 0) !== 1) {
            return (int) ($this->homeroomMap[$kelasId] ?? 0);
        }

        $kmId  = (int) ($kelasMapel['id'] ?? 0);
        $kelas = $this->kelasById[$kelasId] ?? [];
        $locked = $this->kmDayLabFromJadwal[$kmId][$hariId] ?? null;
        if ($locked !== null) {
            return (int) $locked;
        }

        $resolved = SchedulingContext::resolveLabForPlacement(
            $kmId,
            $hariId,
            $timeslotId,
            (int) ($kelasMapel['lab_id'] ?? 0),
            (int) ($kelas['jurusan_id'] ?? 0),
            $this->labPoolByJurusan,
            $this->labSlot,
            $this->kmDayLabFromJadwal,
            null
        );

        return $resolved ?? 0;
    }

    /**
     * @param array<string, mixed> $kelasMapel
     * @deprecated Use resolveRuanganForPlacement with hari/timeslot context.
     */
    public function deriveRuanganId(array $kelasMapel, int $kelasId): int
    {
        if ((int) ($kelasMapel['butuh_lab'] ?? 0) === 1) {
            return (int) ($kelasMapel['lab_id'] ?? 0);
        }

        return (int) ($this->homeroomMap[$kelasId] ?? 0);
    }

    public function resolveBlokGroup(int $kelasId, int $hariId, int $timeslotId, int $mapelId): string
    {
        $slotMeta = SchedulingContext::slotMeta($hariId, $timeslotId, $this->jpSlotsByHari);
        if ($slotMeta === null) {
            return bin2hex(random_bytes(8));
        }

        $jamKe = (int) $slotMeta['jam_ke'];
        foreach ([$jamKe - 1, $jamKe + 1] as $neighborJam) {
            $bg = $this->blokByKelasHariMapelJam[$kelasId][$hariId][$mapelId][$neighborJam] ?? null;
            if ($bg) {
                return $bg;
            }
        }

        return bin2hex(random_bytes(8));
    }

    /**
     * @param array<string, mixed> $km
     * @return array<string, mixed>
     */
    protected function buildUnitFromKelasMapel(array $km, int $kelasId): array
    {
        $kelas = $this->kelasById[$kelasId] ?? [];
        $mapelId = (int) $km['mapel_id'];
        $meta = $this->mapelMeta[$mapelId] ?? [];

        return [
            'kelas_id'         => $kelasId,
            'mapel_id'         => $mapelId,
            'jurusan_id'       => (int) ($kelas['jurusan_id'] ?? 0),
            'butuh_lab'        => (int) ($km['butuh_lab'] ?? 0),
            'lab_id'           => $km['lab_id'] ?? null,
            'mapel_tipe'       => $meta['tipe'] ?? 'umum',
            'mapel_jurusan_id' => isset($meta['jurusan_id']) ? (int) $meta['jurusan_id'] : null,
        ];
    }

    /**
     * @param list<string> $violations
     */
    protected function violationMessage(array $violations): string
    {
        $labels = [
            'HC-1' => 'Guru sudah mengajar di slot ini (konflik guru).',
            'HC-2' => 'Rombel sudah memiliki jadwal di slot ini.',
            'HC-3' => 'Lab sudah dipakai rombel lain di slot ini.',
            'HC-4' => 'Guru diblokir pada hari ini.',
            'HC-5' => 'Kuota JP mapel untuk rombel ini sudah terpenuhi.',
            'HC-6' => 'Guru tidak eligible atau melebihi cap mingguan untuk mapel ini.',
            'HC-7' => 'Mapel tidak sesuai jurusan rombel.',
            'HC-7-LAB' => 'Tidak ada lab jurusan yang tersedia untuk slot ini.',
            'HC-8' => 'Slot bukan jam pelajaran (JP).',
        ];

        $msgs = [];
        foreach ($violations as $v) {
            $msgs[] = $labels[$v] ?? $v;
        }

        return implode(' ', $msgs);
    }

    public function slotLabel(int $hariId, int $timeslotId): string
    {
        $db = \Config\Database::connect();
        $hari = $db->table('hari')->where('id', $hariId)->get()->getRowArray();
        $slot = $db->table('timeslot')->where('id', $timeslotId)->get()->getRowArray();
        if (! $hari || ! $slot) {
            return 'Slot #' . $timeslotId;
        }

        $time = substr($slot['waktu_mulai'], 0, 5) . '–' . substr($slot['waktu_selesai'], 0, 5);

        return ($hari['nama'] ?? '') . ', JP ' . (int) $slot['jam_ke'] . ' (' . $time . ')';
    }

    /**
     * @param array<string, mixed> $rowA
     * @param array<string, mixed> $rowB
     * @return array{valid:bool, message:string, sc_warnings?:list<string>}
     */
    public function validateSwapSlots(array $rowA, array $rowB): array
    {
        if ((int) $rowA['kelas_id'] !== (int) $rowB['kelas_id']) {
            return ['valid' => false, 'message' => 'Swap slot hanya untuk rombel yang sama.'];
        }

        return $this->validateHypotheticalSwap($rowA, $rowB, 'slots');
    }

    /**
     * @param array<string, mixed> $rowA
     * @param array<string, mixed> $rowB
     */
    public function validateSwapMapel(array $rowA, array $rowB): array
    {
        if ((int) $rowA['kelas_id'] !== (int) $rowB['kelas_id']) {
            return ['valid' => false, 'message' => 'Swap mapel hanya untuk rombel yang sama.'];
        }

        return $this->validateHypotheticalSwap($rowA, $rowB, 'mapel');
    }

    /**
     * @param array<string, mixed> $rowA
     * @param array<string, mixed> $rowB
     */
    public function validateSwapGuru(array $rowA, array $rowB): array
    {
        return $this->validateHypotheticalSwap($rowA, $rowB, 'guru');
    }

    /**
     * @param array<string, mixed> $rowA
     * @param array<string, mixed> $rowB
     */
    protected function validateHypotheticalSwap(array $rowA, array $rowB, string $mode): array
    {
        $clone = clone $this;
        $clone->removeRowFromIndex($rowA);
        $clone->removeRowFromIndex($rowB);

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

        $checkA = $clone->validatePlace([
            'kelas_id'       => (int) $newA['kelas_id'],
            'hari_id'        => (int) $newA['hari_id'],
            'timeslot_id'    => (int) $newA['timeslot_id'],
            'kelas_mapel_id' => (int) $newA['kelas_mapel_id'],
            'guru_id'        => (int) $newA['guru_id'],
        ]);
        if (! $checkA['valid']) {
            return $checkA;
        }

        $clone->applyHypotheticalRow($newA);
        $checkB = $clone->validatePlace([
            'kelas_id'       => (int) $newB['kelas_id'],
            'hari_id'        => (int) $newB['hari_id'],
            'timeslot_id'    => (int) $newB['timeslot_id'],
            'kelas_mapel_id' => (int) $newB['kelas_mapel_id'],
            'guru_id'        => (int) $newB['guru_id'],
        ]);

        return $checkB;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function removeRowFromIndex(array $row): void
    {
        $kmId = (int) $row['kelas_mapel_id'];
        if (isset($this->scheduledByKelasMapel[$kmId])) {
            $this->scheduledByKelasMapel[$kmId]--;
            if ($this->scheduledByKelasMapel[$kmId] <= 0) {
                unset($this->scheduledByKelasMapel[$kmId]);
            }
        }

        $hariId     = (int) $row['hari_id'];
        $timeslotId = (int) $row['timeslot_id'];
        $guruId     = (int) $row['guru_id'];
        $kelasId    = (int) $row['kelas_id'];
        $mapelId    = (int) $row['mapel_id'];

        unset($this->guruSlot[$guruId][$hariId][$timeslotId]);
        unset($this->kelasSlot[$kelasId][$hariId][$timeslotId]);

        $km = $this->kelasMapelById[$kmId] ?? null;
        if ($km && (int) ($km['butuh_lab'] ?? 0) === 1) {
            $ruanganId = (int) ($row['ruangan_id'] ?? 0);
            if ($ruanganId > 0) {
                unset($this->labSlot[$ruanganId][$hariId][$timeslotId], $this->labOccupant[$ruanganId][$hariId][$timeslotId]);
            }
            if (isset($this->kmDayLabFromJadwal[$kmId][$hariId])) {
                unset($this->kmDayLabFromJadwal[$kmId][$hariId]);
            }
        }

        if (isset($this->guruMapelAssigned[$guruId][$mapelId])) {
            $this->guruMapelAssigned[$guruId][$mapelId]--;
            if ($this->guruMapelAssigned[$guruId][$mapelId] <= 0) {
                unset($this->guruMapelAssigned[$guruId][$mapelId]);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function applyHypotheticalRow(array $row): void
    {
        $this->indexJadwalRow($row);
    }
}
