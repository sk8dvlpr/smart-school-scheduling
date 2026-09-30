<?php

namespace App\Libraries;

/**
 * Independent HC-1..HC-8 integrity check for a completed assignment set.
 * Used by tests and post-generate verification (does not mutate the schedule).
 */
class ScheduleIntegrityValidator
{
    /**
     * @param array<int|string, array<string, mixed>> $assignments unitId => placement
     * @param array<int|string, array<string, mixed>> $units       unitId => unit meta
     * @param array<string, mixed>                    $engine      scheduling engine context
     *
     * @return array{ok: bool, violations: list<array{code: string, message: string}>}
     */
    public function validate(array $assignments, array $units, array $engine): array
    {
        $violations = [];
        $guruSlot   = [];
        $kelasSlot  = [];
        $labSlot    = [];
        $guruMapel  = [];
        $kmDayLab   = [];
        $kmCount    = [];

        $jpSlots = $engine['jp_slots_by_hari'] ?? [];
        $jpIndex = [];
        foreach ($jpSlots as $hariId => $slots) {
            foreach ($slots as $slot) {
                $jpIndex[(int) $hariId][(int) $slot['id']] = true;
            }
        }

        foreach ($assignments as $unitId => $a) {
            if (! isset($units[$unitId])) {
                $violations[] = ['code' => 'HC-5', 'message' => "Unknown unit $unitId"];
                continue;
            }

            $unit = $units[$unitId];
            $g    = (int) $a['guru_id'];
            $h    = (int) $a['hari_id'];
            $t    = (int) $a['timeslot_id'];
            $k    = (int) $unit['kelas_id'];
            $m    = (int) $unit['mapel_id'];
            $km   = (int) $unit['kelas_mapel_id'];

            if ($jpIndex !== [] && ! isset($jpIndex[$h][$t])) {
                $violations[] = ['code' => 'HC-8', 'message' => "Non-JP slot h=$h t=$t"];
            }

            if (isset($guruSlot[$g][$h][$t])) {
                $violations[] = ['code' => 'HC-1', 'message' => "Teacher clash guru=$g h=$h t=$t"];
            }
            if (isset($kelasSlot[$k][$h][$t])) {
                $violations[] = ['code' => 'HC-2', 'message' => "Class clash kelas=$k h=$h t=$t"];
            }
            $guruSlot[$g][$h][$t]  = true;
            $kelasSlot[$k][$h][$t] = true;

            if ((int) ($unit['butuh_lab'] ?? 0) === 1) {
                $lab = (int) ($a['ruangan_id'] ?? 0);
                if ($lab <= 0) {
                    $violations[] = ['code' => 'HC-7', 'message' => "Lab unit missing ruangan unit=$unitId"];
                } else {
                    $pool = $engine['lab_pool_by_jurusan'][(int) ($unit['jurusan_id'] ?? 0)] ?? [];
                    if ($pool !== [] && ! in_array($lab, $pool, true)) {
                        $violations[] = ['code' => 'HC-7', 'message' => "Lab $lab not in jurusan pool"];
                    }
                    $locked = $kmDayLab[$km][$h] ?? null;
                    if ($locked !== null && $locked !== $lab) {
                        $violations[] = ['code' => 'HC-7', 'message' => "Lab day lock km=$km h=$h"];
                    } else {
                        $kmDayLab[$km][$h] = $lab;
                    }
                    if (isset($labSlot[$lab][$h][$t])) {
                        $violations[] = ['code' => 'HC-3', 'message' => "Lab clash lab=$lab h=$h t=$t"];
                    }
                    $labSlot[$lab][$h][$t] = true;
                }
            }

            if (isset($engine['guru_blokir'][$g][$h])) {
                $violations[] = ['code' => 'HC-4', 'message' => "Guru $g on blocked day $h"];
            }

            $guruMapel[$g][$m] = (int) ($guruMapel[$g][$m] ?? 0) + 1;
            $kmCount[$km]      = (int) ($kmCount[$km] ?? 0) + 1;
        }

        foreach ($guruMapel as $g => $mapels) {
            foreach ($mapels as $m => $cnt) {
                $cap = null;
                foreach ($engine['guru_pool'][$m] ?? [] as $e) {
                    if ((int) $e['guru_id'] === (int) $g) {
                        $cap = (int) $e['max_jam'];
                        break;
                    }
                }
                if ($cap === null) {
                    $violations[] = ['code' => 'HC-6', 'message' => "Guru $g not eligible for mapel $m"];
                } elseif ($cnt > $cap) {
                    $violations[] = ['code' => 'HC-6', 'message' => "Cap exceeded guru=$g mapel=$m ($cnt>$cap)"];
                }
            }
        }

        // HC-5: each kelas_mapel demand met when demand map provided
        $demand = $engine['kelas_mapel_demand'] ?? null;
        if (is_array($demand)) {
            foreach ($demand as $kmId => $need) {
                $have = (int) ($kmCount[(int) $kmId] ?? 0);
                if ($have < (int) $need) {
                    $violations[] = [
                        'code'    => 'HC-5',
                        'message' => "Under-scheduled km=$kmId have=$have need=$need",
                    ];
                }
            }
        }

        return [
            'ok'         => $violations === [],
            'violations' => $violations,
        ];
    }
}
