<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title">Hari Blokir — <?= esc($guru['nama']) ?></h1>
        <p class="s3-page-desc">Centang hari yang tidak tersedia mengajar (HC-4). Kosong = tersedia semua hari.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('kurikulum/guru') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<div class="card">
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
