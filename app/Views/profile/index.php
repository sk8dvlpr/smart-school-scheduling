<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Informasi Profil</h5>
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('profile') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">NIP <small class="text-muted">(opsional)</small></label>
                        <input type="text" class="form-control" value="<?= esc($user['nip'] ?? '') ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= esc($user['nama'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= esc($user['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="no_telp" class="form-control" value="<?= esc($user['no_telp'] ?? '') ?>">
                    </div>
                    <button class="btn btn-primary" type="submit">Simpan Profil</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Ganti Password</h5>
                <form method="post" action="<?= base_url('profile/password') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Password Lama</label>
                        <input type="password" name="password_lama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password_baru" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="password_konfirmasi" class="form-control" minlength="6" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Simpan Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
