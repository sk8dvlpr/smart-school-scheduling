<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="s3-page-header">
    <div>
        <h1 class="s3-page-title"><i class="bi bi-sliders"></i> Preferensi Jadwal Mengajar</h1>
        <p class="s3-page-desc">Atur hari/jam yang Anda suka atau hindari. Digunakan algoritma GA (SC-7) saat generate — bukan larangan keras (gunakan Hari Tidak Mengajar untuk itu).</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?= view('components/guru_preferensi_form', [
            'hari'            => $hari,
            'timeslotsByHari' => $timeslotsByHari,
            'formState'       => $formState,
            'formAction'      => base_url('guru/preferensi'),
            'backUrl'         => null,
            'subtitle'        => 'Cukup pilih Suka / Hindari per hari. Buka “Detail jam JP” hanya jika perlu preferensi jam tertentu.',
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('components/guru_preferensi_form_script') ?>
<?= $this->endSection() ?>
