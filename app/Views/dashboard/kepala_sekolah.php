<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title">Dashboard Kepala Sekolah</h1>
        <p class="s3-page-desc">Pantau status jadwal dan laporan jam mengajar guru.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="s3-kpi-label">Tahun Ajaran Aktif</div>
            <div class="s3-kpi-value" style="font-size:1.25rem;"><?= esc($active_ta['nama'] ?? 'Belum diatur') ?></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Slot Jadwal Terisi</div>
                    <div class="s3-kpi-value"><?= (int) $jadwal_count ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-calendar-check"></i></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Guru Terjadwal</div>
                    <div class="s3-kpi-value"><?= (int) $guru_terjadwal ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-person-check"></i></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Guru / Rombel</div>
                    <div class="s3-kpi-value"><?= (int) $total_guru ?> / <?= (int) $total_kelas ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-building"></i></span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="fw-bold mb-3" style="letter-spacing:-0.02em;">Akses Cepat</h5>
        <?php if ($latest_log): ?>
            <p class="text-muted small mb-3">
                Generate terakhir: <?= esc($latest_log['created_at'] ?? '-') ?>
                (<?= esc($latest_log['status'] ?? '') ?>)
            </p>
        <?php elseif (! $has_jadwal): ?>
            <p class="text-muted mb-3">Jadwal sekolah belum tersedia untuk tahun ajaran aktif.</p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('kepala-sekolah/jadwal') ?>" class="btn btn-primary">
                <i class="bi bi-calendar-week me-1"></i> Lihat Jadwal
            </a>
            <a href="<?= base_url('kepala-sekolah/laporan/guru-jam') ?>" class="btn btn-outline-primary">
                <i class="bi bi-bar-chart-line me-1"></i> Laporan Jam Mengajar
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
