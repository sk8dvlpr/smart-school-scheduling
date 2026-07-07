<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0">Hasil Penjadwalan</h4>
            <p class="text-muted mb-0">Jadwal untuk tahun ajaran aktif.</p>
        </div>
        <div class="d-flex gap-2">
            <?php
                $exportAllSuffix = !empty($schedule_log_id) ? '?schedule_log_id=' . (int) $schedule_log_id : '';
            ?>
            <a href="<?= base_url('kurikulum/schedule/export/pdf-all' . $exportAllSuffix) ?>" class="btn btn-outline-danger" target="_blank">
                <i class="bi bi-file-pdf"></i> Export Semua Kelas (PDF)
            </a>
            <a href="<?= base_url('kurikulum/schedule/export/excel-all' . $exportAllSuffix) ?>" class="btn btn-outline-success">
                <i class="bi bi-file-excel"></i> Export Semua Kelas (Excel)
            </a>
            <a href="<?= base_url('kurikulum/schedule') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <ul class="nav nav-tabs mb-4" id="jadwalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="kelas-tab" data-bs-toggle="tab" data-bs-target="#kelas-pane" type="button" role="tab">Berdasarkan Kelas</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="guru-tab" data-bs-toggle="tab" data-bs-target="#guru-pane" type="button" role="tab">Berdasarkan Guru</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="ruang-tab" data-bs-toggle="tab" data-bs-target="#ruang-pane" type="button" role="tab">Berdasarkan Ruangan</button>
            </li>
        </ul>
        
        <div class="tab-content" id="jadwalTabContent">
            <!-- Kelas Tab -->
            <div class="tab-pane fade show active" id="kelas-pane" role="tabpanel" tabindex="0">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Kelas</label>
                        <select class="form-select" id="selectKelas" onchange="loadJadwal('kelas', this.value)">
                            <option value="">-- Silakan Pilih --</option>
                            <?php foreach ($kelas as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= esc($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <button class="btn btn-danger me-2" onclick="exportData('pdf')"><i class="bi bi-file-pdf"></i> Export PDF</button>
                        <button class="btn btn-success" onclick="exportData('excel')"><i class="bi bi-file-excel"></i> Export Excel</button>
                    </div>
                </div>
                <div id="swapModeBanner" class="alert alert-warning border-0 py-2 mb-3 small d-none" role="status">
                    <i class="bi bi-arrow-left-right me-1"></i>
                    <strong>Mode tukar jadwal</strong> — pilih sel jadwal kedua.
                    <span id="swapSourceHint" class="ms-1"></span>
                    <button type="button" class="btn btn-link btn-sm p-0 ms-2 align-baseline" id="btnCancelSwap">Batal</button>
                </div>
                <div id="jadwalContainerKelas">
                    <div class="text-center p-5 text-muted bg-light rounded">
                        <i class="bi bi-calendar-event fs-1 mb-3 d-block"></i>
                        Silakan pilih kelas terlebih dahulu untuk melihat jadwal.
                    </div>
                </div>
            </div>
            
            <!-- Guru Tab -->
            <div class="tab-pane fade" id="guru-pane" role="tabpanel" tabindex="0">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Guru</label>
                        <select class="form-select" id="selectGuru" onchange="loadJadwal('guru', this.value)">
                            <option value="">-- Silakan Pilih --</option>
                            <?php foreach ($guru as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= esc($g['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <button class="btn btn-danger me-2" onclick="exportData('pdf')"><i class="bi bi-file-pdf"></i> Export PDF</button>
                        <button class="btn btn-success" onclick="exportData('excel')"><i class="bi bi-file-excel"></i> Export Excel</button>
                    </div>
                </div>
                <div id="jadwalContainerGuru">
                    <div class="text-center p-5 text-muted bg-light rounded">
                        <i class="bi bi-person-video3 fs-1 mb-3 d-block"></i>
                        Silakan pilih guru terlebih dahulu.
                    </div>
                </div>
            </div>
            
            <!-- Ruangan Tab -->
            <div class="tab-pane fade" id="ruang-pane" role="tabpanel" tabindex="0">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Ruangan</label>
                        <select class="form-select" id="selectRuangan" onchange="loadJadwal('ruangan', this.value)">
                            <option value="">-- Silakan Pilih --</option>
                            <?php foreach ($ruangan as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= esc($r['nama']) ?> (<?= ucfirst($r['tipe']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <button class="btn btn-danger me-2" onclick="exportData('pdf')"><i class="bi bi-file-pdf"></i> Export PDF</button>
                        <button class="btn btn-success" onclick="exportData('excel')"><i class="bi bi-file-excel"></i> Export Excel</button>
                    </div>
                </div>
                <div id="jadwalContainerRuangan">
                    <div class="text-center p-5 text-muted bg-light rounded">
                        <i class="bi bi-door-open fs-1 mb-3 d-block"></i>
                        Silakan pilih ruangan terlebih dahulu.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal penempatan manual -->
<div class="modal fade" id="modalManualPlace" tabindex="-1" aria-labelledby="modalManualPlaceLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalManualPlaceLabel">Tambah Jadwal Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="manualSlotLabel">—</p>
                <div class="mb-3">
                    <label class="form-label fw-medium" for="manualMapelSelect">Mata Pelajaran</label>
                    <select class="form-select" id="manualMapelSelect" required>
                        <option value="">— Pilih mapel —</option>
                    </select>
                    <div class="form-text">Hanya mapel kurikulum kelas dengan sisa JP &gt; 0.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium" for="manualGuruSelect">Guru</label>
                    <select class="form-select" id="manualGuruSelect" required disabled>
                        <option value="">— Pilih mapel dulu —</option>
                    </select>
                </div>
                <div id="manualPlaceError" class="alert alert-danger d-none small mb-0"></div>
                <div id="manualBlockedMapel" class="d-none mt-3">
                    <p class="small fw-medium text-muted mb-2">Mapel tidak bisa dipilih di slot ini:</p>
                    <ul class="list-group list-group-flush small" id="manualBlockedList"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnManualPlaceSubmit">
                    <i class="bi bi-check-lg me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal swap jadwal manual -->
<div class="modal fade" id="modalManualSwap" tabindex="-1" aria-labelledby="modalManualSwapLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalManualSwapLabel">Tukar Jadwal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Pilih jenis pertukaran antara dua sel terisi:</p>
                <ul class="list-group list-group-flush small mb-3">
                    <li class="list-group-item px-0 py-2">
                        <span class="text-muted">Sel A:</span>
                        <span class="fw-medium" id="swapSourceLabel">—</span>
                    </li>
                    <li class="list-group-item px-0 py-2 border-0">
                        <span class="text-muted">Sel B:</span>
                        <span class="fw-medium" id="swapTargetLabel">—</span>
                    </li>
                </ul>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary text-start" id="btnSwapSlots">
                        <i class="bi bi-clock me-2"></i>
                        <span class="fw-medium">Tukar slot</span>
                        <span class="d-block small text-muted ms-4">Pindahkan posisi hari/jam kedua mapel</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary text-start" id="btnSwapMapel">
                        <i class="bi bi-journal-text me-2"></i>
                        <span class="fw-medium">Tukar mapel</span>
                        <span class="d-block small text-muted ms-4">Tukar isi mapel, guru, dan ruangan di slot masing-masing</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary text-start" id="btnSwapGuru">
                        <i class="bi bi-person me-2"></i>
                        <span class="fw-medium">Tukar guru</span>
                        <span class="d-block small text-muted ms-4">Hanya guru yang ditukar; mapel dan slot tetap</span>
                    </button>
                </div>
                <div id="swapError" class="alert alert-danger d-none small mb-0 mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const scheduleLogId = <?= json_encode($schedule_log_id) ?>;

    function loadJadwal(type, id) {
        if (!id) return;
        
        let containerId = '#jadwalContainer' + type.charAt(0).toUpperCase() + type.slice(1);
        
        // Placeholder for loading state
        $(containerId).html(`
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Memuat jadwal...</p>
            </div>
        `);
        
        const params = scheduleLogId ? { schedule_log_id: scheduleLogId } : {};

        // AJAX request to get the view HTML
        $.get(`<?= base_url('kurikulum/schedule/view/') ?>${type}/${id}`, params, function(data) {
            if (data.trim() === '') {
                $(containerId).html(`
                    <div class="alert alert-danger border-0 rounded bg-danger-subtle text-center py-4">
                        <i class="bi bi-x-circle-fill fs-2 mb-2 text-danger d-block"></i>
                        Gagal memuat jadwal. Pastikan Tahun Ajaran Aktif tersedia.
                    </div>
                `);
            } else {
                $(containerId).html(data);
            }
        }).fail(function() {
            $(containerId).html(`
                <div class="alert alert-danger">Terjadi kesalahan koneksi saat memuat jadwal.</div>
            `);
        });
    }
    
    function exportData(format) {
        // Find which tab is active and get selected ID
        let type = '';
        let id = '';
        
        if ($('#kelas-pane').hasClass('active')) {
            type = 'kelas';
            id = $('#selectKelas').val();
        } else if ($('#guru-pane').hasClass('active')) {
            type = 'guru';
            id = $('#selectGuru').val();
        } else if ($('#ruang-pane').hasClass('active')) {
            type = 'ruangan';
            id = $('#selectRuangan').val();
        }
        
        if (!id) {
            alert('Silakan pilih data terlebih dahulu sebelum melakukan ekspor.');
            return;
        }
        
        let exportUrl = `<?= base_url('kurikulum/schedule/export/') ?>${format}-${type}-${id}`;
        if (scheduleLogId) {
            exportUrl += '?schedule_log_id=' + encodeURIComponent(scheduleLogId);
        }

        // Open in new tab/window for download
        window.open(exportUrl, '_blank');
    }

    const manualPlaceState = {
        kelasId: null,
        hariId: null,
        timeslotId: null,
        mapelOptions: [],
    };
    const manualModal = new bootstrap.Modal(document.getElementById('modalManualPlace'));
    const swapModal = new bootstrap.Modal(document.getElementById('modalManualSwap'));
    const swapState = {
        sourceId: null,
        sourceKelasId: null,
        sourceLabel: '',
        targetId: null,
        targetKelasId: null,
        targetLabel: '',
    };

    function clearSwapMode() {
        swapState.sourceId = null;
        swapState.sourceKelasId = null;
        swapState.sourceLabel = '';
        swapState.targetId = null;
        swapState.targetKelasId = null;
        swapState.targetLabel = '';
        $('#swapModeBanner').addClass('d-none');
        $('#swapSourceHint').text('');
        $('.tt-subject').removeClass('tt-swap-pending tt-swap-hover tt-swap-mode-active');
    }

    function cellSwapLabel($el) {
        const mapel = $el.attr('data-mapel-nama') || $el.find('.tt-subject-name').first().text().trim();
        const guru = $el.attr('data-guru-nama') || $el.find('.tt-subject-sub').first().text().trim();
        return guru ? `${mapel} (${guru})` : mapel;
    }

    function beginSwapPick($el) {
        const jadwalId = $el.attr('data-jadwal-id');
        const kelasId = $el.attr('data-kelas-id');
        if (!jadwalId || !kelasId) {
            return;
        }

        clearSwapMode();
        swapState.sourceId = jadwalId;
        swapState.sourceKelasId = kelasId;
        swapState.sourceLabel = cellSwapLabel($el);

        const $subject = $el.hasClass('tt-subject') ? $el : $el.closest('.tt-subject');
        $subject.addClass('tt-swap-pending');
        $('.tt-subject--swappable').addClass('tt-swap-mode-active');
        $('#swapSourceHint').text(`Dari: ${swapState.sourceLabel}`);
        $('#swapModeBanner').removeClass('d-none');
    }

    function openSwapModal($targetEl) {
        const targetId = $targetEl.attr('data-jadwal-id');
        const targetKelasId = $targetEl.attr('data-kelas-id');
        if (!swapState.sourceId || !targetId) {
            return;
        }
        if (String(targetId) === String(swapState.sourceId)) {
            clearSwapMode();
            return;
        }
        if (String(targetKelasId) !== String(swapState.sourceKelasId)) {
            alert('Kedua sel harus dari kelas yang sama.');
            return;
        }

        swapState.targetId = targetId;
        swapState.targetKelasId = targetKelasId;
        swapState.targetLabel = cellSwapLabel($targetEl);

        $('#swapSourceLabel').text(swapState.sourceLabel);
        $('#swapTargetLabel').text(swapState.targetLabel);
        $('#swapError').addClass('d-none').text('');
        $('#btnSwapSlots, #btnSwapMapel, #btnSwapGuru').prop('disabled', false);
        swapModal.show();
    }

    function performSwap(endpoint) {
        if (!swapState.sourceId || !swapState.targetId || !swapState.sourceKelasId) {
            return;
        }
        $('#swapError').addClass('d-none').text('');
        $('#btnSwapSlots, #btnSwapMapel, #btnSwapGuru').prop('disabled', true);

        $.ajax({
            url: endpoint,
            method: 'POST',
            dataType: 'json',
            data: {
                jadwal_id_a: swapState.sourceId,
                jadwal_id_b: swapState.targetId,
                kelas_id: swapState.sourceKelasId,
            },
            success: function(res) {
                if (res.success) {
                    swapModal.hide();
                    clearSwapMode();
                    reloadKelasJadwal();
                } else {
                    $('#swapError').removeClass('d-none').text(res.message || 'Gagal menukar jadwal.');
                    $('#btnSwapSlots, #btnSwapMapel, #btnSwapGuru').prop('disabled', false);
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan saat menukar jadwal.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#swapError').removeClass('d-none').text(msg);
                $('#btnSwapSlots, #btnSwapMapel, #btnSwapGuru').prop('disabled', false);
            },
        });
    }

    function reloadKelasJadwal() {
        clearSwapMode();
        const id = $('#selectKelas').val();
        if (id) loadJadwal('kelas', id);
    }

    function fillGuruSelect(mapelOption) {
        const $guru = $('#manualGuruSelect');
        $guru.empty();
        if (!mapelOption || !mapelOption.gurus || mapelOption.gurus.length === 0) {
            $guru.append('<option value="">— Tidak ada guru eligible —</option>');
            $guru.prop('disabled', true);
            return;
        }
        $guru.append('<option value="">— Pilih guru —</option>');
        mapelOption.gurus.forEach(g => {
            const cap = g.remaining_cap !== undefined ? ` (sisa ${g.remaining_cap} JP)` : '';
            $guru.append(`<option value="${g.guru_id}">${g.nama}${cap}</option>`);
        });
        $guru.prop('disabled', false);
    }

    function hcBadgeClass(code) {
        const map = {
            'HC-1': 'bg-warning text-dark',
            'HC-3': 'bg-danger',
            'HC-5': 'bg-secondary',
            'HC-6': 'bg-secondary',
            'HC-8': 'bg-info text-dark',
        };
        return map[code] || 'bg-secondary';
    }

    function renderBlockedMapel(blocked) {
        const $wrap = $('#manualBlockedMapel');
        const $list = $('#manualBlockedList');
        $list.empty();
        if (!blocked || blocked.length === 0) {
            $wrap.addClass('d-none');
            return;
        }
        blocked.forEach(m => {
            const codes = (m.codes || []).map(c =>
                `<span class="badge ${hcBadgeClass(c)} me-1">${c}</span>`
            ).join('');
            const jp = `${m.scheduled}/${m.demand} JP (sisa ${m.remaining})`;
            $list.append(
                `<li class="list-group-item px-0 py-2 border-0 border-bottom">` +
                `<div class="d-flex justify-content-between align-items-start gap-2">` +
                `<div><span class="fw-medium">${m.mapel_nama}</span> <span class="text-muted">${jp}</span></div>` +
                `<div class="text-nowrap">${codes}</div></div>` +
                `<div class="text-muted mt-1">${m.message || ''}</div></li>`
            );
        });
        $wrap.removeClass('d-none');
    }

    $(document).on('click keypress', '.tt-empty-cell--editable', function(e) {
        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
        e.preventDefault();

        const $el = $(this);
        manualPlaceState.kelasId = $el.attr('data-kelas-id');
        manualPlaceState.hariId = $el.attr('data-hari-id');
        manualPlaceState.timeslotId = $el.attr('data-timeslot-id');
        manualPlaceState.mapelOptions = [];

        $('#manualPlaceError').addClass('d-none').text('').removeClass('alert-warning').addClass('alert-danger');
        $('#manualBlockedMapel').addClass('d-none');
        $('#manualBlockedList').empty();
        $('#manualMapelSelect').html('<option value="">Memuat...</option>');
        $('#manualGuruSelect').html('<option value="">— Pilih mapel dulu —</option>').prop('disabled', true);
        $('#btnManualPlaceSubmit').prop('disabled', false);
        $('#manualSlotLabel').text('Memuat opsi...');
        manualModal.show();

        $.get('<?= base_url('kurikulum/schedule/manual/options') ?>', {
            kelas_id: manualPlaceState.kelasId,
            hari_id: manualPlaceState.hariId,
            timeslot_id: manualPlaceState.timeslotId,
        }, function(res) {
            if (!res.success) {
                $('#manualPlaceError').removeClass('d-none').text(res.message || 'Gagal memuat opsi.');
                $('#manualMapelSelect').html('<option value="">— Tidak tersedia —</option>');
                $('#manualSlotLabel').text('Slot tidak tersedia');
                renderBlockedMapel([]);
                $('#btnManualPlaceSubmit').prop('disabled', true);
                return;
            }
            manualPlaceState.mapelOptions = res.mapel_options || [];
            $('#manualSlotLabel').text(res.slot_label || 'Slot terpilih');
            renderBlockedMapel(res.blocked_mapel || []);

            const $mapel = $('#manualMapelSelect');
            $mapel.empty();
            if (manualPlaceState.mapelOptions.length === 0) {
                $mapel.append('<option value="">— Tidak ada mapel yang bisa dipilih —</option>');
                $('#btnManualPlaceSubmit').prop('disabled', true);
                if ((res.blocked_mapel || []).length === 0 && res.message) {
                    $('#manualPlaceError').removeClass('d-none').text(res.message);
                }
            } else {
                $mapel.append('<option value="">— Pilih mapel —</option>');
                manualPlaceState.mapelOptions.forEach(m => {
                    $mapel.append(
                        `<option value="${m.kelas_mapel_id}">${m.mapel_nama} — ${m.scheduled}/${m.demand} JP</option>`
                    );
                });
                $('#btnManualPlaceSubmit').prop('disabled', false);
            }

            if (res.slot_codes && res.slot_codes.length > 0 && res.message) {
                $('#manualPlaceError').removeClass('d-none').addClass('alert-warning').removeClass('alert-danger')
                    .text(res.message);
            }
        }).fail(function() {
            $('#manualPlaceError').removeClass('d-none').text('Koneksi gagal saat memuat opsi penempatan.');
            renderBlockedMapel([]);
            $('#btnManualPlaceSubmit').prop('disabled', true);
        });
    });

    $('#manualMapelSelect').on('change', function() {
        const kmId = parseInt($(this).val(), 10);
        const opt = manualPlaceState.mapelOptions.find(m => m.kelas_mapel_id === kmId);
        fillGuruSelect(opt);
    });

    $('#btnManualPlaceSubmit').on('click', function() {
        const kelasMapelId = parseInt($('#manualMapelSelect').val(), 10);
        const guruId = parseInt($('#manualGuruSelect').val(), 10);
        if (!kelasMapelId || !guruId) {
            $('#manualPlaceError').removeClass('d-none').text('Pilih mapel dan guru terlebih dahulu.');
            return;
        }
        const $btn = $(this).prop('disabled', true);
        $('#manualPlaceError').addClass('d-none');

        $.ajax({
            url: '<?= base_url('kurikulum/schedule/manual/place') ?>',
            method: 'POST',
            dataType: 'json',
            data: {
                kelas_id: manualPlaceState.kelasId,
                hari_id: manualPlaceState.hariId,
                timeslot_id: manualPlaceState.timeslotId,
                kelas_mapel_id: kelasMapelId,
                guru_id: guruId,
            },
            success: function(res) {
                if (res.success) {
                    manualModal.hide();
                    reloadKelasJadwal();
                } else {
                    $('#manualPlaceError').removeClass('d-none').text(res.message || 'Gagal menyimpan.');
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan saat menyimpan jadwal.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#manualPlaceError').removeClass('d-none').text(msg);
            },
            complete: function() {
                $btn.prop('disabled', false);
            },
        });
    });

    $(document).on('click', '.tt-delete-btn', function(e) {
        e.stopPropagation();
        const jadwalId = $(this).attr('data-jadwal-id');
        const kelasId = $(this).attr('data-kelas-id');
        if (!confirm('Hapus jadwal di slot ini? Kuota JP mapel akan dikembalikan.')) {
            return;
        }
        $.ajax({
            url: `<?= base_url('kurikulum/schedule/manual/delete/') ?>${jadwalId}`,
            method: 'POST',
            dataType: 'json',
            data: {
                kelas_id: kelasId,
            },
            success: function(res) {
                if (res.success) {
                    reloadKelasJadwal();
                } else {
                    alert(res.message || 'Gagal menghapus jadwal.');
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan saat menghapus jadwal.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            },
        });
    });

    $(document).on('click', '.tt-swap-pick-btn', function(e) {
        e.stopPropagation();
        beginSwapPick($(this));
    });

    $(document).on('click', '.tt-subject--swappable', function(e) {
        if (!swapState.sourceId) {
            return;
        }
        if ($(e.target).closest('.tt-action-bar').length) {
            return;
        }
        e.stopPropagation();
        openSwapModal($(this));
    });

    $(document).on('mouseenter', '.tt-subject--swappable.tt-swap-mode-active', function() {
        if (!swapState.sourceId) {
            return;
        }
        const id = $(this).attr('data-jadwal-id');
        if (id && String(id) !== String(swapState.sourceId)) {
            $(this).addClass('tt-swap-hover');
        }
    }).on('mouseleave', '.tt-subject--swappable', function() {
        $(this).removeClass('tt-swap-hover');
    });

    $('#btnCancelSwap').on('click', clearSwapMode);

    $('#modalManualSwap').on('hidden.bs.modal', function() {
        if (!swapState.targetId) {
            return;
        }
        swapState.targetId = null;
        swapState.targetLabel = '';
    });

    $('#btnSwapSlots').on('click', function() {
        performSwap('<?= base_url('kurikulum/schedule/manual/swap-slots') ?>');
    });
    $('#btnSwapMapel').on('click', function() {
        performSwap('<?= base_url('kurikulum/schedule/manual/swap-mapel') ?>');
    });
    $('#btnSwapGuru').on('click', function() {
        performSwap('<?= base_url('kurikulum/schedule/manual/swap-guru') ?>');
    });
</script>
<?= $this->endSection() ?>
