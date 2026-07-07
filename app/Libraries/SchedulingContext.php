<?php

namespace App\Libraries;

/**
 * Shared scheduling helpers (v3.1): JP slots per hari, guru pool,
 * eligibility (HC-4/HC-6/HC-7).
 */
class SchedulingContext
{
    /**
     * JP slots per hari (timeslot.tipe = 'jp' only).
     *
     * @param array<int, list<array<string, mixed>>> $timeslotsByHari
     * @return array<int, list<array{id: int, jam_ke: int, slot_index: int}>>
     */
    public static function buildJpSlotsByHari(array $timeslotsByHari): array
    {
        $result = [];
        foreach ($timeslotsByHari as $hariId => $slots) {
            $jp  = [];
            $idx = 0;
            foreach ($slots as $slot) {
                if (($slot['tipe'] ?? '') !== 'jp') {
                    continue;
                }
                $jp[] = [
                    'id'         => (int) $slot['id'],
                    'jam_ke'     => (int) $slot['jam_ke'],
                    'slot_index' => $idx++,
                ];
            }
            $result[(int) $hariId] = $jp;
        }

        return $result;
    }

    /**
     * @param array<int, list<array{id: int, jam_ke: int, slot_index: int}>> $jpSlotsByHari
     */
    public static function weeklyJpCapacity(array $jpSlotsByHari): int
    {
        $total = 0;
        foreach ($jpSlotsByHari as $slots) {
            $total += count($slots);
        }

        return $total;
    }

    /**
     * @param array<int, list<array{id: int, jam_ke: int, slot_index: int}>> $jpSlotsByHari
     */
    public static function dailyJpCapacity(int $hariId, array $jpSlotsByHari): int
    {
        return count($jpSlotsByHari[$hariId] ?? []);
    }

    /**
     * @param list<array<string, mixed>> $guruMapelRows
     * @param list<array<string, mixed>> $mapelRows
     * @return array<int, list<array{guru_id: int, max_jam: int, mapel_tipe: string, mapel_jurusan_id: ?int}>>
     */
    public static function buildGuruPool(array $guruMapelRows, array $mapelRows): array
    {
        $mapelMeta = [];
        foreach ($mapelRows as $m) {
            $mapelMeta[(int) $m['id']] = $m;
        }

        $pool = [];
        foreach ($guruMapelRows as $gm) {
            $mapelId = (int) $gm['mapel_id'];
            $meta    = $mapelMeta[$mapelId] ?? [];
            $pool[$mapelId][] = [
                'guru_id'          => (int) $gm['guru_id'],
                'max_jam'          => (int) $gm['max_jam_per_minggu'],
                'mapel_tipe'       => $meta['tipe'] ?? 'umum',
                'mapel_jurusan_id' => isset($meta['jurusan_id']) ? (int) $meta['jurusan_id'] : null,
            ];
        }

        return $pool;
    }

    /**
     * HC-4 index: [guru_id][hari_id] => true when the guru is blocked that day.
     *
     * @param list<array<string, mixed>> $blokirRows
     * @return array<int, array<int, true>>
     */
    public static function buildGuruBlokirIndex(array $blokirRows): array
    {
        $index = [];
        foreach ($blokirRows as $row) {
            $index[(int) $row['guru_id']][(int) $row['hari_id']] = true;
        }

        return $index;
    }

    /**
     * Eligible guru ids for a unit on a given day, enforcing HC-4 (hari blokir),
     * HC-6 (guru_mapel membership + weekly cap), and HC-7 (kejuruan jurusan match).
     *
     * @param array<string, mixed> $unit
     * @param array<int, list<array{guru_id: int, max_jam: int, mapel_tipe: string, mapel_jurusan_id: ?int}>> $guruPool
     * @param array<int, array<int, true>> $guruBlokir
     * @param array<int, array<int, int>> $guruMapelAssigned [guru_id][mapel_id] => count
     * @return list<int> guru_ids
     */
    public static function eligibleGurus(
        array $unit,
        int $hariId,
        array $guruPool,
        array $guruBlokir,
        array $guruMapelAssigned
    ): array {
        $mapelId   = (int) $unit['mapel_id'];
        $jurusanId = (int) $unit['jurusan_id'];
        $candidates = [];

        foreach ($guruPool[$mapelId] ?? [] as $entry) {
            $guruId = $entry['guru_id'];

            if (isset($guruBlokir[$guruId][$hariId])) {
                continue; // HC-4
            }

            if ($entry['mapel_tipe'] === 'kejuruan') {
                $mj = $entry['mapel_jurusan_id'];
                if ($mj !== null && $mj !== $jurusanId) {
                    continue; // HC-7
                }
            }

            $assigned = (int) ($guruMapelAssigned[$guruId][$mapelId] ?? 0);
            if ($assigned >= $entry['max_jam']) {
                continue; // HC-6 weekly cap
            }

            $candidates[] = $guruId;
        }

        return $candidates;
    }

    /**
     * Remaining weekly cap for a guru on a mapel (HC-6). Returns the smallest
     * remaining cap across the guru's matching guru_mapel entries.
     *
     * @param array<int, list<array{guru_id: int, max_jam: int, mapel_tipe: string, mapel_jurusan_id: ?int}>> $guruPool
     * @param array<int, array<int, int>> $guruMapelAssigned
     */
    public static function remainingCap(int $guruId, int $mapelId, array $guruPool, array $guruMapelAssigned): int
    {
        $assigned = (int) ($guruMapelAssigned[$guruId][$mapelId] ?? 0);
        foreach ($guruPool[$mapelId] ?? [] as $entry) {
            if ($entry['guru_id'] === $guruId) {
                return max(0, $entry['max_jam'] - $assigned);
            }
        }

        return 0;
    }

    /**
     * @param array<int, list<array{id: int, jam_ke: int, slot_index: int}>> $jpSlotsByHari
     */
    public static function slotMeta(int $hariId, int $timeslotId, array $jpSlotsByHari): ?array
    {
        foreach ($jpSlotsByHari[$hariId] ?? [] as $slot) {
            if ((int) $slot['id'] === $timeslotId) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Lab pool per jurusan from ruangan rows (tipe=lab, jurusan_id set).
     *
     * @param list<array<string, mixed>> $ruanganRows
     * @return array<int, list<int>>
     */
    public static function buildLabPoolByJurusan(array $ruanganRows): array
    {
        $pool = [];
        foreach ($ruanganRows as $row) {
            if (($row['tipe'] ?? '') !== 'lab') {
                continue;
            }
            if (! empty($row['deleted_at'])) {
                continue;
            }
            $jurusanId = $row['jurusan_id'] ?? null;
            if ($jurusanId === null || $jurusanId === '') {
                continue;
            }
            $pool[(int) $jurusanId][] = (int) $row['id'];
        }

        return $pool;
    }

    /**
     * Preferred lab first, then remaining pool labs in stable order.
     *
     * @param list<int> $pool
     * @return list<int>
     */
    public static function orderedLabCandidates(int $preferredLabId, array $pool): array
    {
        if ($pool === []) {
            return $preferredLabId > 0 ? [$preferredLabId] : [];
        }

        $ordered = [];
        if ($preferredLabId > 0 && in_array($preferredLabId, $pool, true)) {
            $ordered[] = $preferredLabId;
        }
        foreach ($pool as $labId) {
            if ($labId !== $preferredLabId) {
                $ordered[] = $labId;
            }
        }

        return $ordered;
    }

    /**
     * Build km+hari => ruangan_id lock from existing lab assignments.
     *
     * @param array<int, array<string, mixed>> $assignments
     * @param array<int, array<string, mixed>> $units
     * @return array<int, array<int, int>>
     */
    public static function buildKmDayLabFromAssignments(array $assignments, array $units, ?int $excludeUnitId = null): array
    {
        $kmDayLab = [];
        foreach ($assignments as $unitId => $a) {
            if ($excludeUnitId !== null && (int) $unitId === $excludeUnitId) {
                continue;
            }
            $unit = $units[$unitId] ?? null;
            if (! $unit || (int) ($unit['butuh_lab'] ?? 0) !== 1) {
                continue;
            }
            $kmId      = (int) $unit['kelas_mapel_id'];
            $hariId    = (int) $a['hari_id'];
            $ruanganId = (int) ($a['ruangan_id'] ?? 0);
            if ($ruanganId > 0) {
                $kmDayLab[$kmId][$hariId] = $ruanganId;
            }
        }

        return $kmDayLab;
    }

    /**
     * Resolve lab for a butuh_lab placement (HC-LAB-JURUSAN + HC-LAB-DAY + HC-3).
     *
     * @param array<int, list<int>> $labPoolByJurusan
     * @param array<int, array<int, array<int, true>>> $labSlot
     * @param array<int, array<int, int>> $kmDayLab
     */
    public static function resolveLabForPlacement(
        int $kmId,
        int $hariId,
        int $timeslotId,
        int $preferredLabId,
        int $jurusanId,
        array $labPoolByJurusan,
        array $labSlot,
        array $kmDayLab,
        ?int $explicitRuanganId = null
    ): ?int {
        $pool = $labPoolByJurusan[$jurusanId] ?? [];
        if ($pool === []) {
            return null;
        }

        $locked = $kmDayLab[$kmId][$hariId] ?? null;
        if ($locked !== null) {
            if (! in_array($locked, $pool, true)) {
                return null;
            }
            if (isset($labSlot[$locked][$hariId][$timeslotId])) {
                return null;
            }
            if ($explicitRuanganId !== null && $explicitRuanganId > 0 && $explicitRuanganId !== $locked) {
                return null;
            }

            return $locked;
        }

        if ($explicitRuanganId !== null && $explicitRuanganId > 0) {
            if (! in_array($explicitRuanganId, $pool, true)) {
                return null;
            }
            if (isset($labSlot[$explicitRuanganId][$hariId][$timeslotId])) {
                return null;
            }

            return $explicitRuanganId;
        }

        foreach (self::orderedLabCandidates($preferredLabId, $pool) as $labId) {
            if (! isset($labSlot[$labId][$hariId][$timeslotId])) {
                return $labId;
            }
        }

        return null;
    }
}
