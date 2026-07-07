<?php
/**
 * Timetable v3 — classic grid table: rows = slot positions, columns = days.
 *
 * @var array    $jadwal
 * @var array    $hari
 * @var array    $timeslotsByHari  [hari_id => slots[]]
 * @var string   $viewType         kelas|guru|ruangan
 * @var string   $title
 * @var int|null $totalJp
 * @var bool     $isExport
 */

$isExport       = $isExport ?? false;
$totalJp        = $totalJp ?? null;
$editable       = $editable ?? false;
$kelasId        = $kelasId ?? null;
$timeslotsByHari = $timeslotsByHari ?? [];

// Index jadwal by [hari_id][timeslot_id]
$jadwalIndex = [];
foreach ($jadwal as $j) {
    $jadwalIndex[(int) $j['hari_id']][(int) $j['timeslot_id']] = $j;
}

// Normalize slots per hari into zero-indexed arrays
$slotsByHariIdx = [];
$maxSlots = 0;
foreach ($hari as $h) {
    $hId   = (int) $h['id'];
    $slots = array_values($timeslotsByHari[$hId] ?? []);
    $slotsByHariIdx[$hId] = $slots;
    if (count($slots) > $maxSlots) {
        $maxSlots = count($slots);
    }
}

// timeslot_id → row index in each day's slot list
$slotRowIdx = [];
foreach ($hari as $h) {
    $hId = (int) $h['id'];
    foreach ($slotsByHariIdx[$hId] as $idx => $slot) {
        $slotRowIdx[$hId][(int) $slot['id']] = $idx;
    }
}

// Pre-calculate rowspan (blok_group) and cells to skip
if (! function_exists('timetableIndicesAreAdjacentJp')) {
    /** Adjacent table rows that are both JP slots (no istirahat/kegiatan between). */
    function timetableIndicesAreAdjacentJp(array $slots, int $a, int $b): bool
    {
        if ($b !== $a + 1) {
            return false;
        }

        return ($slots[$a]['tipe'] ?? '') === 'jp' && ($slots[$b]['tipe'] ?? '') === 'jp';
    }
}

$rowspanMap    = []; // [hariId][rowIdx] => table rows spanned
$blokJpCountMap = []; // [hariId][rowIdx] => JP count for badge
$skipCell      = []; // [hariId][rowIdx] => covered by rowspan above

$blokMembers = []; // [hariId][blok_group][] = jadwal row
foreach ($jadwal as $j) {
    $bg = $j['blok_group'] ?? '';
    if ($bg === '' || $bg === null) {
        continue;
    }
    $hId = (int) $j['hari_id'];
    $blokMembers[$hId][$bg][] = $j;
}

foreach ($hari as $h) {
    $hId   = (int) $h['id'];
    $slots = $slotsByHariIdx[$hId];

    $flushRun = static function (array $run) use (&$rowspanMap, &$blokJpCountMap, &$skipCell, $hId): void {
        if (count($run) < 2) {
            return;
        }
        $start     = $run[0];
        $tableSpan = end($run) - $start + 1;
        $rowspanMap[$hId][$start]     = max($rowspanMap[$hId][$start] ?? 1, $tableSpan);
        $blokJpCountMap[$hId][$start] = count($run);
        for ($r = $start + 1; $r < $start + $tableSpan; $r++) {
            $skipCell[$hId][$r] = true;
        }
    };

    foreach ($blokMembers[$hId] ?? [] as $members) {
        $indices = [];
        foreach ($members as $m) {
            $ts = (int) $m['timeslot_id'];
            if (isset($slotRowIdx[$hId][$ts])) {
                $indices[] = $slotRowIdx[$hId][$ts];
            }
        }
        $indices = array_values(array_unique($indices));
        sort($indices);
        if (count($indices) < 2) {
            continue;
        }

        $run = [$indices[0]];
        for ($i = 1, $n = count($indices); $i < $n; $i++) {
            $prev = end($run);
            $curr = $indices[$i];
            if (timetableIndicesAreAdjacentJp($slots, $prev, $curr)) {
                $run[] = $curr;
                continue;
            }
            $flushRun($run);
            $run = [$curr];
        }
        $flushRun($run);
    }
}
?>

<div class="timetable-wrapper">
    <?php if ($isExport): ?>
        <h5 class="mb-3 text-center fw-bold">Jadwal: <?= esc($title) ?></h5>
    <?php endif; ?>

    <?php if ($viewType === 'guru' && $totalJp !== null): ?>
        <div class="mb-3">
            <span class="badge bg-primary fs-6">
                <i class="bi bi-clock me-1"></i>Total <?= (int) $totalJp ?> JP/minggu
            </span>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="timetable-table">
            <thead>
                <tr>
                    <th class="tt-th-jp">JP</th>
                    <th class="tt-th-time">Waktu</th>
                    <?php foreach ($hari as $h):
                        $hId    = (int) $h['id'];
                        $slots  = $slotsByHariIdx[$hId];
                        $jpCnt  = count(array_filter($slots, fn ($s) => ($s['tipe'] ?? '') === 'jp'));
                    ?>
                        <th class="tt-th-day">
                            <?= esc($h['nama']) ?>
                            <span class="badge bg-white bg-opacity-25 ms-1" style="font-size:0.65rem;"><?= $jpCnt ?> JP</span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($row = 0; $row < $maxSlots; $row++):
                    // Use first day's slot at this position as row reference
                    $ref = null;
                    foreach ($hari as $h) {
                        $hId = (int) $h['id'];
                        if (isset($slotsByHariIdx[$hId][$row])) {
                            $ref = $slotsByHariIdx[$hId][$row];
                            break;
                        }
                    }
                    if (! $ref) {
                        continue;
                    }
                    $refTipe = $ref['tipe'] ?? 'jp';

                    $trClass = match ($refTipe) {
                        'istirahat'      => 'tt-row-break',
                        'kegiatan_khusus' => 'tt-row-kegiatan',
                        default          => '',
                    };
                ?>
                <tr class="<?= $trClass ?>">
                    <!-- JP / row-type column -->
                    <td class="tt-td-jp">
                        <?php if ($refTipe === 'jp'): ?>
                            <span class="tt-jp-num"><?= (int) ($ref['jam_ke'] ?? $row + 1) ?></span>
                        <?php elseif ($refTipe === 'istirahat'): ?>
                            <i class="bi bi-cup-hot tt-icon-break"></i>
                        <?php else: ?>
                            <i class="bi bi-flag tt-icon-kegiatan"></i>
                        <?php endif; ?>
                    </td>

                    <!-- Time column (from reference day) -->
                    <td class="tt-td-time">
                        <?= esc(substr($ref['waktu_mulai'], 0, 5) . '–' . substr($ref['waktu_selesai'], 0, 5)) ?>
                    </td>

                    <!-- Day columns -->
                    <?php foreach ($hari as $h):
                        $hId     = (int) $h['id'];
                        $daySlot = $slotsByHariIdx[$hId][$row] ?? null;

                        // Cell skipped — covered by a rowspan from a previous row
                        if (isset($skipCell[$hId][$row])) {
                            continue;
                        }

                        if (! $daySlot):
                    ?>
                            <td class="tt-td-empty"></td>
                        <?php continue; endif;

                        $tipe    = $daySlot['tipe'] ?? 'jp';
                        $span    = $rowspanMap[$hId][$row] ?? 1;
                        $jpCount = $blokJpCountMap[$hId][$row] ?? 1;
                        $rsAttr  = $span > 1 ? " rowspan=\"{$span}\"" : '';
                        $timeStr = substr($daySlot['waktu_mulai'], 0, 5) . '–' . substr($daySlot['waktu_selesai'], 0, 5);
                    ?>

                        <?php if ($tipe === 'istirahat'): ?>
                            <td class="tt-td-break"<?= $rsAttr ?>>
                                <div class="tt-break-inner">
                                    <span class="tt-break-label"><?= esc(strtoupper($daySlot['keterangan'] ?? 'ISTIRAHAT')) ?></span>
                                    <span class="tt-break-time"><?= esc($timeStr) ?></span>
                                </div>
                            </td>

                        <?php elseif ($tipe === 'kegiatan_khusus'): ?>
                            <td class="tt-td-kegiatan"<?= $rsAttr ?>>
                                <div class="tt-kegiatan-inner">
                                    <span class="tt-kegiatan-label"><?= esc(strtoupper($daySlot['keterangan'] ?? 'KEGIATAN KHUSUS')) ?></span>
                                    <span class="tt-break-time"><?= esc($timeStr) ?></span>
                                </div>
                            </td>

                        <?php else:
                            $cell = $jadwalIndex[$hId][(int) $daySlot['id']] ?? null;
                            $cellBg = $cell ? esc($cell['mapel_warna']) : '';
                        ?>
                            <td class="tt-td-cell<?= $span > 1 ? ' tt-td-merged' : '' ?><?= ($editable && $viewType === 'kelas') ? ' tt-td-editable' : '' ?>"<?= $rsAttr ?>>
                                <?php if ($cell): ?>
                                    <?php
                                        $cellEditable = $editable && $viewType === 'kelas' && ! empty($cell['id']);
                                        $subjectClasses = 'tt-subject' . ($span > 1 ? ' tt-subject--spanned' : '');
                                        if ($cellEditable) {
                                            $subjectClasses .= ' tt-subject--swappable';
                                        }
                                    ?>
                                    <div class="<?= $subjectClasses ?>"
                                        style="background-color:<?= $cellBg ?>;<?= $span > 1 ? '--tt-span:' . $span . ';' : '' ?>"
                                        title="<?= esc($cell['mapel_nama']) ?>"
                                        <?php if ($cellEditable): ?>
                                        data-jadwal-id="<?= (int) $cell['id'] ?>"
                                        data-kelas-id="<?= (int) $kelasId ?>"
                                        data-mapel-nama="<?= esc($cell['mapel_nama']) ?>"
                                        data-guru-nama="<?= esc($cell['guru_nama']) ?>"
                                        <?php endif; ?>>
                                        <?php if ($cellEditable): ?>
                                            <div class="tt-action-bar">
                                                <button type="button"
                                                    class="tt-swap-pick-btn"
                                                    data-jadwal-id="<?= (int) $cell['id'] ?>"
                                                    data-kelas-id="<?= (int) $kelasId ?>"
                                                    data-mapel-nama="<?= esc($cell['mapel_nama']) ?>"
                                                    data-guru-nama="<?= esc($cell['guru_nama']) ?>"
                                                    title="Tukar jadwal"
                                                    aria-label="Tukar jadwal">
                                                    <i class="bi bi-arrow-left-right"></i>
                                                </button>
                                                <button type="button"
                                                    class="tt-delete-btn"
                                                    data-jadwal-id="<?= (int) $cell['id'] ?>"
                                                    data-kelas-id="<?= (int) $kelasId ?>"
                                                    title="Hapus jadwal"
                                                    aria-label="Hapus jadwal">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($jpCount > 1): ?>
                                            <span class="tt-blok-badge"><?= $jpCount ?> JP</span>
                                        <?php endif; ?>
                                        <div class="tt-subject-name"><?= esc($cell['mapel_nama']) ?></div>
                                        <?php if ($viewType === 'kelas'): ?>
                                            <div class="tt-subject-sub"><?= esc($cell['guru_nama']) ?></div>
                                            <?php if (($cell['ruangan_tipe'] ?? '') === 'lab'): ?>
                                                <div class="tt-lab-badge"><i class="bi bi-door-open-fill"></i> <?= esc($cell['ruangan_kode']) ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($viewType === 'guru'): ?>
                                            <div class="tt-subject-sub"><?= esc($cell['kelas_nama']) ?></div>
                                            <div class="tt-subject-room"><i class="bi bi-geo-alt-fill"></i> <?= esc($cell['ruangan_kode'] ?? $cell['ruangan_nama'] ?? '-') ?></div>
                                        <?php elseif ($viewType === 'ruangan'): ?>
                                            <div class="tt-subject-sub"><?= esc($cell['kelas_nama']) ?></div>
                                            <div class="tt-subject-sub"><?= esc($cell['guru_nama']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php if ($editable && $viewType === 'kelas' && $kelasId): ?>
                                        <div class="tt-empty-cell tt-empty-cell--editable"
                                            role="button"
                                            tabindex="0"
                                            data-hari-id="<?= $hId ?>"
                                            data-timeslot-id="<?= (int) $daySlot['id'] ?>"
                                            data-kelas-id="<?= (int) $kelasId ?>"
                                            title="Klik untuk menambah mapel">
                                            <i class="bi bi-plus-lg tt-empty-add-icon"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="tt-empty-cell"></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>
