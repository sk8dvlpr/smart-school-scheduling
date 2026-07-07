<div class="mb-3">
    <h5 class="fw-bold mb-1">Jadwal Ruangan: <?= esc($ruangan['nama']) ?> (<?= esc($ruangan['kode']) ?>)</h5>
    <div class="d-flex align-items-center gap-2">
        <span class="badge <?= $ruangan['tipe'] == 'lab' ? 'bg-warning text-dark' : 'bg-info' ?>">
            <?= ucfirst($ruangan['tipe']) ?>
        </span>
        <span class="small text-muted">Utilisasi: <?= $used_slots ?> / <?= $max_slots ?> JP (<?= $utilization ?>%)</span>
    </div>
</div>

<?php if (empty($jadwal)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Belum ada jadwal untuk ruangan ini pada tahun ajaran aktif.
    </div>
<?php else: ?>
    <div class="progress mb-4" style="height: 10px;">
        <div class="progress-bar <?= $utilization > 80 ? 'bg-danger' : 'bg-success' ?>" role="progressbar" style="width: <?= $utilization ?>%;" aria-valuenow="<?= $utilization ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
<?php endif; ?>

    <?= view('components/timetable', [
        'jadwal' => $jadwal,
        'hari' => $hari,
        'timeslotsByHari' => $timeslotsByHari,
        'viewType' => 'ruangan',
        'title' => $ruangan['nama']
    ]) ?>
