<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Lihat Jadwal Sekolah</h4>
            <p class="text-muted mb-0">
                <?php if ($active_ta): ?>
                    Tahun ajaran: <?= esc($active_ta['nama']) ?>
                <?php else: ?>
                    Tidak ada tahun ajaran aktif.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($active_ta && $has_jadwal): ?>
        <div class="d-flex gap-2">
            <a href="<?= base_url('kepala-sekolah/jadwal/export/pdf-all') ?>" class="btn btn-outline-danger" target="_blank">
                <i class="bi bi-file-pdf"></i> Export Semua Rombel (PDF)
            </a>
            <a href="<?= base_url('kepala-sekolah/jadwal/export/excel-all') ?>" class="btn btn-outline-success">
                <i class="bi bi-file-excel"></i> Export Semua Rombel (Excel)
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (! $active_ta): ?>
<div class="alert alert-warning border-0">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Tidak ada tahun ajaran aktif. Hubungi tim kurikulum.
</div>
<?php elseif (! $has_jadwal): ?>
<div class="alert alert-info border-0 text-center py-5">
    <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
    <h5 class="fw-bold">Belum Ada Jadwal</h5>
    <p class="text-muted mb-0">Jadwal sekolah belum di-publish oleh Kurikulum untuk tahun ajaran ini.</p>
</div>
<?php else: ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<?php
    $approvalStatus = $published_log['approval_status'] ?? 'pending';
    $approvalBadge = match ($approvalStatus) {
        'approved' => 'success',
        'rejected' => 'danger',
        default    => 'warning',
    };
    $approvalLabel = match ($approvalStatus) {
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        default    => 'Menunggu Persetujuan',
    };
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h5 class="fw-bold mb-1">Persetujuan Jadwal Published</h5>
                <p class="text-muted mb-2 mb-md-0">
                    History #<?= (int) $published_log['id'] ?>
                    <?php if (! empty($published_log['label'])): ?>
                        — <?= esc($published_log['label']) ?>
                    <?php endif; ?>
                    <span class="badge bg-<?= $approvalBadge ?> ms-2"><?= esc($approvalLabel) ?></span>
                </p>
                <?php if (! empty($published_log['approval_note'])): ?>
                    <small class="text-muted">Catatan: <?= esc($published_log['approval_note']) ?></small>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1" style="max-width: 420px;">
                <form method="post" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label small mb-1">Catatan (opsional)</label>
                        <input type="text" name="approval_note" class="form-control form-control-sm" maxlength="500" placeholder="Alasan setuju / tolak">
                    </div>
                    <div class="col-auto">
                        <button formaction="<?= base_url('kepala-sekolah/jadwal/approve') ?>" type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Setujui jadwal ini agar tampil di akun Guru?');">
                            <i class="bi bi-check-lg"></i> Setujui
                        </button>
                    </div>
                    <div class="col-auto">
                        <button formaction="<?= base_url('kepala-sekolah/jadwal/reject') ?>" type="submit" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('Tolak jadwal ini? Guru tidak akan melihat jadwal sampai Kurikulum publish ulang dan Anda setujui.');">
                            <i class="bi bi-x-lg"></i> Tolak
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <ul class="nav nav-tabs mb-4" id="jadwalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="kelas-tab" data-bs-toggle="tab" data-bs-target="#kelas-pane" type="button" role="tab">Berdasarkan Rombel</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="guru-tab" data-bs-toggle="tab" data-bs-target="#guru-pane" type="button" role="tab">Berdasarkan Guru</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="ruang-tab" data-bs-toggle="tab" data-bs-target="#ruang-pane" type="button" role="tab">Berdasarkan Ruangan</button>
            </li>
        </ul>

        <div class="tab-content" id="jadwalTabContent">
            <div class="tab-pane fade show active" id="kelas-pane" role="tabpanel" tabindex="0">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Rombel</label>
                        <select class="form-select" id="selectKelas" onchange="loadJadwal('kelas', this.value)">
                            <option value="">-- Silakan Pilih --</option>
                            <?php foreach ($kelas as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= esc($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <button class="btn btn-danger me-2" onclick="exportData('pdf')"><i class="bi bi-file-pdf"></i> Export PDF</button>
                        <button class="btn btn-success" onclick="exportData('excel')"><i class="bi bi-file-excel"></i> Export Excel</button>
                    </div>
                </div>
                <div id="jadwalContainerKelas">
                    <div class="text-center p-5 text-muted bg-light rounded">
                        <i class="bi bi-calendar-event fs-1 mb-3 d-block"></i>
                        Silakan pilih rombel terlebih dahulu untuk melihat jadwal.
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="guru-pane" role="tabpanel" tabindex="0">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Guru</label>
                        <select class="form-select" id="selectGuru" onchange="loadJadwal('guru', this.value)">
                            <option value="">-- Silakan Pilih --</option>
                            <?php foreach ($guru as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= esc($g['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <button class="btn btn-danger me-2" onclick="exportData('pdf')"><i class="bi bi-file-pdf"></i> Export PDF</button>
                        <button class="btn btn-success" onclick="exportData('excel')"><i class="bi bi-file-excel"></i> Export Excel</button>
                    </div>
                </div>
                <div id="jadwalContainerGuru">
                    <div class="text-center p-5 text-muted bg-light rounded">
                        <i class="bi bi-person-video3 fs-1 mb-3 d-block"></i>
                        Silakan pilih guru terlebih dahulu.
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="ruang-pane" role="tabpanel" tabindex="0">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Ruangan</label>
                        <select class="form-select" id="selectRuangan" onchange="loadJadwal('ruangan', this.value)">
                            <option value="">-- Silakan Pilih --</option>
                            <?php foreach ($ruangan as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= esc($r['nama']) ?> (<?= ucfirst($r['tipe']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <button class="btn btn-danger me-2" onclick="exportData('pdf')"><i class="bi bi-file-pdf"></i> Export PDF</button>
                        <button class="btn btn-success" onclick="exportData('excel')"><i class="bi bi-file-excel"></i> Export Excel</button>
                    </div>
                </div>
                <div id="jadwalContainerRuangan">
                    <div class="text-center p-5 text-muted bg-light rounded">
                        <i class="bi bi-door-open fs-1 mb-3 d-block"></i>
                        Silakan pilih ruangan terlebih dahulu.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?php if ($active_ta && $has_jadwal): ?>
<?= $this->section('scripts') ?>
<script>
    function loadJadwal(type, id) {
        if (!id) return;

        let containerId = '#jadwalContainer' + type.charAt(0).toUpperCase() + type.slice(1);

        $(containerId).html(`
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Memuat jadwal...</p>
            </div>
        `);

        $.get(`<?= base_url('kepala-sekolah/jadwal/') ?>${type}/${id}`, function(data) {
            if (data.trim() === '') {
                $(containerId).html(`
                    <div class="alert alert-danger border-0 rounded bg-danger-subtle text-center py-4">
                        <i class="bi bi-x-circle-fill fs-2 mb-2 text-danger d-block"></i>
                        Gagal memuat jadwal. Pastikan Tahun Ajaran Aktif tersedia.
                    </div>
                `);
            } else {
                $(containerId).html(data);
            }
        }).fail(function() {
            $(containerId).html(`
                <div class="alert alert-danger">Terjadi kesalahan koneksi saat memuat jadwal.</div>
            `);
        });
    }

    function exportData(format) {
        let type = '';
        let id = '';

        if ($('#kelas-pane').hasClass('active')) {
            type = 'kelas';
            id = $('#selectKelas').val();
        } else if ($('#guru-pane').hasClass('active')) {
            type = 'guru';
            id = $('#selectGuru').val();
        } else if ($('#ruang-pane').hasClass('active')) {
            type = 'ruangan';
            id = $('#selectRuangan').val();
        }

        if (!id) {
            alert('Silakan pilih data terlebih dahulu sebelum melakukan ekspor.');
            return;
        }

        window.open(`<?= base_url('kepala-sekolah/jadwal/export/') ?>${format}-${type}-${id}`, '_blank');
    }
</script>
<?= $this->endSection() ?>
<?php endif; ?>
