<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="mb-3">
    <a href="<?= base_url('kurikulum/guru') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Hari Blokir — <?= esc($guru['nama']) ?></h5>
        <small class="text-muted">Centang hari yang tidak tersedia mengajar (HC13). Kosong = tersedia semua hari.</small>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>

        <form action="<?= base_url('kurikulum/guru/' . $guru['id'] . '/hari-blokir') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <?php foreach ($hari as $h): ?>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="hari_id[]" value="<?= $h['id'] ?>" id="hari_<?= $h['id'] ?>"
                            <?= in_array($h['id'], $blocked_ids, true) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-medium" for="hari_<?= $h['id'] ?>"><?= esc($h['nama']) ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-save"></i> Simpan Hari Blokir</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
