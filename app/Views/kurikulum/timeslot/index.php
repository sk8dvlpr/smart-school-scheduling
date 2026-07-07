<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="mb-0 fw-bold">Data Timeslot per Hari</h5>
            <?php if ($active_hari_id): ?>
                <small class="text-muted"><span class="badge bg-primary"><?= $jp_count ?> JP</span> pada hari ini</small>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal()">
            <i class="bi bi-plus-lg"></i> Tambah Timeslot
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

        <ul class="nav nav-tabs mb-3">
            <?php foreach ($hari as $h): ?>
            <li class="nav-item">
                <a class="nav-link <?= (int) $h['id'] === $active_hari_id ? 'active' : '' ?>"
                   href="<?= base_url('kurikulum/timeslot?hari_id=' . $h['id']) ?>">
                    <?= esc($h['nama']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="table-responsive">
            <table id="dataTable" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>Jam Ke</th>
                        <th>Waktu</th>
                        <th>Tipe</th>
                        <th>Keterangan</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($timeslot as $row): ?>
                    <tr class="<?= in_array($row['tipe'], ['istirahat', 'kegiatan_khusus'], true) ? 'table-warning' : '' ?>">
                        <td><?= $no++ ?></td>
                        <td class="fw-bold text-center"><?= $row['jam_ke'] == 0 ? '-' : esc($row['jam_ke']) ?></td>
                        <td><?= date('H:i', strtotime($row['waktu_mulai'])) ?> — <?= date('H:i', strtotime($row['waktu_selesai'])) ?></td>
                        <td>
                            <?php if ($row['tipe'] === 'istirahat'): ?>
                                <span class="badge bg-warning text-dark">Istirahat</span>
                            <?php elseif ($row['tipe'] === 'kegiatan_khusus'): ?>
                                <span class="badge bg-secondary">Kegiatan Khusus</span>
                            <?php else: ?>
                                <span class="badge bg-primary">JP</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($row['keterangan'] ?? '-') ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info text-white" onclick="editData(<?= $row['id'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="<?= base_url('kurikulum/timeslot/' . $row['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus permanen timeslot ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="dataForm" action="<?= base_url('kurikulum/timeslot') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" name="hari_id" value="<?= $active_hari_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Timeslot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipe</label>
                        <select class="form-select" name="tipe" id="tipe" onchange="toggleTipe()" required>
                            <option value="jp">Jam Pelajaran (JP)</option>
                            <option value="istirahat">Istirahat</option>
                            <option value="kegiatan_khusus">Kegiatan Khusus</option>
                        </select>
                    </div>
                    <div class="mb-3" id="jamKeWrapper">
                        <label class="form-label">Jam Pelajaran Ke-</label>
                        <input type="number" class="form-control" name="jam_ke" id="jam_ke" min="1" value="1">
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Waktu Mulai</label>
                            <input type="time" class="form-control" name="waktu_mulai" id="waktu_mulai" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Waktu Selesai</label>
                            <input type="time" class="form-control" name="waktu_selesai" id="waktu_selesai" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <input type="text" class="form-control" name="keterangan" id="keterangan" placeholder="Opsional">
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
            responsive: true,
            ordering: false
        });
    });

    const modal = new bootstrap.Modal(document.getElementById('formModal'));

    function toggleTipe() {
        const tipe = $('#tipe').val();
        const nonJp = (tipe === 'istirahat' || tipe === 'kegiatan_khusus');
        if (nonJp) {
            $('#jamKeWrapper').hide();
            $('#jam_ke').val(0);
        } else {
            $('#jamKeWrapper').show();
            if ($('#jam_ke').val() == 0) $('#jam_ke').val(1);
        }
    }

    function openModal() {
        $('#formMethod').val('POST');
        $('#dataForm').attr('action', '<?= base_url('kurikulum/timeslot') ?>');
        $('#modalTitle').text('Tambah Timeslot');
        $('#dataForm')[0].reset();
        $('input[name="hari_id"]').val(<?= $active_hari_id ?>);
        toggleTipe();
        modal.show();
    }

    function editData(id) {
        $.get('<?= base_url('kurikulum/timeslot/') ?>' + id, function(data) {
            $('#formMethod').val('PUT');
            $('#dataForm').attr('action', '<?= base_url('kurikulum/timeslot/') ?>' + id);
            $('#modalTitle').text('Edit Timeslot');
            $('#tipe').val(data.tipe);
            $('#waktu_mulai').val(data.waktu_mulai);
            $('#waktu_selesai').val(data.waktu_selesai);
            $('#keterangan').val(data.keterangan);
            toggleTipe();
            if (data.tipe === 'jp') $('#jam_ke').val(data.jam_ke);
            modal.show();
        }).fail(function() { alert('Gagal mengambil data'); });
    }
</script>
<?= $this->endSection() ?>
