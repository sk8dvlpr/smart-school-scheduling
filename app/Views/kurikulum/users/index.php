<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">Data User</h5>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal()">
            <i class="bi bi-plus-lg"></i> Tambah User
        </button>
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2 mb-3">
            <small><i class="bi bi-info-circle me-1"></i> Untuk <strong>guru yang mengajar</strong>, gunakan <a href="<?= base_url('kurikulum/guru') ?>">Manajemen Guru</a>. Modul ini untuk staff non-mengajar (Kepala Sekolah, Kurikulum tanpa mengajar).</small>
        </div>
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
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
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
                        <th>Email</th>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($users as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= esc($row['email']) ?></td>
                        <td>
                            <?php if (! empty($row['nip'])): ?>
                                <span class="badge bg-secondary"><?= esc($row['nip']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-medium"><?= esc($row['nama']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= esc(ucwords(str_replace('_', ' ', $row['role']))) ?></span></td>
                        <td>
                            <?php if ($row['is_active']): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['role'] !== 'guru'): ?>
                            <button type="button" class="btn btn-sm btn-info text-white" onclick="editData(<?= $row['id'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ((int) $row['id'] !== (int) session()->get('user_id')): ?>
                            <form action="<?= base_url('kurikulum/users/' . $row['id'] . '/reset-password') ?>" method="post" class="d-inline" onsubmit="return confirm('Reset password ke password123?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-warning" title="Reset Password">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            <form action="<?= base_url('kurikulum/users/' . $row['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus user ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="dataForm" action="<?= base_url('kurikulum/users') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" id="nama" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">NIP <small class="text-muted">(opsional)</small></label>
                            <input type="text" class="form-control" name="nip" id="nip">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" class="form-control" name="no_telp" id="no_telp">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="kurikulum">Kurikulum</option>
                                <option value="kepala_sekolah">Kepala Sekolah</option>
                            </select>
                            <div class="form-text">Guru yang mengajar didaftarkan via <a href="<?= base_url('kurikulum/guru') ?>">Manajemen Guru</a>.</div>
                        </div>
                        <div class="col-md-6 pt-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2" id="passwordInfo">
                        <small><i class="bi bi-info-circle me-1"></i> Password default: <strong>password123</strong> (wajib ganti saat login pertama)</small>
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
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
            responsive: true
        });
    });

    const modal = new bootstrap.Modal(document.getElementById('formModal'));

    function openModal() {
        $('#formMethod').val('POST');
        $('#dataForm').attr('action', '<?= base_url('kurikulum/users') ?>');
        $('#modalTitle').text('Tambah User');
        $('#dataForm')[0].reset();
        $('#is_active').prop('checked', true);
        $('#passwordInfo').show();
        modal.show();
    }

    function editData(id) {
        $.get('<?= base_url('kurikulum/users/') ?>' + id, function(data) {
            $('#formMethod').val('PUT');
            $('#dataForm').attr('action', '<?= base_url('kurikulum/users/') ?>' + id);
            $('#modalTitle').text('Edit User');
            $('#email').val(data.email);
            $('#nama').val(data.nama);
            $('#nip').val(data.nip || '');
            $('#no_telp').val(data.no_telp);
            $('#role').val(data.role);
            $('#is_active').prop('checked', data.is_active == 1);
            $('#passwordInfo').hide();
            modal.show();
        }).fail(function() { alert('Gagal mengambil data'); });
    }
</script>
<?= $this->endSection() ?>
