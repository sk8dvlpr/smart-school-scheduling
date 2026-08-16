<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-12">
        <h4 class="fw-bold"><i class="bi bi-speedometer2"></i> Dashboard Guru</h4>
        <p class="text-muted">Selamat datang, <?= esc(session()->get('nama')) ?></p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center bg-primary text-white h-100 py-4">
            <div class="card-body">
                <i class="bi bi-clock-history fs-1 mb-2"></i>
                <h2 class="fw-bold mb-1"><?= $total_jp ?></h2>
                <div class="small text-white-50 text-uppercase">Total Jam Mengajar</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center bg-success text-white h-100 py-4">
            <div class="card-body">
                <i class="bi bi-diagram-3 fs-1 mb-2"></i>
                <h2 class="fw-bold mb-1"><?= $total_kelas ?></h2>
                <div class="small text-white-50 text-uppercase">Total Rombel Diajar</div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                <h6 class="fw-bold mb-0">Jadwal Mengajar Hari Ini</h6>
                <a href="<?= base_url('guru/jadwal') ?>" class="btn btn-sm btn-outline-primary">Lihat Jadwal Lengkap</a>
            </div>
            <div class="card-body pt-0">
                <?php if (!$active_ta): ?>
                    <div class="alert alert-warning mb-0">Tahun ajaran belum aktif.</div>
                <?php elseif (! empty($approval_note)): ?>
                    <div class="alert alert-info border-0 bg-info-subtle mb-0">
                        <i class="bi bi-hourglass-split me-1"></i>
                        <?= esc($approval_note) ?>
                    </div>
                <?php elseif (empty($jadwal)): ?>
                    <div class="alert alert-info border-0 bg-info-subtle mb-0">
                        Belum ada jadwal mengajar pada tahun ajaran ini.
                    </div>
                <?php elseif (empty($jadwal_hari_ini)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-cup-hot fs-2 mb-2 d-block"></i>
                        Tidak ada jadwal mengajar hari ini.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($jadwal_hari_ini as $j): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold"><?= esc($j['mapel_nama']) ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-people-fill me-1"></i> Rombel <?= esc($j['kelas_nama']) ?>
                                        <span class="mx-2">|</span>
                                        <i class="bi bi-geo-alt-fill me-1"></i> <?= esc($j['ruangan_kode']) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary rounded-pill mb-1">Jam ke-<?= $j['jam_ke'] ?></span>
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

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-calendar-event me-2"></i>Preview Jadwal Besok</h6>
                <?php if (! empty($approval_note)): ?>
                    <div class="text-muted"><?= esc($approval_note) ?></div>
                <?php elseif (empty($jadwal_besok)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-moon-stars fs-2 mb-2 d-block"></i>
                        Belum ada jadwal untuk besok.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
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
                                    <td><span class="badge bg-primary rounded-pill">Jam ke-<?= esc($item['jam_ke']) ?></span></td>
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
    </div>
</div>
<?= $this->endSection() ?>
