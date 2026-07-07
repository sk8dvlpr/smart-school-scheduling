<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Laporan Jam Mengajar Guru</h4>
            <p class="text-muted mb-0">
                <?php if ($active_ta): ?>
                    Tahun ajaran: <?= esc($active_ta['nama']) ?>
                <?php else: ?>
                    Tidak ada tahun ajaran aktif.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($active_ta && $has_jadwal && $rows !== []): ?>
        <div class="d-flex gap-2">
            <a href="<?= base_url('kepala-sekolah/laporan/guru-jam/export?format=pdf&guru_id=' . (int) $filter_guru . '&mapel_id=' . (int) $filter_mapel) ?>"
               class="btn btn-outline-danger" target="_blank">
                <i class="bi bi-file-pdf"></i> Export PDF
            </a>
            <a href="<?= base_url('kepala-sekolah/laporan/guru-jam/export?format=excel&guru_id=' . (int) $filter_guru . '&mapel_id=' . (int) $filter_mapel) ?>"
               class="btn btn-outline-success">
                <i class="bi bi-file-excel"></i> Export Excel
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
    <p class="text-muted mb-0">Laporan akan tersedia setelah jadwal di-generate oleh kurikulum.</p>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= base_url('kepala-sekolah/laporan/guru-jam') ?>" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Guru</label>
                <select name="guru_id" class="form-select">
                    <option value="">Semua Guru</option>
                    <?php foreach ($guru_list as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= (int) $filter_guru === (int) $g['id'] ? 'selected' : '' ?>>
                            <?= esc($g['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Mata Pelajaran</label>
                <select name="mapel_id" class="form-select">
                    <option value="">Semua Mapel</option>
                    <?php foreach ($mapel_list as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= (int) $filter_mapel === (int) $m['id'] ? 'selected' : '' ?>>
                            <?= esc($m['kode']) ?> — <?= esc($m['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Terapkan Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if ($rows === []): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-search fs-1 d-block mb-2"></i>
                Tidak ada data untuk filter yang dipilih.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="laporanTable">
                    <thead class="table-light">
                        <tr>
                            <th>NIP</th>
                            <th>Nama Guru</th>
                            <th>Kode Mapel</th>
                            <th>Nama Mapel</th>
                            <th class="text-end">JP/Minggu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped as $group): ?>
                            <?php foreach ($group['rows'] as $item): ?>
                            <tr>
                                <td><?= esc($group['nip'] ?: '-') ?></td>
                                <td><?= esc($group['nama_guru']) ?></td>
                                <td><?= esc($item['mapel_kode'] ?? '') ?></td>
                                <td><?= esc($item['mapel_nama'] ?? '') ?></td>
                                <td class="text-end"><?= (int) $item['total_jp'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-secondary fw-bold">
                                <td colspan="3" class="text-end">Subtotal <?= esc($group['nama_guru']) ?></td>
                                <td></td>
                                <td class="text-end"><?= (int) $group['subtotal'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mb-0 mt-3">
                <i class="bi bi-info-circle me-1"></i>
                Data dari jadwal generate terakhir — untuk perhitungan gaji manual.
            </p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?php if ($active_ta && $has_jadwal && $rows !== []): ?>
<?= $this->section('scripts') ?>
<script>
    $(document).ready(function() {
        $('#laporanTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            pageLength: 25,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            }
        });
    });
</script>
<?= $this->endSection() ?>
<?php endif; ?>
