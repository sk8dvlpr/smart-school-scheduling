<div class="mb-3">
    <h5 class="fw-bold mb-0">Jadwal Guru: <?= esc($guru['nama'] ?? '-') ?></h5>
    <span class="badge bg-primary mt-1">Total Mengajar: <?= $total_jp ?> JP/minggu</span>
</div>

<?php if (empty($jadwal)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Belum ada jadwal mengajar untuk guru ini pada tahun ajaran aktif.
    </div>
<?php endif; ?>
    <?= view('components/timetable', [
        'jadwal' => $jadwal,
        'hari' => $hari,
        'timeslotsByHari' => $timeslotsByHari,
        'viewType' => 'guru',
        'title' => $guru['nama'] ?? '',
        'totalJp' => null,
    ]) ?>
