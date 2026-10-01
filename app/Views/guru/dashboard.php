<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title">Dashboard Guru</h1>
        <p class="s3-page-desc">Selamat datang, <?= esc(session()->get('nama')) ?></p>
    </div>
    <a href="<?= base_url('guru/jadwal') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-calendar-week me-1"></i> Jadwal Lengkap
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Jam Mengajar</div>
                    <div class="s3-kpi-value"><?= esc($total_jp) ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-clock-history"></i></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Rombel Diajar</div>
                    <div class="s3-kpi-value"><?= esc($total_kelas) ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-diagram-3"></i></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">Jadwal Mengajar Hari Ini</h6>
                </div>
                <?php if (!$active_ta): ?>
                    <div class="alert alert-warning mb-0">Tahun ajaran belum aktif.</div>
                <?php elseif (! empty($approval_note)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-hourglass-split me-1"></i>
                        <?= esc($approval_note) ?>
                    </div>
                <?php elseif (empty($jadwal)): ?>
                    <div class="alert alert-info mb-0">
                        Belum ada jadwal mengajar pada tahun ajaran ini.
                    </div>
                <?php elseif (empty($jadwal_hari_ini)): ?>
                    <div class="s3-empty py-4">
                        <i class="bi bi-cup-hot"></i>
                        Tidak ada jadwal mengajar hari ini.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($jadwal_hari_ini as $j): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center gap-2">
                                <div>
                                    <h6 class="mb-1 fw-bold"><?= esc($j['mapel_nama']) ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-people-fill me-1"></i> Rombel <?= esc($j['kelas_nama']) ?>
                                        <span class="mx-2">|</span>
                                        <i class="bi bi-geo-alt-fill me-1"></i> <?= esc($j['ruangan_kode']) ?>
                                    </small>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <span class="badge bg-primary mb-1">Jam ke-<?= esc($j['jam_ke']) ?></span>
                                    <div class="small text-muted" style="font-size:0.7rem;"><?= substr($j['waktu_mulai'], 0, 5) ?> - <?= substr($j['waktu_selesai'], 0, 5) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-calendar-event me-2"></i>Preview Jadwal Besok</h6>
        <?php if (! empty($approval_note)): ?>
            <div class="text-muted"><?= esc($approval_note) ?></div>
        <?php elseif (empty($jadwal_besok)): ?>
            <div class="s3-empty">
                <i class="bi bi-moon-stars"></i>
                Belum ada jadwal untuk besok.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Jam Ke</th>
                            <th style="width: 120px;">Waktu</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Ruangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jadwal_besok as $item): ?>
                        <tr>
                            <td><span class="badge bg-primary">Jam ke-<?= esc($item['jam_ke']) ?></span></td>
                            <td class="text-muted small"><?= substr($item['waktu_mulai'], 0, 5) ?> - <?= substr($item['waktu_selesai'], 0, 5) ?></td>
                            <td class="fw-semibold"><?= esc($item['mapel_nama']) ?></td>
                            <td><?= esc($item['kelas_nama']) ?></td>
                            <td><i class="bi bi-geo-alt-fill text-muted me-1"></i><?= esc($item['ruangan_kode']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
