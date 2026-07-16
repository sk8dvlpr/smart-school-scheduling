<?php

namespace App\Models;

use CodeIgniter\Model;

class GuruPreferensiModel extends Model
{
    protected $table            = 'guru_preferensi';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'guru_id', 'hari_id', 'timeslot_id', 'tipe', 'bobot',
    ];
    protected $useTimestamps = true;

    public function getByGuru(int $guruId): array
    {
        return $this->where('guru_id', $guruId)
            ->orderBy('hari_id', 'ASC')
            ->orderBy('timeslot_id', 'ASC')
            ->findAll();
    }

    /**
     * Build UI state per hari from DB rows.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $hariList
     * @return array<int, array{mode: string, bobot: int, slots: array<int, string>}>
     */
    public function toFormState(array $rows, array $hariList): array
    {
        $state = [];
        foreach ($hariList as $h) {
            $hid = (int) $h['id'];
            $state[$hid] = [
                'mode'  => 'none',
                'bobot' => 5,
                'slots' => [],
            ];
        }

        foreach ($rows as $row) {
            $hid = isset($row['hari_id']) && $row['hari_id'] !== '' && $row['hari_id'] !== null
                ? (int) $row['hari_id']
                : 0;
            if ($hid <= 0 || ! isset($state[$hid])) {
                continue;
            }
            $tipe  = ($row['tipe'] ?? 'prefer') === 'avoid' ? 'avoid' : 'prefer';
            $bobot = max(1, min(10, (int) ($row['bobot'] ?? 5)));
            $tsId  = isset($row['timeslot_id']) && $row['timeslot_id'] !== '' && $row['timeslot_id'] !== null
                ? (int) $row['timeslot_id']
                : 0;

            if ($tsId > 0) {
                $state[$hid]['slots'][$tsId] = $tipe;
            } else {
                $state[$hid]['mode']  = $tipe;
                $state[$hid]['bobot'] = $bobot;
            }
        }

        return $state;
    }

    /**
     * Parse POST day cards into DB rows for replaceForGuru.
     *
     * Expected POST:
     * day[hari_id][mode] = none|prefer|avoid
     * day[hari_id][bobot] = 1..10
     * day[hari_id][slot][timeslot_id] = none|prefer|avoid
     *
     * @param array<string|int, mixed> $dayPost
     * @return list<array{hari_id: int, timeslot_id: ?int, tipe: string, bobot: int}>
     */
    public function fromFormPost(array $dayPost): array
    {
        $out = [];
        foreach ($dayPost as $hariId => $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            $hid = (int) $hariId;
            if ($hid <= 0) {
                continue;
            }
            $mode  = (string) ($cfg['mode'] ?? 'none');
            $bobot = max(1, min(10, (int) ($cfg['bobot'] ?? 5)));

            if ($mode === 'prefer' || $mode === 'avoid') {
                $out[] = [
                    'hari_id'     => $hid,
                    'timeslot_id' => null,
                    'tipe'        => $mode,
                    'bobot'       => $bobot,
                ];
            }

            $slots = $cfg['slot'] ?? [];
            if (! is_array($slots)) {
                continue;
            }
            foreach ($slots as $tsId => $slotMode) {
                $tid = (int) $tsId;
                $sm  = (string) $slotMode;
                if ($tid <= 0 || ($sm !== 'prefer' && $sm !== 'avoid')) {
                    continue;
                }
                $out[] = [
                    'hari_id'     => $hid,
                    'timeslot_id' => $tid,
                    'tipe'        => $sm,
                    'bobot'       => $bobot,
                ];
            }
        }

        return $out;
    }

    public function replaceForGuru(int $guruId, array $rows): void
    {
        $this->where('guru_id', $guruId)->delete();
        foreach ($rows as $row) {
            if (empty($row['hari_id']) && empty($row['timeslot_id'])) {
                continue;
            }
            $this->insert([
                'guru_id'     => $guruId,
                'hari_id'     => $row['hari_id'] ?: null,
                'timeslot_id' => $row['timeslot_id'] ?: null,
                'tipe'        => ($row['tipe'] ?? 'prefer') === 'avoid' ? 'avoid' : 'prefer',
                'bobot'       => max(1, min(10, (int) ($row['bobot'] ?? 5))),
            ]);
        }
    }
}
