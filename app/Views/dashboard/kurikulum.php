<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title">Dashboard Kurikulum</h1>
        <p class="s3-page-desc">Ringkasan master data dan status penjadwalan sekolah.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('kurikulum/schedule') ?>" class="btn btn-primary btn-sm"><i class="bi bi-cpu me-1"></i> Generate Jadwal</a>
        <a href="<?= base_url('kurikulum/schedule/result') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-week me-1"></i> Lihat Jadwal</a>
        <a href="<?= base_url('kurikulum/users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people me-1"></i> User</a>
        <a href="<?= base_url('kurikulum/guru') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge me-1"></i> Guru</a>
        <a href="<?= base_url('kurikulum/kelas') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-building me-1"></i> Rombel</a>
        <a href="<?= base_url('kurikulum/timeslot') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock me-1"></i> Timeslot</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Total User</div>
                    <div class="s3-kpi-value"><?= esc($total_users) ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-people"></i></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Profil Guru</div>
                    <div class="s3-kpi-value"><?= esc($total_guru) ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-person-badge"></i></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Total Rombel</div>
                    <div class="s3-kpi-value"><?= esc($total_kelas) ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-building"></i></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="s3-kpi">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="s3-kpi-label">Mata Pelajaran</div>
                    <div class="s3-kpi-value"><?= esc($total_mapel) ?></div>
                </div>
                <span class="s3-kpi-icon"><i class="bi bi-book"></i></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-4" style="letter-spacing:-0.02em;">Statistik Penjadwalan</h5>
                <canvas id="scheduleChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-4" style="letter-spacing:-0.02em;">Aktivitas Generator</h5>
                <?php if (empty($logs)): ?>
                    <div class="s3-empty">
                        <i class="bi bi-activity"></i>
                        Belum ada aktivitas penjadwalan.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush border-0">
                        <?php foreach ($logs as $log): ?>
                            <div class="list-group-item px-0 pt-3 pb-3 border-bottom">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold">
                                        <?php if ($log->status === 'completed'): ?>
                                            <span class="badge bg-success">Berhasil</span>
                                        <?php elseif ($log->status === 'partial'): ?>
                                            <span class="badge bg-warning">Partial</span>
                                        <?php elseif ($log->status === 'failed'): ?>
                                            <span class="badge bg-danger">Gagal</span>
                                        <?php elseif ($log->status === 'running'): ?>
                                            <span class="badge bg-secondary">Proses</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><?= esc(ucfirst((string) $log->status)) ?></span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted"><?php
                                        $logTime = $log->started_at ?? $log->completed_at ?? $log->created_at ?? null;
                                        echo $logTime ? date('d M Y, H:i', strtotime((string) $logTime)) : '-';
                                    ?></small>
                                </div>
                                <p class="mb-1 small text-muted">
                                    Fitness: <?= esc($log->fitness_score ?? '-') ?> | Waktu: <?= $log->execution_time ? $log->execution_time . 's' : '-' ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-3" style="letter-spacing:-0.02em;">Status Jadwal Aktif</h5>
                <p class="mb-1"><strong>Tahun Ajaran:</strong> <?= esc($active_ta['nama'] ?? '-') ?></p>
                <p class="mb-1"><strong>Semester:</strong> <?= esc(isset($active_ta['semester']) ? ucfirst($active_ta['semester']) : '-') ?></p>
                <p class="mb-2">
                    <strong>Status:</strong>
                    <?php if ($has_jadwal ?? false): ?>
                        <span class="badge bg-success">Sudah Generate</span>
                    <?php else: ?>
                        <span class="badge bg-warning">Belum Generate</span>
                    <?php endif; ?>
                </p>
                <p class="mb-3"><strong>Fitness Terakhir:</strong> <?= esc($latest_log->fitness_score ?? '-') ?></p>
                <a href="<?= base_url('kurikulum/schedule/result') ?>" class="btn btn-primary btn-sm">Lihat Jadwal</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-4" style="letter-spacing:-0.02em;">Distribusi Rombel per Jurusan</h5>
                <canvas id="jurusanChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<?php if (session()->get('guru_id') && ! empty($jadwal_hari_ini)): ?>
<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="bi bi-calendar-day me-1"></i> Jadwal Mengajar Hari Ini</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Mapel</th>
                                <th>Rombel</th>
                                <th>Ruangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_hari_ini as $j): ?>
                            <tr>
                                <td>JP <?= esc($j['jam_ke']) ?></td>
                                <td><?= esc($j['mapel_nama']) ?></td>
                                <td><?= esc($j['kelas_nama']) ?></td>
                                <td><?= esc($j['ruangan_nama'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?= base_url('guru/jadwal') ?>" class="btn btn-sm btn-outline-primary mt-3">Lihat Jadwal Lengkap</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>
    const isDarkMode = document.body.classList.contains('dark-mode')
        || document.documentElement.classList.contains('dark-mode');
    const chartTick = isDarkMode ? '#A9B8C9' : '#5B6B7C';
    const chartGrid = isDarkMode ? 'rgba(232,238,245,0.12)' : 'rgba(11,31,58,0.06)';
    const chartBar = isDarkMode ? '#E85D4C' : '#0B1F3A';
    const chartBarHover = isDarkMode ? '#F5B5AD' : '#E85D4C';
    const chartLegend = isDarkMode ? '#E8EEF5' : '#142033';
    const chartSlices = isDarkMode
        ? ['#E85D4C', '#F5B5AD', '#5B8FB8', '#A9B8C9', '#C48A2A']
        : ['#0B1F3A', '#E85D4C', '#163457', '#5B6B7C', '#C48A2A'];

    const ctx = document.getElementById('scheduleChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
                datasets: [{
                    label: 'Jumlah Slot Terjadwal',
                    data: <?= json_encode($jadwal_per_hari ?? [0,0,0,0,0]) ?>,
                    backgroundColor: chartBar,
                    hoverBackgroundColor: chartBarHover,
                    borderRadius: 6,
                    maxBarThickness: 36
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: chartGrid },
                        ticks: { color: chartTick }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: chartTick }
                    }
                }
            }
        });
    }

    const jurusanCtx = document.getElementById('jurusanChart');
    if (jurusanCtx) {
        new Chart(jurusanCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($kelas_per_jurusan_labels ?? []) ?>,
                datasets: [{
                    data: <?= json_encode($kelas_per_jurusan_data ?? []) ?>,
                    backgroundColor: chartSlices,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 }, color: chartLegend }
                    }
                },
                cutout: '62%'
            }
        });
    }
</script>
<?= $this->endSection() ?>
