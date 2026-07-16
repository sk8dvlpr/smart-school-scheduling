<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<?= view('components/datatables_styles') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">Data Rombel</h5>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal()">
            <i class="bi bi-plus-lg"></i> Tambah Data
        </button>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= esc(session()->getFlashdata('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= esc(session()->getFlashdata('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                <?php foreach (session()->getFlashdata('errors') as $error) : ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= base_url('kurikulum/kelas') ?>" class="row g-3 align-items-end mb-4">
            <div class="col-md-3">
                <label class="form-label">Tahun Ajaran</label>
                <select name="tahun_ajaran_id" class="form-select">
                    <option value="">Semua Tahun Ajaran</option>
                    <?php foreach ($ta as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) ($filter_tahun_ajaran ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= esc($t['nama']) ?> — <?= ucfirst(esc($t['semester'])) ?>
                            <?= ! empty($t['is_active']) ? '(Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tingkat</label>
                <select name="tingkat" class="form-select">
                    <option value="">Semua Tingkat</option>
                    <?php foreach (['X', 'XI', 'XII'] as $tingkatOpt): ?>
                        <option value="<?= $tingkatOpt ?>" <?= ($filter_tingkat ?? '') === $tingkatOpt ? 'selected' : '' ?>>
                            <?= $tingkatOpt ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Jurusan</label>
                <select name="jurusan_id" class="form-select">
                    <option value="">Semua Jurusan</option>
                    <?php foreach ($jurusan as $j): ?>
                        <option value="<?= (int) $j['id'] ?>" <?= (int) ($filter_jurusan ?? 0) === (int) $j['id'] ? 'selected' : '' ?>>
                            <?= esc($j['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Terapkan
                </button>
                <a href="<?= base_url('kurikulum/kelas') ?>" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>

        <table id="dataTable" class="table table-striped table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>Tahun Ajaran</th>
                        <th>Tingkat</th>
                        <th>Nama Rombel</th>
                        <th>Jurusan</th>
                        <th>Ruang Rombel (Homeroom)</th>
                        <th>Total JP</th>
                        <th width="18%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($kelas as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= esc($row['ta_nama']) ?></span>
                            <small class="text-muted d-block"><?= ucfirst(esc($row['semester'])) ?></small>
                        </td>
                        <td><?= esc($row['tingkat']) ?></td>
                        <td class="fw-bold text-primary"><?= esc($row['nama']) ?></td>
                        <td><?= esc($row['nama_jurusan']) ?></td>
                        <td><?= esc($row['nama_ruangan']) ?></td>
                        <td>
                            <?php $jp = (int) ($row['total_jp'] ?? 0); ?>
                            <span class="badge <?= $jp === 48 ? 'bg-success' : ($jp > 0 ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= $jp ?> / 48 JP
                            </span>
                        </td>
                        <td>
                            <a href="<?= base_url('kurikulum/kelas/' . $row['id'] . '/mapel') ?>" class="btn btn-sm btn-outline-primary" title="Kelola Mapel">
                                <i class="bi bi-book"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-info text-white" onclick="editData(<?= $row['id'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="<?= base_url('kurikulum/kelas/' . $row['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="dataForm" action="<?= base_url('kurikulum/kelas') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Rombel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran</label>
                        <select class="form-select" name="tahun_ajaran_id" id="tahun_ajaran_id" required>
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            <?php foreach ($ta as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= esc($t['nama']) ?> - <?= ucfirst($t['semester']) ?> 
                                    <?= $t['is_active'] ? '(Aktif)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Tingkat</label>
                            <select class="form-select" name="tingkat" id="tingkat" required>
                                <option value="">-- Pilih --</option>
                                <option value="X">X (10)</option>
                                <option value="XI">XI (11)</option>
                                <option value="XII">XII (12)</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Jurusan</label>
                            <select class="form-select" name="jurusan_id" id="jurusan_id" required>
                                <option value="">-- Pilih Jurusan --</option>
                                <?php foreach ($jurusan as $j): ?>
                                    <option value="<?= $j['id'] ?>"><?= esc($j['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Rombel Lengkap</label>
                        <input type="text" class="form-control" name="nama" id="nama" placeholder="Contoh: X TKJ 1" required>
                        <div class="form-text">Pastikan nama unik dalam satu tahun ajaran.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ruang Rombel (Homeroom)</label>
                        <select class="form-select" name="ruangan_id" id="ruangan_id" required>
                            <option value="">-- Pilih Ruang Rombel --</option>
                            <?php foreach ($ruangan as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= esc($r['nama']) ?> (Kap: <?= $r['kapasitas'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Berdasarkan constraint HC10, rombel akan selalu berada di ruangan ini kecuali untuk mata pelajaran praktek (Lab).</div>
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
<?= view('components/datatables_scripts') ?>
<script>
    $(document).ready(function() {
        $('#dataTable').DataTable();
    });

    const modal = new bootstrap.Modal(document.getElementById('formModal'));
    
    function openModal() {
        $('#formMethod').val('POST');
        $('#dataForm').attr('action', '<?= base_url('kurikulum/kelas') ?>');
        $('#modalTitle').text('Tambah Rombel');
        $('#dataForm')[0].reset();
        modal.show();
    }

    function editData(id) {
        $.get('<?= base_url('kurikulum/kelas/') ?>' + id, function(data) {
            $('#formMethod').val('PUT');
            $('#dataForm').attr('action', '<?= base_url('kurikulum/kelas/') ?>' + id);
            $('#modalTitle').text('Edit Rombel');
            
            $('#tahun_ajaran_id').val(data.tahun_ajaran_id);
            $('#tingkat').val(data.tingkat);
            $('#jurusan_id').val(data.jurusan_id);
            $('#nama').val(data.nama);
            $('#ruangan_id').val(data.ruangan_id);
            
            modal.show();
        }).fail(function() {
            alert('Gagal mengambil data');
        });
    }
</script>
<?= $this->endSection() ?>
