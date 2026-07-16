<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold"><i class="bi bi-sliders"></i> Preferensi Jadwal Mengajar</h4>
        <p class="text-muted mb-0">Atur hari/jam yang Anda suka atau hindari. Digunakan algoritma GA (SC-7) saat generate — bukan larangan keras (gunakan Hari Tidak Mengajar untuk itu).</p>
    </div>
</div>

<div class="card border-0 shadow-sm">
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
