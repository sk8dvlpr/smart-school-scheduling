<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="mb-3">
    <a href="<?= base_url('kurikulum/guru') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Preferensi Jadwal — <?= esc($guru['nama']) ?></h5>
        <small class="text-muted">SC-7: preferensi/hindari. Untuk larangan mutlak gunakan Hari Blokir (HC-4).</small>
    </div>
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
