<?= $this->extend('install/layout') ?>
<?= $this->section('content') ?>
<h2 class="h5 mb-3">Jalankan instalasi</h2>
<p class="text-muted">Proses ini akan menulis file <code>.env</code>, menjalankan migrasi, menyiapkan daftar hari, dan membuat akun admin. Timeslot dikonfigurasi setelah login.</p>

<ul class="small text-muted">
    <li>Database: <strong><?= esc($wizard['db']['database'] ?? '') ?></strong></li>
    <li>Sekolah: <strong><?= esc($wizard['school']['nama_sekolah'] ?? '') ?></strong></li>
    <li>Admin: <strong><?= esc($wizard['admin']['email'] ?? '') ?></strong></li>
</ul>

<form method="post" action="<?= site_url('install/run') ?>">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between mt-4">
        <a href="<?= site_url('install/setup') ?>" class="btn btn-outline-secondary">Kembali</a>
        <button type="submit" class="btn btn-success">Instal sekarang</button>
    </div>
</form>
<?= $this->endSection() ?>
