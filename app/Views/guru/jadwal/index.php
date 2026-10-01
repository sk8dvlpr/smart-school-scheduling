<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title">Jadwal Mengajar Anda</h1>
        <p class="s3-page-desc">
            Tahun Ajaran: <?= $active_ta ? esc($active_ta['nama']) . ' (' . ucfirst($active_ta['semester']) . ')' : '-' ?>
            <?php if (! empty($total_jp)): ?>
                · <span class="badge bg-primary"><?= (int) $total_jp ?> JP/minggu</span>
            <?php endif; ?>
        </p>
    </div>
    <?php if ($active_ta && !empty($jadwal)): ?>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('guru/jadwal/export/pdf') ?>" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-pdf"></i> Export PDF
        </a>
        <a href="<?= base_url('guru/jadwal/export/excel') ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-excel"></i> Export Excel
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger mb-0"><?= esc($error) ?></div>
        <?php elseif (empty($jadwal)): ?>
            <div class="alert alert-warning mb-3 border-0 bg-warning-subtle text-center py-3">
                <i class="bi bi-calendar-x me-1"></i>
                Belum ada jadwal mengajar pada tahun ajaran ini — grid di bawah menampilkan struktur jam pelajaran.
            </div>
            <?= view('components/timetable', [
                'jadwal' => [],
                'hari' => $hari,
                'timeslotsByHari' => $timeslotsByHari,
                'viewType' => 'guru',
                'title' => $user['nama'] ?? session()->get('nama'),
                'totalJp' => 0,
            ]) ?>
        <?php else: ?>
            <?= view('components/timetable', [
                'jadwal' => $jadwal,
                'hari' => $hari,
                'timeslotsByHari' => $timeslotsByHari,
                'viewType' => 'guru',
                'title' => $user['nama'] ?? session()->get('nama'),
                'totalJp' => $total_jp ?? null,
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
