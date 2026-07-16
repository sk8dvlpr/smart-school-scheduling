<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="mb-3">
    <a href="<?= base_url('kurikulum/kelas') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Kurikulum Rombel — <?= esc($kelas['nama']) ?></h5>
        <small class="text-muted">
            <?= esc($kelas['nama_jurusan']) ?> | <?= esc($kelas['ta_nama']) ?> |
            Total JP: <strong class="<?= $total_jp === 48 ? 'text-success' : 'text-warning' ?>"><?= $total_jp ?> / 48</strong>
            <?php if ($total_jp !== 48): ?>
                <span class="badge bg-warning text-dark ms-1">Target 48 JP/minggu</span>
            <?php endif; ?>
        </small>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form action="<?= base_url('kurikulum/kelas/' . $kelas['id'] . '/mapel') ?>" method="post" class="row g-3 mb-4 border-bottom pb-4" id="addForm">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label">Mata Pelajaran</label>
                <select name="mapel_id" id="mapel_id" class="form-select" required onchange="fillJamFromMapel()">
                    <option value="">-- Pilih mapel --</option>
                    <?php foreach ($available_mapel as $m): ?>
                        <option value="<?= $m['id'] ?>" data-jam="<?= esc($m['jam_per_minggu'] ?? 2) ?>">
                            <?= esc($m['kode'] . ' — ' . $m['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">JP/Minggu</label>
                <input type="number" name="jam_per_minggu" id="jam_per_minggu" class="form-control" min="1" value="2" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="butuh_lab" id="butuh_lab" value="1" onchange="toggleLab()">
                    <label class="form-check-label" for="butuh_lab">Butuh Lab</label>
                </div>
            </div>
            <div class="col-md-3" id="labWrapper" style="display:none;">
                <label class="form-label">Lab utama (preferensi)</label>
                <select name="lab_id" id="lab_id" class="form-select">
                    <option value="">-- Pilih lab --</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>"><?= esc($lab['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Saat generate, sistem dapat memakai lab jurusan lain jika lab utama penuh; satu hari tetap satu lab untuk mapel ini.</div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Mapel</th>
                        <th>JP/Minggu</th>
                        <th>Lab Utama</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mapel_list)): ?>
                        <tr><td colspan="4" class="text-center text-muted">Belum ada mapel kurikulum.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mapel_list as $row): ?>
                        <tr>
                            <td><?= esc($row['mapel_kode'] . ' — ' . $row['mapel_nama']) ?></td>
                            <td><?= esc($row['jam_per_minggu']) ?> JP</td>
                            <td>
                                <?php if ($row['butuh_lab']): ?>
                                    <span class="badge bg-info text-dark"><?= esc($row['lab_nama'] ?? 'Lab') ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Homeroom</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info text-white" onclick="editRow(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="<?= base_url('kurikulum/kelas/' . $kelas['id'] . '/mapel/' . $row['mapel_id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus mapel ini?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit Mapel Kurikulum</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold" id="editMapelName"></p>
                    <div class="mb-3">
                        <label class="form-label">JP/Minggu</label>
                        <input type="number" name="jam_per_minggu" id="edit_jp" class="form-control" min="1" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="butuh_lab" id="edit_butuh_lab" value="1" onchange="toggleEditLab()">
                        <label class="form-check-label" for="edit_butuh_lab">Butuh Lab</label>
                    </div>
                    <div class="mb-3" id="editLabWrapper" style="display:none;">
                        <label class="form-label">Lab utama (preferensi)</label>
                        <select name="lab_id" id="edit_lab_id" class="form-select">
                            <option value="">-- Pilih lab --</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?= $lab['id'] ?>"><?= esc($lab['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Solver boleh overflow ke lab jurusan lain; satu hari tetap satu lab.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));

    function fillJamFromMapel() {
        const select = document.getElementById('mapel_id');
        const option = select.options[select.selectedIndex];
        const jam = option?.dataset?.jam;
        if (jam) {
            document.getElementById('jam_per_minggu').value = jam;
        }
    }

    function toggleLab() {
        const on = document.getElementById('butuh_lab').checked;
        document.getElementById('labWrapper').style.display = on ? 'block' : 'none';
        document.getElementById('lab_id').required = on;
    }

    function toggleEditLab() {
        const on = document.getElementById('edit_butuh_lab').checked;
        document.getElementById('editLabWrapper').style.display = on ? 'block' : 'none';
    }

    function editRow(row) {
        document.getElementById('editForm').action = '<?= base_url('kurikulum/kelas/' . $kelas['id'] . '/mapel/') ?>' + row.mapel_id;
        document.getElementById('editMapelName').textContent = row.mapel_kode + ' — ' + row.mapel_nama;
        document.getElementById('edit_jp').value = row.jam_per_minggu;
        document.getElementById('edit_butuh_lab').checked = row.butuh_lab == 1;
        document.getElementById('edit_lab_id').value = row.lab_id || '';
        toggleEditLab();
        editModal.show();
    }
</script>
<?= $this->endSection() ?>
