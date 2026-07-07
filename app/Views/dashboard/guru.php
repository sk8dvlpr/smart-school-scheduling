<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-5">
                <i class="bi bi-person-badge display-1 text-primary-custom mb-3"></i>
                <h3 class="fw-bold">Selamat Datang, <?= session()->get('nama') ?>!</h3>
                <p class="text-muted lead">Ini adalah dashboard khusus Guru. Anda dapat melihat jadwal mengajar Anda di menu samping.</p>
                <a href="<?= base_url('guru/jadwal') ?>" class="btn btn-primary btn-lg mt-3">
                    <i class="bi bi-calendar-week me-2"></i> Lihat Jadwal Mengajar
                </a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
