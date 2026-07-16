<h5 class="fw-bold mb-3">Jadwal Rombel: <?= esc($kelas['nama']) ?></h5>

<?php $editable = $editable ?? true; ?>
<?php if ($editable): ?>
<div class="alert alert-info border-0 py-2 mb-3 small">
    <i class="bi bi-pencil-square me-1"></i>
    <strong>Mode koreksi manual</strong> — klik slot kosong untuk menambah, hapus/swap mapel atau guru pada sel terisi.
</div>
<?php endif; ?>

<?php if (empty($jadwal)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Belum ada jadwal untuk rombel ini<?= $editable ? ' — Anda tetap bisa menambah mapel manual ke slot kosong' : '' ?>.
    </div>
<?php endif; ?>
    <?= view('components/timetable', [
        'jadwal' => $jadwal,
        'hari' => $hari,
        'timeslotsByHari' => $timeslotsByHari,
        'viewType' => 'kelas',
        'title' => $kelas['nama'],
        'editable' => $editable,
        'kelasId' => (int) $kelas['id'],
        'scheduleLogId' => $schedule_log_id ?? null,
    ]) ?>
