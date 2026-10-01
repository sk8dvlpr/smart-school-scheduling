<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title">Detail History #<?= (int) $log['id'] ?></h1>
        <p class="s3-page-desc"><?= esc($log['label'] ?? '') ?> — <?= esc($log['generate_mode'] ?? 'fresh') ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (in_array($log['status'], ['completed', 'partial'], true)): ?>
            <a href="<?= base_url('kurikulum/schedule/result?schedule_log_id=' . (int) $log['id']) ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-calendar3"></i> Lihat Jadwal
            </a>
        <?php endif; ?>
        <?php if ($is_published): ?>
            <span class="badge bg-success align-self-center"><i class="bi bi-broadcast"></i> Published</span>
            <?php
                $appr = $log['approval_status'] ?? null;
                if ($appr === 'approved'): ?>
                <span class="badge bg-primary align-self-center"><i class="bi bi-check-circle"></i> Disetujui Kepsek</span>
            <?php elseif ($appr === 'rejected'): ?>
                <span class="badge bg-danger align-self-center"><i class="bi bi-x-circle"></i> Ditolak Kepsek</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark align-self-center"><i class="bi bi-hourglass-split"></i> Menunggu Acc Kepsek</span>
            <?php endif; ?>
        <?php else: ?>
            <form action="<?= base_url('kurikulum/schedule/publish/' . (int) $log['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Publish history ini ke Kepala Sekolah untuk ditinjau?<?= $log['status'] === 'partial' ? ' PERINGATAN: status partial (' . $pct . '% terisi).' : '' ?> Guru baru melihat setelah Kepsek menyetujui.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-broadcast"></i> Publish</button>
            </form>
        <?php endif; ?>
        <a href="<?= base_url('kurikulum/schedule/logs') ?>" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="s3-kpi">
            <div class="s3-kpi-label">Status</div>
            <div class="s3-kpi-value" style="font-size:1.25rem;"><?= esc($log['status']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="s3-kpi">
            <div class="s3-kpi-label">% Terisi</div>
            <div class="s3-kpi-value"><?= $pct ?>%</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="s3-kpi">
            <div class="s3-kpi-label">Fitness</div>
            <div class="s3-kpi-value" style="font-size:1.25rem;"><?= $log['fitness_score'] ? number_format((float) $log['fitness_score'], 4) : '-' ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="s3-kpi">
            <div class="s3-kpi-label">Durasi</div>
            <div class="s3-kpi-value" style="font-size:1.25rem;">
                <?php 
                    if (isset($log['execution_time'])) {
                        echo gmdate("H:i:s", (int)$log['execution_time']);
                    } else {
                        echo '-';
                    }
                ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($report['stats']['ga']['violations'])): ?>
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Kualitas Jadwal (GA)</h6>
        <?php
            $fit = (float) ($report['stats']['ga']['fitness'] ?? 0);
            $penalty = $fit > 0 ? (1 / $fit) - 1 : 0;
            $qScore = max(0, 100 - ($penalty * 2));
            
            $bgClass = 'bg-danger';
            if ($qScore >= 80) $bgClass = 'bg-success';
            elseif ($qScore >= 60) $bgClass = 'bg-info';
            elseif ($qScore >= 40) $bgClass = 'bg-warning';
        ?>
        <div class="mb-3 d-flex flex-wrap align-items-center gap-3">
            <div>
                Quality Score: 
                <span class="fs-4 fw-bold <?= str_replace('bg-', 'text-', $bgClass) ?>"><?= number_format($qScore, 1) ?>%</span>
            </div>
            <div class="progress flex-grow-1" style="height: 10px; max-width: 200px;">
                <div class="progress-bar <?= $bgClass ?>" role="progressbar" style="width: <?= $qScore ?>%"></div>
            </div>
        </div>
        
        <p>Fitness (Raw): <strong><?= number_format($fit, 4) ?></strong>
        | Generations: <?= (int) ($report['stats']['ga']['generations'] ?? 0) ?>
        | Violation score: <?= (int) ($report['stats']['ga']['violations'] ?? 0) ?></p>

        <?php if (isset($report['stats']['ga']['weighted_sum'])): ?>
        <p class="mb-2">Weighted sum: <strong><?= esc(number_format((float) $report['stats']['ga']['weighted_sum'], 4)) ?></strong></p>
        <?php endif; ?>

        <?php if (!empty($report['stats']['ga']['penalty_breakdown'])): ?>
            <?php
                $scLabels = [
                    'sc1'  => 'Gap guru',
                    'sc2'  => 'Gap kelas',
                    'sc3'  => 'Sebaran mapel',
                    'sc4'  => 'Mapel berat di sore',
                    'sc5'  => 'Mapel ringan di pagi',
                    'sc6'  => 'Beban guru harian',
                    'sc7'  => 'Preferensi guru',
                    'sc8'  => 'Transisi ruangan',
                    'sc9'  => 'Kontinuitas guru',
                    'sc10' => 'Rotasi slot pertama',
                    'sc11' => 'Keseimbangan lab',
                    'sc12' => 'Pemadatan hari lab',
                    'lab_pref' => 'Preferensi lab',
                ];
            ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Kriteria</th>
                            <th class="text-end">Penalty (0-1)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scLabels as $key => $label): ?>
                            <?php if (array_key_exists($key, $report['stats']['ga']['penalty_breakdown'])): ?>
                            <tr>
                                <td><?= esc($label) ?></td>
                                <td class="text-end font-monospace"><?= esc(number_format((float) $report['stats']['ga']['penalty_breakdown'][$key], 4)) ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($suggestions)): ?>
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning"></i> Saran Perbaikan</h6>
        <p class="small text-muted mb-3">Rekomendasi berdasarkan unit yang belum terjadwal dan peringatan dari proses generate.</p>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
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
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold text-danger mb-3">Diagnostik Partial — Unit Belum Terplace</h6>
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
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Fill Report per Rombel</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Rombel</th><th>Detail JP/hari</th></tr></thead>
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
</div>
<?php endif; ?>

<?php if (in_array($log['status'], ['completed', 'partial'], true)): ?>
<p class="text-muted small mb-0">
    <i class="bi bi-info-circle"></i>
    Gunakan tombol <strong>Lihat Jadwal</strong> di atas untuk membuka tampilan jadwal lengkap history ini.
</p>
<?php endif; ?>
<?= $this->endSection() ?>
