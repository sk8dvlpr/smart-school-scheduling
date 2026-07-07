<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0">Detail History #<?= (int) $log['id'] ?></h4>
            <p class="text-muted mb-0"><?= esc($log['label'] ?? '') ?> — <?= esc($log['generate_mode'] ?? 'fresh') ?></p>
        </div>
        <div>
            <?php if (in_array($log['status'], ['completed', 'partial'], true)): ?>
                <a href="<?= base_url('kurikulum/schedule/result?schedule_log_id=' . (int) $log['id']) ?>" class="btn btn-primary me-2">
                    <i class="bi bi-calendar3"></i> Lihat Jadwal
                </a>
            <?php endif; ?>
            <?php if ($is_published): ?>
                <span class="badge bg-success me-2"><i class="bi bi-broadcast"></i> Published</span>
            <?php else: ?>
                <form action="<?= base_url('kurikulum/schedule/publish/' . (int) $log['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Publish history ini ke Guru & Kepala Sekolah?<?= $log['status'] === 'partial' ? ' PERINGATAN: status partial (' . $pct . '% terisi).' : '' ?>');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success"><i class="bi bi-broadcast"></i> Publish</button>
                </form>
            <?php endif; ?>
            <a href="<?= base_url('kurikulum/schedule/logs') ?>" class="btn btn-outline-secondary ms-2">Kembali</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Status</div>
            <div class="fw-bold"><?= esc($log['status']) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">% Terisi</div>
            <div class="fw-bold"><?= $pct ?>%</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Fitness</div>
            <div class="fw-bold"><?= $log['fitness_score'] ? number_format((float) $log['fitness_score'], 4) : '-' ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Durasi</div>
            <div class="fw-bold"><?= esc($log['execution_time'] ?? '-') ?> dtk</div>
        </div></div>
    </div>
</div>

<?php if (!empty($report['stats']['ga']['violations'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Kualitas Jadwal (GA)</div>
    <div class="card-body small">
        <p>Fitness: <strong><?= number_format((float) ($report['stats']['ga']['fitness'] ?? 0), 4) ?></strong>
        | Generations: <?= (int) ($report['stats']['ga']['generations'] ?? 0) ?>
        | Violation score: <?= (int) ($report['stats']['ga']['violations'] ?? 0) ?></p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($suggestions)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold"><i class="bi bi-lightbulb text-warning"></i> Saran Perbaikan</div>
    <div class="card-body">
        <p class="small text-muted mb-3">Rekomendasi berdasarkan unit yang belum terjadwal dan peringatan dari proses generate.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Penyebab</th>
                        <th style="width:70px;">Jumlah</th>
                        <th>Saran</th>
                        <th>Contoh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suggestions as $s): ?>
                    <tr>
                        <td class="small fw-medium"><?= esc($s['reason_label']) ?></td>
                        <td><span class="badge bg-secondary"><?= (int) $s['count'] ?></span></td>
                        <td class="small"><?= esc($s['suggested_fix']) ?></td>
                        <td class="small text-muted">
                            <?php if (!empty($s['examples'])): ?>
                                <?= esc(implode('; ', $s['examples'])) ?>
                                <?php if ($s['count'] > count($s['examples'])): ?>
                                    <span class="text-muted">(+<?= $s['count'] - count($s['examples']) ?> lainnya)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($report['unplaced'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold text-danger">Diagnostik Partial — Unit Belum Terplace</div>
    <div class="card-body">
        <ul class="small mb-0">
            <?php foreach (array_slice($report['unplaced'], 0, 30) as $u): ?>
            <li>
                <?= esc(($u['kelas_nama'] ?? '') . ' / ' . ($u['mapel_nama'] ?? '')) ?>
                — <?= esc($u['reason_label'] ?? '') ?>
                <?php if (!empty($u['suggested_fix'])): ?>
                    <br><span class="text-primary"><i class="bi bi-arrow-return-right"></i> <?= esc($u['suggested_fix']) ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($report['fill_report'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Fill Report per Kelas</div>
    <div class="card-body table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Kelas</th><th>Detail JP/hari</th></tr></thead>
            <tbody>
            <?php foreach ($report['fill_report'] as $kelasNama => $days): ?>
                <tr>
                    <td><?= esc($kelasNama) ?></td>
                    <td class="small"><?= esc(implode(' | ', $days)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($log['status'], ['completed', 'partial'], true)): ?>
<p class="text-muted small mb-0">
    <i class="bi bi-info-circle"></i>
    Gunakan tombol <strong>Lihat Jadwal</strong> di atas untuk membuka tampilan jadwal lengkap history ini.
</p>
<?php endif; ?>
<?= $this->endSection() ?>
