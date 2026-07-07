<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">Data Guru</h5>
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload"></i> Import CSV
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal()">
                <i class="bi bi-plus-lg"></i> Tambah Guru
            </button>
        </div>
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
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('import_errors')): ?>
            <div class="alert alert-warning">
                <strong>Detail error import:</strong>
                <ul class="mb-0 small">
                <?php foreach (session()->getFlashdata('import_errors') as $err): ?>
                    <li><?= esc($err) ?></li>
                <?php endforeach ?>
                </ul>
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
                        <th width="22%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($guru as $row): ?>
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
                        <td><?= esc(ucfirst($row['role'])) ?></td>
                        <td>
                            <a href="<?= base_url('kurikulum/guru/' . $row['id'] . '/mapel') ?>" class="btn btn-sm btn-outline-primary" title="Kelola Mapel">
                                <i class="bi bi-book"></i>
                            </a>
                            <a href="<?= base_url('kurikulum/guru/' . $row['id'] . '/hari-blokir') ?>" class="btn btn-sm btn-outline-warning" title="Hari Blokir">
                                <i class="bi bi-calendar-x"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-info text-white" onclick="editData(<?= $row['id'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id'] ?>, '<?= esc($row['nama'], 'js') ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
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
            <form id="dataForm" action="<?= base_url('kurikulum/guru') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" name="entry_mode" id="entry_mode" value="new">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Guru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="createModeToggle" class="mb-3">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="mode_radio" id="modeNew" value="new" checked>
                            <label class="btn btn-outline-primary" for="modeNew">Guru Baru</label>
                            <input type="radio" class="btn-check" name="mode_radio" id="modeExisting" value="existing">
                            <label class="btn btn-outline-primary" for="modeExisting">Pasang ke User Existing</label>
                        </div>
                    </div>

                    <div id="existingUserFields" class="d-none mb-3">
                        <label class="form-label">Pilih User</label>
                        <select class="form-select" name="user_id" id="user_id">
                            <option value="">-- Pilih user guru/kurikulum --</option>
                            <?php foreach ($available_users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= esc($u['email'] . ' — ' . $u['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Untuk user kurikulum yang sudah ada tetapi belum punya profil mengajar.</div>
                    </div>

                    <div id="newUserFields">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" id="nama">
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
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="role">
                                <option value="guru">Guru</option>
                                <option value="kurikulum">Kurikulum (juga mengajar)</option>
                            </select>
                        </div>
                        <div class="alert alert-info py-2">
                            <small><i class="bi bi-info-circle me-1"></i> Password default: <strong>password123</strong> (wajib ganti saat login pertama)</small>
                        </div>
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

<form id="deleteForm" method="post" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="delete_user" id="delete_user" value="0">
</form>

<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('kurikulum/guru/import') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Import Guru CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Header wajib: <code>email,nama</code>. Opsional: <code>nip,role</code></p>
                    <input type="file" class="form-control" name="csv_file" accept=".csv,text/csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
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

        $('input[name="mode_radio"]').on('change', function() {
            setEntryMode($(this).val());
        });
    });

    const modal = new bootstrap.Modal(document.getElementById('formModal'));

    function setEntryMode(mode) {
        $('#entry_mode').val(mode);
        if (mode === 'existing') {
            $('#existingUserFields').removeClass('d-none');
            $('#newUserFields').addClass('d-none');
            $('#user_id').prop('required', true);
            $('#email, #nama').prop('required', false);
        } else {
            $('#existingUserFields').addClass('d-none');
            $('#newUserFields').removeClass('d-none');
            $('#user_id').prop('required', false);
            $('#email, #nama').prop('required', true);
        }
    }

    function openModal() {
        $('#formMethod').val('POST');
        $('#dataForm').attr('action', '<?= base_url('kurikulum/guru') ?>');
        $('#modalTitle').text('Tambah Guru');
        $('#dataForm')[0].reset();
        $('#modeNew').prop('checked', true);
        $('#createModeToggle').show();
        setEntryMode('new');
        modal.show();
    }

    function editData(id) {
        $.get('<?= base_url('kurikulum/guru/') ?>' + id, function(data) {
            $('#formMethod').val('PUT');
            $('#dataForm').attr('action', '<?= base_url('kurikulum/guru/') ?>' + id);
            $('#modalTitle').text('Edit Guru — ' + data.nama);
            $('#createModeToggle').hide();
            $('#existingUserFields').addClass('d-none');
            $('#newUserFields').removeClass('d-none');
            $('#entry_mode').val('new');
            $('#email').val(data.email).prop('required', true);
            $('#nama').val(data.nama).prop('required', true);
            $('#nip').val(data.nip || '');
            $('#no_telp').val(data.no_telp || '');
            $('#role').val(data.role === 'kurikulum' ? 'kurikulum' : 'guru');
            modal.show();
        }).fail(function() { alert('Gagal mengambil data'); });
    }

    function confirmDelete(id, nama) {
        const hapusAkun = confirm(
            'Hapus profil guru "' + nama + '"?\n\n' +
            'OK = hapus profil guru saja (akun login tetap ada)\n' +
            'Cancel = batalkan'
        );
        if (!hapusAkun) return;

        const hapusUser = confirm('Hapus juga akun login user ini?');
        $('#delete_user').val(hapusUser ? '1' : '0');
        $('#deleteForm').attr('action', '<?= base_url('kurikulum/guru/') ?>' + id).submit();
    }
</script>
<?= $this->endSection() ?>
