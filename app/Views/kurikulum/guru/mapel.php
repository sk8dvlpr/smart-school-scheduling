<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="mb-3">
    <a href="<?= base_url('kurikulum/guru') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Kompetensi Mapel — <?= esc($guru['nama']) ?></h5>
        <small class="text-muted">
            Email: <?= esc($guru['email']) ?>
            <?php if (! empty($guru['nip'])): ?> | NIP: <?= esc($guru['nip']) ?><?php endif; ?>
            | Total cap: <strong><?= $total_cap ?> JP/minggu</strong>
        </small>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form action="<?= base_url('kurikulum/guru/' . $guru['id'] . '/mapel') ?>" method="post" class="row g-3 mb-4 border-bottom pb-4">
            <?= csrf_field() ?>
            <div class="col-md-5">
                <label class="form-label">Mata Pelajaran</label>
                <select name="mapel_id" class="form-select" required>
                    <option value="">-- Pilih mapel --</option>
                    <?php foreach ($available_mapel as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= esc($m['kode'] . ' — ' . $m['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Max JP / Minggu</label>
                <input type="number" name="max_jam_per_minggu" class="form-control" min="1" value="4" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Mapel</th>
                        <th>Max JP/Minggu</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mapel_list)): ?>
                        <tr><td colspan="3" class="text-center text-muted">Belum ada kompetensi mapel.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mapel_list as $row): ?>
                        <tr>
                            <td><?= esc($row['mapel_kode'] . ' — ' . $row['mapel_nama']) ?></td>
                            <td><?= esc($row['max_jam_per_minggu']) ?> JP</td>
                            <td>
                                <form action="<?= base_url('kurikulum/guru/' . $guru['id'] . '/mapel/' . $row['mapel_id']) ?>" method="post" onsubmit="return confirm('Hapus kompetensi ini?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
