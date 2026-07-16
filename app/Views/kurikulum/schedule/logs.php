<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<?= view('components/datatables_styles') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0">Riwayat Generate Jadwal</h4>
            <p class="text-muted mb-0">Log hasil eksekusi algoritma penjadwalan</p>
        </div>
        <a href="<?= base_url('kurikulum/schedule') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table id="dataTable" class="table table-striped table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>Waktu Eksekusi</th>
                        <th>Status</th>
                        <th>Ringkasan</th>
                        <th>Fitness Score</th>
                        <th>Generasi</th>
                        <th>Konflik</th>
                        <th>Durasi (dtk)</th>
                        <th>Dijalankan Oleh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $report = !empty($log['result_report']) ? json_decode($log['result_report'], true) : null;
                        $unplacedCount = is_array($report) ? count($report['unplaced'] ?? []) : 0;
                        $warningCount = is_array($report) ? count($report['warnings'] ?? []) : 0;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= date('d M Y', strtotime($log['started_at'])) ?></div>
                            <div class="small text-muted"><?= date('H:i:s', strtotime($log['started_at'])) ?></div>
                        </td>
                        <td>
                            <?php if ($log['status'] == 'completed'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Selesai</span>
                            <?php elseif ($log['status'] == 'partial'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Parsial</span>
                            <?php elseif ($log['status'] == 'running'): ?>
                                <span class="badge bg-info text-dark"><i class="bi bi-gear-fill"></i> Berjalan</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Gagal</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 280px;">
                            <?php if (!empty($log['error_message'])): ?>
                                <div class="small"><?= esc($log['error_message']) ?></div>
                            <?php endif; ?>
                            <?php if ($warningCount > 0): ?>
                                <div class="small text-warning mt-1"><i class="bi bi-exclamation-circle"></i> <?= $warningCount ?> peringatan</div>
                            <?php endif; ?>
                            <?php if ($unplacedCount > 0): ?>
                                <div class="small text-danger mt-1"><i class="bi bi-slash-circle"></i> <?= $unplacedCount ?> blok belum terjadwal</div>
                            <?php endif; ?>
                            <?php if (is_array($report) && !empty($report['unplaced'])): ?>
                                <details class="mt-1">
                                    <summary class="small text-muted" style="cursor:pointer;">Detail unplaced</summary>
                                    <ul class="small mb-0 ps-3 mt-1" style="font-size: 0.7rem;">
                                        <?php foreach (array_slice($report['unplaced'], 0, 5) as $u): ?>
                                            <li>
                                                <?= esc(($u['kelas_nama'] ?? 'Rombel') . ' / ' . ($u['mapel_nama'] ?? 'Mapel')) ?>
                                                — <?= esc($u['reason_label'] ?? ($u['reason'] ?? '-')) ?>
                                                <?php if (!empty($u['suggested_fix'])): ?>
                                                    <br><span class="text-muted"><?= esc($u['suggested_fix']) ?></span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if ($unplacedCount > 5): ?>
                                            <li>...dan <?= $unplacedCount - 5 ?> lainnya</li>
                                        <?php endif; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['fitness_score']): ?>
                                <div class="progress" style="height: 6px; width: 60px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= $log['fitness_score'] * 100 ?>%"></div>
                                </div>
                                <div class="small fw-bold mt-1"><?= number_format($log['fitness_score'], 4) ?></div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $log['generations_run'] ?? '-' ?></td>
                        <td>
                            <?php if ($log['total_conflicts'] !== null): ?>
                                <?php if ($log['total_conflicts'] == 0): ?>
                                    <span class="text-success fw-bold">0</span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold"><?= $log['total_conflicts'] ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $log['execution_time'] ?? '-' ?></td>
                        <td><?= esc($log['admin_nama'] ?? 'System') ?></td>
                        <td class="text-nowrap">
                            <?php if (in_array($log['status'], ['completed', 'partial'], true)): ?>
                                <a href="<?= base_url('kurikulum/schedule/result?schedule_log_id=' . (int) $log['id']) ?>" class="btn btn-sm btn-primary" title="Lihat jadwal history ini">
                                    <i class="bi bi-calendar3"></i> Jadwal
                                </a>
                            <?php endif; ?>
                            <a href="<?= base_url('kurikulum/schedule/history/' . (int) $log['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                            <?php if (in_array($log['status'], ['completed', 'partial'], true)): ?>
                                <?php if ((int) ($published_id ?? 0) === (int) $log['id']): ?>
                                    <span class="badge bg-success">Published</span>
                                <?php else: ?>
                                    <form action="<?= base_url('kurikulum/schedule/publish/' . (int) $log['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Publish history ini?<?= $log['status'] === 'partial' ? ' Status partial — akan tampil warning.' : '' ?>');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-success">Publish</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('components/datatables_scripts') ?>
<script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            order: [[0, 'desc']]
        });
    });
</script>
<?= $this->endSection() ?>
