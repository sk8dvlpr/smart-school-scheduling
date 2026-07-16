<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Hari Tidak Mengajar</h5>
        <small class="text-muted">Centang hari yang tidak tersedia mengajar (HC-4). Kosong = tersedia semua hari. Data ini hanya milik Anda.</small>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form action="<?= base_url('guru/hari-blokir') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <?php foreach ($hari as $h): ?>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="hari_id[]" value="<?= (int) $h['id'] ?>" id="hari_<?= (int) $h['id'] ?>"
                            <?= in_array((int) $h['id'], $blocked_ids, true) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-medium" for="hari_<?= (int) $h['id'] ?>"><?= esc($h['nama']) ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-save"></i> Simpan</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
