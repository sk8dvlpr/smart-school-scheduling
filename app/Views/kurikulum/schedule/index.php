<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold"><i class="bi bi-cpu"></i> Generator Jadwal Otomatis (CSP + GA)</h4>
        <p class="text-muted">Generate jadwal mata pelajaran menggunakan algoritma Constraint Satisfaction Problem dan Genetic Algorithm.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="<?= base_url('kurikulum/schedule/config') ?>" class="btn btn-outline-secondary me-2">
            <i class="bi bi-gear"></i> Konfigurasi
        </a>
        <a href="<?= base_url('kurikulum/schedule/logs') ?>" class="btn btn-outline-info">
            <i class="bi bi-clock-history"></i> Riwayat
        </a>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= esc(session()->getFlashdata('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!$active_ta): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> Tidak ada Tahun Ajaran yang aktif. Silakan aktifkan di menu Master Data -> Tahun Ajaran.
    </div>
<?php else: ?>
    <div class="row">
        <!-- Pre-validation Panel -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">1. Pra-Validasi Data</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Sistem memeriksa kelengkapan master data sebelum proses generate dijalankan.</p>
                    
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($validation as $v): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <span class="fw-medium d-block"><?= esc($v['rule']) ?></span>
                                    <small class="text-muted"><?= esc($v['message']) ?></small>
                                </div>
                                <?php if ($v['status']): ?>
                                    <span class="badge bg-success rounded-pill"><i class="bi bi-check-lg"></i> Valid</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill"><i class="bi bi-x-lg"></i> Error</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!$is_valid): ?>
                        <div class="alert alert-danger mb-0 py-2">
                            <i class="bi bi-x-circle me-1"></i> Lengkapi master data yang masih error sebelum generate.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Generate Control Panel -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">2. Eksekusi Generator</h6>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.75rem;">Status Jadwal Saat Ini</h6>
                        <?php if ($has_jadwal): ?>
                            <div class="alert alert-success py-2 mb-2 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-check-circle-fill me-2"></i> Jadwal sudah digenerate</span>
                                <a href="<?= base_url('kurikulum/schedule/result') ?>" class="btn btn-sm btn-success">Lihat Jadwal</a>
                            </div>
                            <form action="<?= base_url('kurikulum/schedule/reset') ?>" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal yang sudah ada? Ini tidak dapat dibatalkan.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="bi bi-trash"></i> Hapus / Reset Jadwal Saat Ini
                                </button>
                            </form>
                        <?php else: ?>
                            <div id="scheduleStatusPanel">
                                <div class="alert alert-secondary py-2 mb-0">
                                    <i class="bi bi-info-circle me-2"></i> Belum ada jadwal yang di-generate.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-3">

                    <div class="mt-auto">
                        <div id="generateProgressPanel" class="d-none mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small fw-bold text-primary" id="generateStatusText">Sedang mencari solusi (CSP)...</span>
                                <span class="small text-muted" id="generateTimeText">00:00</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div id="generateProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 10%"></div>
                            </div>
                        </div>

                        <div id="generateResultAlert" class="alert d-none mb-3"></div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Mode Generate</label>
                            <select id="generateMode" class="form-select form-select-sm">
                                <option value="fresh">Fresh — dari awal (CSP + GA)</option>
                                <option value="history_repair">History Repair — patokan history sebelumnya</option>
                            </select>
                        </div>
                        <?php if (!empty($history_logs)): ?>
                        <div class="mb-3" id="parentLogPanel" style="display:none;">
                            <label class="form-label small fw-bold">History Referensi</label>
                            <select id="parentLogId" class="form-select form-select-sm">
                                <?php foreach ($history_logs as $hl): ?>
                                    <?php if (in_array($hl['status'], ['completed', 'partial'], true)): ?>
                                    <option value="<?= (int) $hl['id'] ?>">
                                        #<?= (int) $hl['id'] ?> — <?= esc($hl['label'] ?? $hl['status']) ?> (<?= esc($hl['status']) ?>)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($published_log)): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-broadcast"></i> Published: history #<?= (int) $published_log['id'] ?>
                            (<?= esc($published_log['status']) ?>)
                        </div>
                        <?php endif; ?>

                        <button type="button" id="btnGenerate" class="btn btn-primary w-100 py-2 fw-bold" <?= (!$is_valid) ? 'disabled' : '' ?>>
                            <i class="bi bi-play-fill me-1"></i> Mulai Generate Jadwal Otomatis
                        </button>
                        
                        <div class="form-text text-muted mt-2 text-center small">
                            Generate baru membuat <strong>history terpisah</strong> — history lama tidak dihapus. Publish manual setelah review.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const RESULT_BASE_URL = <?= json_encode(base_url('kurikulum/schedule/result')) ?>;
    let timerInterval;
    let seconds = 0;
    let lastScheduleLogId = null;

    function formatTime(sec) {
        let m = Math.floor(sec / 60).toString().padStart(2, '0');
        let s = (sec % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function goToResult(logId) {
        const id = logId || lastScheduleLogId;
        const url = id
            ? RESULT_BASE_URL + '?schedule_log_id=' + encodeURIComponent(id)
            : RESULT_BASE_URL;
        window.location.href = url;
    }

    function bindGenerateButton() {
        $('#btnGenerate')
            .off('click')
            .prop('disabled', <?= $is_valid ? 'false' : 'true' ?>)
            .html('<i class="bi bi-play-fill me-1"></i> Mulai Generate Jadwal Otomatis')
            .on('click', startGenerate);
    }

    function bindViewResultButton(logId) {
        lastScheduleLogId = logId || null;
        $('#btnGenerate')
            .off('click')
            .prop('disabled', false)
            .html('<i class="bi bi-eye"></i> Lihat Hasil')
            .on('click', function() {
                goToResult(logId);
            });
    }

    function updateStatusPanel(logId) {
        const html = `
            <div class="alert alert-success py-2 mb-0 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check-circle-fill me-2"></i> Jadwal baru berhasil digenerate</span>
                <button type="button" class="btn btn-sm btn-success" id="btnStatusViewResult">Lihat Jadwal</button>
            </div>`;
        $('#scheduleStatusPanel').html(html);
        $('#btnStatusViewResult').on('click', function() {
            goToResult(logId);
        });
    }

    function startGenerate() {
        if (!confirm('Mulai proses generate? Hasil disimpan sebagai history baru (history lama tetap ada).')) {
            return;
        }

        const btn = $('#btnGenerate');
        const progressPanel = $('#generateProgressPanel');
        const statusBar = $('#generateProgressBar');
        const statusText = $('#generateStatusText');
        const timeText = $('#generateTimeText');
        const resultAlert = $('#generateResultAlert');

        // Reset UI
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Memproses...');
        resultAlert.addClass('d-none').removeClass('alert-success alert-danger alert-warning');
        progressPanel.removeClass('d-none');
        statusBar.css('width', '10%').addClass('progress-bar-animated bg-primary');
        statusText.text('Inisialisasi engine...');
        
        // Start Timer
        seconds = 0;
        timeText.text('00:00');
        clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            seconds++;
            timeText.text(formatTime(seconds));
            
            // Fake progress stages for better UX (since it's a synchronous call)
            if (seconds === 3) {
                statusBar.css('width', '30%');
                statusText.text('Fase 1: Constraint Satisfaction Problem (CSP)...');
            } else if (seconds === 15) {
                statusBar.css('width', '60%');
                statusText.text('Fase 2: Genetic Algorithm (GA) Optimization...');
            } else if (seconds === 30) {
                statusBar.css('width', '80%');
            }
        }, 1000);

        // Make AJAX call to controller
        $.ajax({
            url: '<?= base_url('kurikulum/schedule/generate') ?>',
            type: 'POST',
            data: {
                <?= csrf_token() ?>: '<?= csrf_hash() ?>',
                generate_mode: $('#generateMode').val(),
                parent_log_id: $('#generateMode').val() === 'history_repair' ? $('#parentLogId').val() : ''
            },
            dataType: 'json',
            success: function(response) {
                clearInterval(timerInterval);
                statusBar.removeClass('progress-bar-animated');
                
                if (response.success) {
                    const isPartial = response.status === 'partial';
                    statusBar.css('width', '100%').removeClass('bg-primary').addClass(isPartial ? 'bg-warning' : 'bg-success');
                    statusText.text(isPartial ? 'Sebagian berhasil' : 'Berhasil!');
                    
                    let resultHtml = `<strong><i class="bi bi-${isPartial ? 'exclamation-triangle-fill' : 'check-circle-fill'}"></i> ${isPartial ? 'Selesai (Parsial)' : 'Selesai!'}</strong><br>`;
                    resultHtml += `${response.summary || 'Jadwal berhasil diproses.'}<br>`;
                    resultHtml += `Waktu: <strong>${response.execution_time}</strong> detik`;
                    if (response.fitness) {
                        resultHtml += ` | Fitness: <strong>${response.fitness}</strong>`;
                    }

                    if (response.report) {
                        if (response.report.warnings && response.report.warnings.length > 0) {
                            resultHtml += `<hr class="my-2"><div class="small"><strong>Peringatan (${response.report.warnings.length}):</strong><ul class="mb-0 ps-3">`;
                            response.report.warnings.slice(0, 5).forEach(w => {
                                resultHtml += `<li>${escapeHtml(w)}</li>`;
                            });
                            if (response.report.warnings.length > 5) {
                                resultHtml += `<li>...dan ${response.report.warnings.length - 5} lainnya</li>`;
                            }
                            resultHtml += `</ul></div>`;
                        }

                        if (response.report.unplaced && response.report.unplaced.length > 0) {
                            resultHtml += `<hr class="my-2"><div class="small"><strong>Blok belum terjadwal (${response.report.unplaced.length}):</strong><ul class="mb-0 ps-3">`;
                            response.report.unplaced.slice(0, 8).forEach(u => {
                                const label = [u.kelas_nama, u.mapel_nama, u.guru_nama].filter(Boolean).join(' / ') || `Blok #${u.block_id}`;
                                resultHtml += `<li>${escapeHtml(label)} — <em>${escapeHtml(u.reason_label || u.reason)}</em></li>`;
                            });
                            if (response.report.unplaced.length > 8) {
                                resultHtml += `<li>...dan ${response.report.unplaced.length - 8} lainnya (lihat Riwayat)</li>`;
                            }
                            resultHtml += `</ul></div>`;
                        }
                    }

                    const logId = response.schedule_log_id || null;
                    if (logId) {
                        resultHtml += `<hr class="my-2"><a href="#" class="btn btn-sm ${isPartial ? 'btn-warning' : 'btn-success'}" id="btnInlineViewResult"><i class="bi bi-calendar3"></i> Buka halaman hasil jadwal</a>`;
                    }

                    resultAlert.removeClass('d-none').addClass(isPartial ? 'alert-warning' : 'alert-success').html(resultHtml);

                    if (logId) {
                        $('#btnInlineViewResult').on('click', function(e) {
                            e.preventDefault();
                            goToResult(logId);
                        });
                    }

                    updateStatusPanel(logId);
                    bindViewResultButton(logId);
                } else {
                    statusBar.css('width', '100%').removeClass('bg-primary').addClass('bg-danger');
                    statusText.text('Gagal');
                    let failHtml = `<strong>Error:</strong> ${escapeHtml(response.message || response.summary || 'Generate gagal.')}`;
                    if (response.report && response.report.warnings && response.report.warnings.length > 0) {
                        failHtml += `<hr class="my-2"><div class="small"><strong>Peringatan:</strong><ul class="mb-0 ps-3">`;
                        response.report.warnings.slice(0, 3).forEach(w => { failHtml += `<li>${escapeHtml(w)}</li>`; });
                        failHtml += `</ul></div>`;
                    }
                    resultAlert.removeClass('d-none').addClass('alert-danger').html(failHtml);
                    bindGenerateButton();
                }
            },
            error: function(xhr) {
                clearInterval(timerInterval);
                statusBar.removeClass('progress-bar-animated').css('width', '100%').removeClass('bg-primary').addClass('bg-danger');
                statusText.text('Error Server');
                
                let errorMsg = 'Terjadi kesalahan pada server saat memproses algoritma.';
                if (xhr.status === 504 || xhr.status === 500) {
                    errorMsg = 'Timeout atau server overload. Kurangi data atau naikkan timeout PHP.';
                }
                
                resultAlert.removeClass('d-none').addClass('alert-danger').html(`<strong>Gagal:</strong> ${errorMsg}`);
                bindGenerateButton();
            }
        });
    }

    $(document).ready(function() {
        bindGenerateButton();

        $('#generateMode').on('change', function() {
            $('#parentLogPanel').toggle($(this).val() === 'history_repair');
        });
    });
</script>
<?= $this->endSection() ?>
