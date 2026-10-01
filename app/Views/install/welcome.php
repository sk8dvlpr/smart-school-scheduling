<?= $this->extend('install/layout') ?>
<?= $this->section('content') ?>
<h2 class="h5 mb-3">Selamat datang</h2>
<p class="text-muted">Instalasi ini akan menyiapkan database dan akun kurikulum admin pertama. Master data sekolah (ruangan, timeslot, guru, rombel, dll.) diisi kemudian lewat aplikasi.</p>

<form method="get" action="<?= site_url('install') ?>" class="mb-4">
    <label class="form-label">Bahasa antarmuka (opsional)</label>
    <select name="lang" class="form-select" onchange="this.form.submit()">
        <option value="id" <?= ($locale ?? 'id') === 'id' ? 'selected' : '' ?>>Indonesia</option>
        <option value="en" <?= ($locale ?? 'id') === 'en' ? 'selected' : '' ?>>English</option>
    </select>
</form>

<a href="<?= site_url('install/requirements') ?>" class="btn btn-primary">Mulai instalasi</a>
<?= $this->endSection() ?>
