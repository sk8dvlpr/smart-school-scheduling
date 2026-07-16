<?php
/**
 * Shared preferensi form (day cards).
 *
 * Expected vars:
 * - $hari: list of hari rows
 * - $timeslotsByHari: [hari_id => list of timeslot]
 * - $formState: from GuruPreferensiModel::toFormState()
 * - $formAction: POST URL
 * - $backUrl: optional cancel/back URL
 * - $subtitle: optional helper text
 */
$subtitle = $subtitle ?? 'Pilih Suka atau Hindari per hari. Detail jam JP opsional. Digunakan GA sebagai SC-7 (bukan larangan keras).';
$backUrl  = $backUrl ?? null;
?>
<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<p class="text-muted small mb-3"><?= esc($subtitle) ?></p>

<form action="<?= esc($formAction) ?>" method="post" id="preferensiForm">
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php foreach ($hari as $h):
            $hid   = (int) $h['id'];
            $st    = $formState[$hid] ?? ['mode' => 'none', 'bobot' => 5, 'slots' => []];
            $mode  = $st['mode'] ?? 'none';
            $bobot = (int) ($st['bobot'] ?? 5);
            $slots = $st['slots'] ?? [];
            $jpSlots = array_values(array_filter(
                $timeslotsByHari[$hid] ?? [],
                static fn ($ts) => ($ts['tipe'] ?? '') === 'jp'
            ));
            $hasSlotPref = $slots !== [];
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 pref-day-card" data-hari="<?= $hid ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0"><?= esc($h['nama']) ?></h6>
                            <span class="badge bg-light text-muted pref-badge">
                                <?= $mode === 'prefer' ? 'Suka' : ($mode === 'avoid' ? 'Hindari' : 'Netral') ?>
                            </span>
                        </div>

                        <div class="btn-group w-100 mb-2" role="group" aria-label="Mode hari">
                            <input type="radio" class="btn-check pref-mode" name="day[<?= $hid ?>][mode]" id="mode_<?= $hid ?>_none" value="none" autocomplete="off" <?= $mode === 'none' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary btn-sm" for="mode_<?= $hid ?>_none">Netral</label>

                            <input type="radio" class="btn-check pref-mode" name="day[<?= $hid ?>][mode]" id="mode_<?= $hid ?>_prefer" value="prefer" autocomplete="off" <?= $mode === 'prefer' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-success btn-sm" for="mode_<?= $hid ?>_prefer">Suka</label>

                            <input type="radio" class="btn-check pref-mode" name="day[<?= $hid ?>][mode]" id="mode_<?= $hid ?>_avoid" value="avoid" autocomplete="off" <?= $mode === 'avoid' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-danger btn-sm" for="mode_<?= $hid ?>_avoid">Hindari</label>
                        </div>

                        <div class="pref-bobot-wrap mb-2 <?= $mode === 'none' && ! $hasSlotPref ? 'd-none' : '' ?>">
                            <label class="form-label small mb-1">Bobot (1–10)</label>
                            <input type="range" class="form-range pref-bobot" name="day[<?= $hid ?>][bobot]" min="1" max="10" value="<?= $bobot ?>"
                                   oninput="this.nextElementSibling.textContent = this.value">
                            <div class="small text-muted text-end pref-bobot-val"><?= $bobot ?></div>
                        </div>

                        <?php if ($jpSlots !== []): ?>
                            <button class="btn btn-link btn-sm px-0 text-decoration-none" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#slots_<?= $hid ?>"
                                    aria-expanded="<?= $hasSlotPref ? 'true' : 'false' ?>">
                                <i class="bi bi-clock"></i> Detail jam JP (opsional)
                            </button>
                            <div class="collapse <?= $hasSlotPref ? 'show' : '' ?>" id="slots_<?= $hid ?>">
                                <div class="border rounded p-2 bg-light">
                                    <div class="small text-muted mb-2">Kosongkan = ikut preferensi hari. Atur slot tertentu jika perlu.</div>
                                    <?php foreach ($jpSlots as $ts):
                                        $tid = (int) $ts['id'];
                                        $sm  = $slots[$tid] ?? 'none';
                                        ?>
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                            <span class="small fw-medium">JP <?= (int) $ts['jam_ke'] ?></span>
                                            <select name="day[<?= $hid ?>][slot][<?= $tid ?>]" class="form-select form-select-sm pref-slot" style="max-width: 120px;">
                                                <option value="none" <?= $sm === 'none' ? 'selected' : '' ?>>—</option>
                                                <option value="prefer" <?= $sm === 'prefer' ? 'selected' : '' ?>>Suka</option>
                                                <option value="avoid" <?= $sm === 'avoid' ? 'selected' : '' ?>>Hindari</option>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Preferensi</button>
        <?php if ($backUrl): ?>
            <a href="<?= esc($backUrl) ?>" class="btn btn-outline-secondary">Kembali</a>
        <?php endif; ?>
    </div>
</form>
