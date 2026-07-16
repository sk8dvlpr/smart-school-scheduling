<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Pengaturan Aplikasi</h5>
        <small class="text-muted">Nama sekolah dan logo ditampilkan di login, menu, favicon, dan export PDF.</small>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ((array) session()->getFlashdata('errors') as $err): ?>
                        <li><?= esc(is_array($err) ? implode(', ', $err) : $err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('kurikulum/pengaturan') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="nama_sekolah" class="form-label fw-medium">Nama Sekolah / Aplikasi</label>
                <input type="text" class="form-control" id="nama_sekolah" name="nama_sekolah" required maxlength="150"
                       value="<?= esc(old('nama_sekolah', $settings['nama_sekolah'] ?? '')) ?>">
            </div>

            <div class="mb-3">
                <label for="logo" class="form-label fw-medium">Logo Sekolah</label>
                <?php if (! empty($branding['logo_url'])): ?>
                    <div class="mb-2">
                        <img src="<?= esc($branding['logo_url']) ?>" alt="Logo" style="max-height: 80px;" class="border rounded p-1 bg-white">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="hapus_logo" value="1" id="hapus_logo">
                        <label class="form-check-label" for="hapus_logo">Hapus logo saat ini</label>
                    </div>
                <?php endif; ?>
                <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, atau WebP. Maks. 2 MB.</div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Pengaturan</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
