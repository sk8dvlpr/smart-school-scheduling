<?php

namespace App\Libraries;

/**
 * ponytail: pemetaan lab per kelas — X/XI/XII + nomor paralel → lab berbeda dalam satu kolom paralel.
 */
class LabAssignment
{
    /**
     * @param list<int> $labIds urutan stabil (mis. ORDER BY kode)
     */
    public static function pickLabId(string $kelasNama, array $labIds): ?int
    {
        if ($labIds === []) {
            return null;
        }

        $nama = trim($kelasNama);
        if (! preg_match('/^(X|XI|XII)\s+/u', $nama, $tm)) {
            return $labIds[0];
        }

        $tingkatIdx = ['X' => 0, 'XI' => 1, 'XII' => 2][$tm[1]] ?? 0;
        $parallel   = 1;
        if (preg_match('/(\d+)$/u', $nama, $pm)) {
            $parallel = max(1, (int) $pm[1]);
        }

        $labIdx = (($parallel - 1) * 3 + $tingkatIdx) % count($labIds);

        return $labIds[$labIdx];
    }
}
