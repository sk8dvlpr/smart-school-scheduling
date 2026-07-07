<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">Data Jurusan</h5>
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
                        <th width="20%">Kode</th>
                        <th>Nama Jurusan</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($jurusan as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge bg-secondary"><?= esc($row['kode']) ?></span></td>
                        <td class="fw-medium"><?= esc($row['nama']) ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info text-white" onclick="editData(<?= $row['id'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="<?= base_url('kurikulum/jurusan/' . $row['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
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
            <form id="dataForm" action="<?= base_url('kurikulum/jurusan') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Jurusan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Jurusan</label>
                        <input type="text" class="form-control" name="kode" id="kode" placeholder="Contoh: TKJ" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Jurusan</label>
                        <input type="text" class="form-control" name="nama" id="nama" placeholder="Contoh: Teknik Komputer Jaringan" required>
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
    
    function openModal() {
        $('#formMethod').val('POST');
        $('#dataForm').attr('action', '<?= base_url('kurikulum/jurusan') ?>');
        $('#modalTitle').text('Tambah Jurusan');
        $('#dataForm')[0].reset();
        modal.show();
    }

    function editData(id) {
        $.get('<?= base_url('kurikulum/jurusan/') ?>' + id, function(data) {
            $('#formMethod').val('PUT');
            $('#dataForm').attr('action', '<?= base_url('kurikulum/jurusan/') ?>' + id);
            $('#modalTitle').text('Edit Jurusan');
            
            $('#kode').val(data.kode);
            $('#nama').val(data.nama);
            
            modal.show();
        }).fail(function() {
            alert('Gagal mengambil data');
        });
    }
</script>
<?= $this->endSection() ?>
