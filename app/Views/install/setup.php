<?= $this->extend('install/layout') ?>
<?= $this->section('content') ?>
<h2 class="h5 mb-3">Profil sekolah & admin kurikulum</h2>

<form method="post" action="<?= site_url('install/setup') ?>">
    <?= csrf_field() ?>
    <h3 class="h6 text-uppercase text-muted mt-2">Sekolah</h3>
    <div class="mb-3">
        <label class="form-label">Nama sekolah</label>
        <input type="text" name="nama_sekolah" class="form-control" value="<?= esc(old('nama_sekolah', 'Smart School Scheduling')) ?>" required minlength="3">
    </div>

    <h3 class="h6 text-uppercase text-muted mt-4">Admin kurikulum</h3>
    <div class="mb-3">
        <label class="form-label">Nama lengkap</label>
        <input type="text" name="admin_nama" class="form-control" value="<?= esc(old('admin_nama')) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Email login</label>
        <input type="email" name="admin_email" class="form-control" value="<?= esc(old('admin_email')) ?>" required>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Password</label>
            <input type="password" name="admin_password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="col-md-6">
            <label class="form-label">Konfirmasi password</label>
            <input type="password" name="admin_password_confirm" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
    </div>

    <h3 class="h6 text-uppercase text-muted mt-4">Template data</h3>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="template" id="tpl-empty" value="empty" checked>
        <label class="form-check-label" for="tpl-empty">Kosong — hari & timeslot dasar saja (disarankan)</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="template" id="tpl-demo" value="demo">
        <label class="form-check-label" for="tpl-demo">Demo legacy — muat dump contoh (opsional, bukan default)</label>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a href="<?= site_url('install/database') ?>" class="btn btn-outline-secondary">Kembali</a>
        <button type="submit" class="btn btn-primary">Lanjut</button>
    </div>
</form>
<?= $this->endSection() ?>
