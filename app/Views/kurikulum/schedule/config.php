<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-12 d-flex align-items-center">
        <a href="<?= base_url('kurikulum/schedule') ?>" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <div>
            <h4 class="fw-bold mb-0">Konfigurasi Algoritma Penjadwalan</h4>
            <p class="text-muted mb-0">Parameter CSP + GA dan bobot soft constraint (v3.0)</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= esc(session()->getFlashdata('success')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="<?= base_url('kurikulum/schedule/config') ?>" method="post">
                    <?= csrf_field() ?>

                    <h6 class="fw-bold text-primary mb-3">Parameter CSP (Fase 1 — Solusi Awal Valid)</h6>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Metode Konsistensi</label>
                            <select name="csp_consistency_method" class="form-select">
                                <?php $cm = $config['csp_consistency_method'] ?? 'AC-3'; ?>
                                <option value="AC-3" <?= $cm === 'AC-3' ? 'selected' : '' ?>>AC-3</option>
                                <option value="none" <?= $cm === 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Variable Ordering</label>
                            <select name="csp_variable_ordering" class="form-select">
                                <?php $vo = $config['csp_variable_ordering'] ?? 'MRV'; ?>
                                <option value="MRV" <?= $vo === 'MRV' ? 'selected' : '' ?>>MRV</option>
                                <option value="degree" <?= $vo === 'degree' ? 'selected' : '' ?>>Degree</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Value Ordering</label>
                            <select name="csp_value_ordering" class="form-select">
                                <?php $vv = $config['csp_value_ordering'] ?? 'LCV'; ?>
                                <option value="LCV" <?= $vv === 'LCV' ? 'selected' : '' ?>>LCV</option>
                                <option value="none" <?= $vv === 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Repair Strategy</label>
                            <select name="csp_repair_strategy" class="form-select">
                                <?php $rs = $config['csp_repair_strategy'] ?? 'min_conflict'; ?>
                                <option value="min_conflict" <?= $rs === 'min_conflict' ? 'selected' : '' ?>>Min-Conflict</option>
                                <option value="none" <?= $rs === 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">CSP Max Attempts</label>
                            <input type="number" class="form-control" name="csp_max_attempts" value="<?= esc($config['csp_max_attempts'] ?? 8) ?>" min="1" max="100" required>
                            <div class="form-text">Retry dengan urutan variabel berbeda jika ada unit gagal.</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold text-primary mb-3">Parameter Genetic Algorithm (Fase 2 — Optimasi)</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Population Size</label>
                            <input type="number" class="form-control" name="population_size" value="<?= esc($config['population_size'] ?? 100) ?>" min="10" max="300" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Max Generations</label>
                            <input type="number" class="form-control" name="max_generations" value="<?= esc($config['max_generations'] ?? 500) ?>" min="50" max="2000" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Tournament Size (k)</label>
                            <input type="number" class="form-control" name="tournament_size" value="<?= esc($config['tournament_size'] ?? 5) ?>" min="2" max="10" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Crossover Rate (0-1)</label>
                            <input type="number" step="0.01" class="form-control" name="crossover_rate" value="<?= esc($config['crossover_rate'] ?? 0.8) ?>" min="0.1" max="1.0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Crossover Method</label>
                            <select name="crossover_method" class="form-select">
                                <?php $cx = $config['crossover_method'] ?? 'order_crossover'; ?>
                                <option value="order_crossover" <?= $cx === 'order_crossover' ? 'selected' : '' ?>>Order Crossover (OX)</option>
                                <option value="uniform" <?= $cx === 'uniform' ? 'selected' : '' ?>>Uniform per-slot</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Mutation Rate (0-1)</label>
                            <input type="number" step="0.01" class="form-control" name="mutation_rate" value="<?= esc($config['mutation_rate'] ?? 0.08) ?>" min="0.01" max="0.5" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Mutation Method</label>
                            <select name="mutation_method" class="form-select">
                                <?php $mm = $config['mutation_method'] ?? 'swap_with_repair'; ?>
                                <option value="swap_with_repair" <?= $mm === 'swap_with_repair' ? 'selected' : '' ?>>Swap + Repair</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Elitism Ratio (0-0.5)</label>
                            <input type="number" step="0.01" class="form-control" name="elitism_ratio" value="<?= esc($config['elitism_ratio'] ?? 0.1) ?>" min="0" max="0.5" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Fitness Threshold (0-1)</label>
                            <input type="number" step="0.01" class="form-control" name="fitness_threshold" value="<?= esc($config['fitness_threshold'] ?? 0.95) ?>" min="0.5" max="1.0" required>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Stagnation Limit</label>
                            <input type="number" class="form-control" name="stagnation_limit" value="<?= esc($config['stagnation_limit'] ?? 40) ?>" min="5" max="300" required>
                            <div class="form-text">Berhenti jika fitness tidak membaik N generasi.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Adaptive Mutation</label>
                            <select name="adaptive_mutation" class="form-select">
                                <option value="1" <?= (int) ($config['adaptive_mutation'] ?? 1) === 1 ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= (int) ($config['adaptive_mutation'] ?? 1) === 0 ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Adaptive Trigger (gen)</label>
                            <input type="number" class="form-control" name="adaptive_mutation_trigger" value="<?= esc($config['adaptive_mutation_trigger'] ?? 20) ?>" min="1" max="200" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Adaptive Increment</label>
                            <input type="number" step="0.01" class="form-control" name="adaptive_mutation_increment" value="<?= esc($config['adaptive_mutation_increment'] ?? 0.02) ?>" min="0" max="0.2" required>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold text-primary mb-3">Bobot Soft Constraint (skala 1–10)</h6>
                    <p class="small text-muted">HC-1–HC-8 tetap hard constraint (tidak dikonfigurasi). Bobot di bawah menentukan prioritas kualitas jadwal di GA. Semakin tinggi = semakin diprioritaskan.</p>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-1 — Minim gap guru</label>
                            <input type="number" step="0.5" class="form-control" name="sc1_teacher_gap" value="<?= esc($config['sc1_teacher_gap'] ?? 9) ?>" min="0" max="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-2 — Minim gap siswa</label>
                            <input type="number" step="0.5" class="form-control" name="sc2_student_gap" value="<?= esc($config['sc2_student_gap'] ?? 9) ?>" min="0" max="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-3 — Distribusi mapel/minggu</label>
                            <input type="number" step="0.5" class="form-control" name="sc3_subject_distribution" value="<?= esc($config['sc3_subject_distribution'] ?? 7) ?>" min="0" max="10" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-4 — Mapel berat di pagi</label>
                            <input type="number" step="0.5" class="form-control" name="sc4_heavy_morning" value="<?= esc($config['sc4_heavy_morning'] ?? 6) ?>" min="0" max="10" required>
                            <div class="form-text">Pakai mapel.bobot_kognitif.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-5 — Mapel ringan di sore</label>
                            <input type="number" step="0.5" class="form-control" name="sc5_light_afternoon" value="<?= esc($config['sc5_light_afternoon'] ?? 5) ?>" min="0" max="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-6 — Beban guru seimbang/hari</label>
                            <input type="number" step="0.5" class="form-control" name="sc6_teacher_load_balance" value="<?= esc($config['sc6_teacher_load_balance'] ?? 7) ?>" min="0" max="10" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-7 — Preferensi hari/jam guru</label>
                            <input type="number" step="0.5" class="form-control" name="sc7_teacher_preference" value="<?= esc($config['sc7_teacher_preference'] ?? 5) ?>" min="0" max="10" required>
                            <div class="form-text">Data dari menu Preferensi Jadwal (role Guru).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-8 — Minim perpindahan ruang</label>
                            <input type="number" step="0.5" class="form-control" name="sc8_room_transition" value="<?= esc($config['sc8_room_transition'] ?? 5) ?>" min="0" max="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-9 — Kontinuitas guru/kelas</label>
                            <input type="number" step="0.5" class="form-control" name="sc9_teacher_continuity" value="<?= esc($config['sc9_teacher_continuity'] ?? 4) ?>" min="0" max="10" required>
                            <div class="form-text">Satu guru per pasangan kelas–mapel.</div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-10 — Rotasi mapel jam pertama</label>
                            <input type="number" step="0.5" class="form-control" name="sc10_first_slot_rotation" value="<?= esc($config['sc10_first_slot_rotation'] ?? 3) ?>" min="0" max="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">SC-11 — Load balancing lab antar jurusan</label>
                            <input type="number" step="0.5" class="form-control" name="sc11_lab_load_balance" value="<?= esc($config['sc11_lab_load_balance'] ?? 6) ?>" min="0" max="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Lab preferensi (HC-7 pelengkap)</label>
                            <input type="number" step="0.5" class="form-control" name="sc_lab_preference" value="<?= esc($config['sc_lab_preference'] ?? 5) ?>" min="0" max="10" required>
                            <div class="form-text">Penalti jika penempatan tidak di lab utama kelas_mapel.</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold text-danger mb-3"><i class="bi bi-cpu"></i> System Limits</h6>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Timeout Algoritma (Detik)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="timeout_seconds" value="<?= esc($config['timeout_seconds'] ?? 300) ?>" min="30" max="3600" required>
                                <span class="input-group-text">Detik</span>
                            </div>
                            <div class="form-text">Batas total waktu CSP + GA.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i> Simpan Konfigurasi</button>
                    </div>
                </form>
            </div>
        </div>

        <?= view('kurikulum/schedule/_config_param_guide') ?>

    </div>
</div>
<?= $this->endSection() ?>
