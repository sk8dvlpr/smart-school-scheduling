<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">Data Mata Pelajaran</h5>
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

        <div class="table-responsive">
            <table id="dataTable" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="10%">Kode</th>
                        <th>Warna</th>
                        <th>Mata Pelajaran</th>
                        <th>JP/Minggu</th>
                        <th>Tipe</th>
                        <th>Jurusan Khusus</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($mapel as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge bg-secondary"><?= esc($row['kode']) ?></span></td>
                        <td>
                            <div style="width: 25px; height: 25px; background-color: <?= esc($row['warna']) ?>; border-radius: 4px; border: 1px solid #ccc;"></div>
                        </td>
                        <td class="fw-medium"><?= esc($row['nama']) ?></td>
                        <td><?= esc($row['jam_per_minggu'] ?? 2) ?> JP</td>
                        <td>
                            <?php if ($row['tipe'] == 'umum'): ?>
                                <span class="badge bg-info text-dark">Umum</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Kejuruan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['nama_jurusan']): ?>
                                <?= esc($row['nama_jurusan']) ?>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info text-white" onclick="editData(<?= $row['id'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="<?= base_url('kurikulum/mapel/' . $row['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
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
</div>

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="dataForm" action="<?= base_url('kurikulum/mapel') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Kode Mapel</label>
                            <input type="text" class="form-control" name="kode" id="kode" placeholder="Contoh: BIG, MTK, TKJ-1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Warna Label</label>
                            <input type="color" class="form-control form-control-color w-100" name="warna" id="warna" value="#4F46E5" required>
                            <div class="form-text small">Untuk di jadwal.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran</label>
                        <input type="text" class="form-control" name="nama" id="nama" placeholder="Contoh: Bahasa Inggris" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jam Pelajaran / Minggu (Default)</label>
                        <input type="number" class="form-control" name="jam_per_minggu" id="jam_per_minggu" min="1" value="2" required>
                        <div class="form-text small">Nilai default saat mapel ditambahkan ke kurikulum kelas.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipe Mata Pelajaran</label>
                        <select class="form-select" name="tipe" id="tipe" required onchange="toggleJurusan(this.value)">
                            <option value="umum">Umum (Bisa untuk semua jurusan)</option>
                            <option value="kejuruan">Kejuruan (Khusus jurusan tertentu)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="jurusanWrapper" style="display: none;">
                        <label class="form-label">Jurusan Khusus</label>
                        <select class="form-select" name="jurusan_id" id="jurusan_id">
                            <option value="">-- Pilih Jurusan --</option>
                            <?php foreach ($jurusan as $j): ?>
                                <option value="<?= $j['id'] ?>"><?= esc($j['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            responsive: true
        });
    });

    const modal = new bootstrap.Modal(document.getElementById('formModal'));
    
    function toggleJurusan(tipe) {
        if (tipe === 'kejuruan') {
            $('#jurusanWrapper').show();
            $('#jurusan_id').prop('required', true);
        } else {
            $('#jurusanWrapper').hide();
            $('#jurusan_id').val('');
            $('#jurusan_id').prop('required', false);
        }
    }
    
    function openModal() {
        $('#formMethod').val('POST');
        $('#dataForm').attr('action', '<?= base_url('kurikulum/mapel') ?>');
        $('#modalTitle').text('Tambah Mata Pelajaran');
        $('#dataForm')[0].reset();
        $('#warna').val('#4F46E5'); // reset default color
        $('#jam_per_minggu').val(2);
        toggleJurusan('umum');
        modal.show();
    }

    function editData(id) {
        $.get('<?= base_url('kurikulum/mapel/') ?>' + id, function(data) {
            $('#formMethod').val('PUT');
            $('#dataForm').attr('action', '<?= base_url('kurikulum/mapel/') ?>' + id);
            $('#modalTitle').text('Edit Mata Pelajaran');
            
            $('#kode').val(data.kode);
            $('#nama').val(data.nama);
            $('#jam_per_minggu').val(data.jam_per_minggu || 2);
            $('#warna').val(data.warna);
            $('#tipe').val(data.tipe);
            
            toggleJurusan(data.tipe);
            $('#jurusan_id').val(data.jurusan_id);
            
            modal.show();
        }).fail(function() {
            alert('Gagal mengambil data');
        });
    }
</script>
<?= $this->endSection() ?>
