<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title">Preferensi Jadwal — <?= esc($guru['nama']) ?></h1>
        <p class="s3-page-desc">SC-7: preferensi/hindari. Untuk larangan mutlak gunakan Hari Blokir (HC-4).</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('kurikulum/guru') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?= view('components/guru_preferensi_form', [
            'hari'            => $hari,
            'timeslotsByHari' => $timeslotsByHari,
            'formState'       => $formState,
            'formAction'      => base_url('kurikulum/guru/' . $guru['id'] . '/preferensi'),
            'backUrl'         => base_url('kurikulum/guru'),
            'subtitle'        => 'Klik Netral / Suka / Hindari per hari. Detail jam JP opsional. Data sama dengan yang bisa diedit guru sendiri.',
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('components/guru_preferensi_form_script') ?>
<?= $this->endSection() ?>
