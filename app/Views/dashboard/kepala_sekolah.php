<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-4 mb-4">
    <div class="col-12 col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Tahun Ajaran Aktif</h6>
                <h4 class="mb-0 fw-bold"><?= esc($active_ta['nama'] ?? 'Belum diatur') ?></h4>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Slot Jadwal Terisi</h6>
                <h2 class="mb-0 fw-bold"><?= (int) $jadwal_count ?></h2>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Guru Terjadwal</h6>
                <h2 class="mb-0 fw-bold"><?= (int) $guru_terjadwal ?></h2>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Guru / Kelas</h6>
                <h2 class="mb-0 fw-bold"><?= (int) $total_guru ?> / <?= (int) $total_kelas ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Akses Cepat</h5>
        <?php if ($latest_log): ?>
            <p class="text-muted small mb-3">
                Generate terakhir: <?= esc($latest_log['created_at'] ?? '-') ?>
                (<?= esc($latest_log['status'] ?? '') ?>)
            </p>
        <?php elseif (! $has_jadwal): ?>
            <p class="text-muted mb-3">Jadwal sekolah belum tersedia untuk tahun ajaran aktif.</p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('kepala-sekolah/jadwal') ?>" class="btn btn-outline-primary">
                <i class="bi bi-calendar-week"></i> Lihat Jadwal
            </a>
            <a href="<?= base_url('kepala-sekolah/laporan/guru-jam') ?>" class="btn btn-outline-success">
                <i class="bi bi-bar-chart-line"></i> Laporan Jam Mengajar
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
