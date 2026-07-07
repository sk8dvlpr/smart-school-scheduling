<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('kurikulum/schedule') ?>" class="btn btn-primary btn-sm"><i class="bi bi-cpu"></i> Generate Jadwal</a>
            <a href="<?= base_url('kurikulum/schedule/result') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-week"></i> Lihat Jadwal</a>
            <a href="<?= base_url('kurikulum/users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people"></i> User</a>
            <a href="<?= base_url('kurikulum/guru') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge"></i> Guru</a>
            <a href="<?= base_url('kurikulum/kelas') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-building"></i> Kelas</a>
            <a href="<?= base_url('kurikulum/timeslot') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock"></i> Timeslot</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">Total User</h6>
                        <h2 class="mb-0 fw-bold"><?= $total_users ?></h2>
                    </div>
                    <div class="fs-1 text-white-50"><i class="bi bi-people"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">Profil Guru</h6>
                        <h2 class="mb-0 fw-bold"><?= $total_guru ?></h2>
                    </div>
                    <div class="fs-1 text-white-50"><i class="bi bi-person-badge"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">Total Kelas</h6>
                        <h2 class="mb-0 fw-bold"><?= $total_kelas ?></h2>
                    </div>
                    <div class="fs-1 text-white-50"><i class="bi bi-building"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">Total Mata Pelajaran</h6>
                        <h2 class="mb-0 fw-bold"><?= $total_mapel ?></h2>
                    </div>
                    <div class="fs-1 text-white-50"><i class="bi bi-book"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-4">Statistik Penjadwalan</h5>
                <canvas id="scheduleChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-4">Aktivitas Generator Terakhir</h5>
                <?php if (empty($logs)): ?>
                    <p class="text-muted text-center my-5">Belum ada aktivitas penjadwalan.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush border-0">
                        <?php foreach ($logs as $log): ?>
                            <div class="list-group-item px-0 pt-3 pb-3 border-bottom">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold">
                                        <?php if ($log->status === 'completed'): ?>
                                            <span class="badge bg-success">Berhasil</span>
                                        <?php elseif ($log->status === 'partial'): ?>
                                            <span class="badge bg-warning text-dark">Partial</span>
                                        <?php elseif ($log->status === 'failed'): ?>
                                            <span class="badge bg-danger">Gagal</span>
                                        <?php elseif ($log->status === 'running'): ?>
                                            <span class="badge bg-secondary">Proses</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><?= esc(ucfirst((string) $log->status)) ?></span>
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
                <h5 class="card-title fw-bold mb-3">Status Jadwal Aktif</h5>
                <p class="mb-1"><strong>Tahun Ajaran:</strong> <?= esc($active_ta['nama'] ?? '-') ?></p>
                <p class="mb-1"><strong>Semester:</strong> <?= esc(isset($active_ta['semester']) ? ucfirst($active_ta['semester']) : '-') ?></p>
                <p class="mb-2">
                    <strong>Status:</strong>
                    <?php if ($has_jadwal ?? false): ?>
                        <span class="badge bg-success">Sudah Generate</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Belum Generate</span>
                    <?php endif; ?>
                </p>
                <p class="mb-2"><strong>Fitness Terakhir:</strong> <?= esc($latest_log->fitness_score ?? '-') ?></p>
                <a href="<?= base_url('kurikulum/schedule/result') ?>" class="btn btn-primary btn-sm">Lihat Jadwal</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-4">Distribusi Kelas per Jurusan</h5>
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
                <h5 class="card-title fw-bold mb-3"><i class="bi bi-calendar-day"></i> Jadwal Mengajar Hari Ini</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Mapel</th>
                                <th>Kelas</th>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('scheduleChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
                datasets: [{
                    label: 'Jumlah Slot Terjadwal',
                    data: <?= json_encode($jadwal_per_hari ?? [0,0,0,0,0]) ?>,
                    backgroundColor: '#4F46E5',
                    borderRadius: 4
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
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
                    backgroundColor: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444']
                }]
            },
            options: { responsive: true }
        });
    }
</script>
<?= $this->endSection() ?>
