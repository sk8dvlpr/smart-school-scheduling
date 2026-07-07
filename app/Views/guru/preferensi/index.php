<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold"><i class="bi bi-sliders"></i> Preferensi Jadwal Mengajar</h4>
        <p class="text-muted">Atur hari/slot yang Anda prefer atau hindari. Digunakan algoritma GA (SC-7) saat generate jadwal.</p>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>

<form action="<?= base_url('guru/preferensi') ?>" method="post">
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Daftar Preferensi</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow"><i class="bi bi-plus"></i> Tambah</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="prefTable">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Slot JP (opsional)</th>
                            <th>Tipe</th>
                            <th>Bobot (1-10)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = $preferensi ?: [['hari_id' => '', 'timeslot_id' => '', 'tipe' => 'prefer', 'bobot' => 5]];
                        foreach ($rows as $i => $p):
                        ?>
                        <tr>
                            <td>
                                <select name="preferensi[<?= $i ?>][hari_id]" class="form-select form-select-sm pref-hari" required>
                                    <option value="">— Pilih —</option>
                                    <?php foreach ($hari as $h): ?>
                                    <option value="<?= (int) $h['id'] ?>" <?= (int) ($p['hari_id'] ?? 0) === (int) $h['id'] ? 'selected' : '' ?>><?= esc($h['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="preferensi[<?= $i ?>][timeslot_id]" class="form-select form-select-sm pref-slot">
                                    <option value="">Semua slot hari ini</option>
                                    <?php foreach ($timeslotsByHari as $hid => $slots): ?>
                                        <?php foreach ($slots as $ts): ?>
                                            <?php if (($ts['tipe'] ?? '') !== 'jp') { continue; } ?>
                                    <option value="<?= (int) $ts['id'] ?>" data-hari="<?= (int) $hid ?>"
                                        <?= (int) ($p['timeslot_id'] ?? 0) === (int) $ts['id'] ? 'selected' : '' ?>>
                                        <?= esc($hari[array_search($hid, array_column($hari, 'id'))]['nama'] ?? '') ?> — JP <?= (int) $ts['jam_ke'] ?>
                                    </option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="preferensi[<?= $i ?>][tipe]" class="form-select form-select-sm">
                                    <option value="prefer" <?= ($p['tipe'] ?? '') === 'prefer' ? 'selected' : '' ?>>Prefer</option>
                                    <option value="avoid" <?= ($p['tipe'] ?? '') === 'avoid' ? 'selected' : '' ?>>Hindari</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="preferensi[<?= $i ?>][bobot]" class="form-control form-control-sm" min="1" max="10" value="<?= (int) ($p['bobot'] ?? 5) ?>">
                            </td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Preferensi</button>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let rowIdx = <?= count($rows) ?>;
$('#btnAddRow').on('click', function() {
    const $first = $('#prefTable tbody tr:first').clone();
    $first.find('select, input').each(function() {
        const name = $(this).attr('name');
        if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + rowIdx + ']'));
        if ($(this).is('select')) $(this).prop('selectedIndex', 0);
        if ($(this).is('input')) $(this).val(5);
    });
    $('#prefTable tbody').append($first);
    rowIdx++;
});
$(document).on('click', '.btn-remove', function() {
    if ($('#prefTable tbody tr').length > 1) $(this).closest('tr').remove();
});
$('.pref-hari').on('change', function() {
    const hariId = $(this).val();
    const $slot = $(this).closest('tr').find('.pref-slot');
    $slot.find('option').each(function() {
        const dh = $(this).data('hari');
        if (!dh) return;
        $(this).toggle(!hariId || String(dh) === String(hariId));
    });
    $slot.val('');
});
</script>
<?= $this->endSection() ?>
