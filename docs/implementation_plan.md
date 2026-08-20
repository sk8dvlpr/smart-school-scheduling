# Perbaikan Fitness Score & Performa Scheduling Engine

## Masalah Saat Ini

Dari screenshot: **1008 unit JP**, **fitness 0.0316**, **waktu 3165 detik (~53 menit)**, zero conflict (semua unit placed).

### Analisis Root Cause

**Fitness 0.0316** berarti: `1 / (1 + X) = 0.0316` → **X ≈ 30.65** (total weighted penalty sangat tinggi).

Setelah membaca keseluruhan kode, saya menemukan **5 masalah utama**:

---

#### 🔴 Masalah 1: Penalty Normalisasi Tidak Benar (Penyebab Utama)

Rumus fitness: `1 / (1 + Σ Wi × Penalty_i)` dengan Wi skala 1–10. Penalty seharusnya 0–1, tapi **beberapa penalty tidak ter-normalisasi dengan benar**:

| SC | Normalisasi Saat Ini | Masalah |
|----|---------------------|---------|
| SC-1 | `$sc1 / $count` | Gap count bisa >> 1 per guru-day. Misal guru punya 3 gap/hari × 5 hari = 15 total gaps. Dibagi 1008 unit = 0.015 → OK secara rata-rata, **tapi jika banyak guru gap, angka bisa membludak** |
| SC-3 | `$sc3 / $sc3Groups` | Sudah OK (0–1ish) |
| SC-4 | `$sc4 / $count` | Setiap unit kontribusi `(bobot/10) × lateness`. Rata-rata bobot=5, lateness=0.5 → **rata-rata 0.25 per unit**, normalized = ~0.25. **Dengan weight 6, ini menambah 1.5 ke sum** |
| SC-5 | `$sc5 / $count` | Sama seperti SC-4 tapi **terbalik** — light subject placed early juga penalty. Rata-rata ~0.25. **Dengan weight 5, ini menambah 1.25 ke sum** |
| SC-6 | `$sc6 / $guruCount` | Deviation rata-rata bisa **> 1.0** karena rumus menghitung `dev/total`, bukan capped ke 0–1 |
| SC-8 | `$sc8 / $count` | OK (rendah) |
| SC-10 | `$sc10 / $kelasCount` | Bisa > 1.0 jika banyak first-slot sama |
| SC-11 | `$sc11 / $jurCount` | `max - min` lab usage bisa jauh > 1.0 |
| SC-12 | `labDayPackPenalty` | Sudah capped min(1.0) — OK |

**SC-4 dan SC-5 adalah kontributor penalty terbesar** karena *setiap unit selalu menghasilkan penalty*. Ini bukan sebenarnya "penalty", ini hanya noise — heavy subject di slot pagi dan light subject di slot sore seharusnya **tidak** kena penalty, tapi rumus saat ini selalu menghitung penalty apapun slot-nya.

**Estimasi sum penalty saat ini** (tanpa GA optimasi):
- SC-1 (gap, w=9): ~0.1 × 9 = **0.9**
- SC-2 (gap, w=9): ~0.1 × 9 = **0.9**
- SC-3 (spread, w=7): ~0.3 × 7 = **2.1**
- SC-4 (heavy_morning, w=6): ~0.25 × 6 = **1.5**
- SC-5 (light_afternoon, w=5): ~0.25 × 5 = **1.25**
- SC-6 (load balance, w=7): ~0.5 × 7 = **3.5**
- SC-7 (preference, w=5): ~0.0 × 5 = **0** (jika tidak ada preferensi)
- SC-8 (room transition, w=5): ~0.01 × 5 = **0.05**
- SC-9 (teacher continuity, w=4): ~0.1 × 4 = **0.4**
- SC-10 (first slot, w=3): ~0.5 × 3 = **1.5**
- SC-11 (lab balance, w=6): ~1.5 × 6 = **9.0** ← unbounded!
- SC-12 (lab pack, w=7): ~0.3 × 7 = **2.1**
- lab_pref (w=5): ~0.3 × 5 = **1.5**

**Estimated total: ~24.7** → Fitness ≈ `1/(1+24.7)` = **0.039** ← Sangat mirip dengan 0.0316!

---

#### 🔴 Masalah 2: GA Timeout Terlalu Pendek & Population Kecil

Di [ScheduleGenerator.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/ScheduleGenerator.php#L497):
```php
'timeout_seconds' => min(180, $cspTimeout),
```
GA timeout **hard-capped di 180 detik** — terlalu singkat untuk 1008 unit. Dan dengan population 100 × fitness evaluation yang mahal (O(n) per call, di-call per chromosome per generation), GA hanya bisa menjalankan sedikit generasi.

---

#### 🔴 Masalah 3: Fitness Evaluation Terlalu Mahal (O(n²) Efektif)

Setiap generation, GA menghitung fitness **semua 100 kromosom**. Setiap fitness call iterasi seluruh 1008 assignment. Berarti: `100 × 1008 = 100,800` iterasi per generation. Ditambah mutation/crossover/isFeasible checks, GA terlalu lambat untuk 1008 unit.

---

#### 🔴 Masalah 4: Crossover Terlalu Konservatif

Di [crossover()](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/GAEngine.php#L210-L235): setiap gene swap membutuhkan `isFeasible()` check (O(n) per check). Dengan block size 40% × 1008 ≈ 403 genes, crossover melakukan **403 × isFeasible()** calls = sangat mahal. Dan banyak gene yang di-reject karena feasibility → crossover hampir tidak merubah apapun.

---

#### 🔴 Masalah 5: CSP Output Tidak Soft-Constraint Aware

CSP hanya fokus HC satisfaction (zero conflict) tanpa mempertimbangkan soft constraint quality. Hasilnya, initial solution dari CSP memiliki soft constraint quality rendah, dan GA harus bekerja ekstra keras untuk memperbaikinya.

---

## Keputusan Desain (Resolved)

| # | Pertanyaan | Keputusan |
|---|-----------|-----------|
| 1 | SC-4/SC-5 formula | **Violation-based**: SC-4 penalty hanya jika heavy subject (bobot ≥ 7) di afternoon (slot_index > 50% cap). SC-5 penalty hanya jika light subject (bobot ≤ 3) di morning (slot_index < 50% cap). Normalisasi = violations / eligible_count. |
| 2 | Timeout terpisah | **Ya, pisah**: `csp_timeout_seconds` (default 300) + `ga_timeout_seconds` (default 300). Masing-masing independen. |
| 3 | SC-11 lab balance | **Normalisasi ke daily capacity**: `min(1.0, (max - min) / avgDailyCap)` |

---

## Proposed Changes

### 1. Normalisasi Penalty yang Benar

#### [MODIFY] [GAEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/GAEngine.php)

Perbaikan method `penalties()` — semua penalty di-cap strict ke range 0–1:

**SC-1 (teacher gaps) — normalisasi per guru-day terhadap daily capacity:**
```php
// BEFORE: raw gap count / unit count
// AFTER: average(gap / dailyCap) per guru-day pair
$sc1 = 0.0;
$sc1Pairs = 0;
foreach ($guruDaySlots as $days) {
    foreach ($days as $hariId => $slots) {
        $cap = $dailyCap[$hariId] ?? 1;
        $gap = $this->gapCount($slots);
        $sc1 += min(1.0, $gap / max(1, $cap));
        $sc1Pairs++;
    }
}
$sc1 = $sc1Pairs > 0 ? $sc1 / $sc1Pairs : 0.0;
```

**SC-2 (student gaps) — same pattern:**
```php
$sc2 = 0.0;
$sc2Pairs = 0;
foreach ($kelasDaySlots as $days) {
    foreach ($days as $hariId => $slots) {
        $cap = $dailyCap[$hariId] ?? 1;
        $gap = $this->gapCount($slots);
        $sc2 += min(1.0, $gap / max(1, $cap));
        $sc2Pairs++;
    }
}
$sc2 = $sc2Pairs > 0 ? $sc2 / $sc2Pairs : 0.0;
```

**SC-4 (heavy morning) — violation-based, bobot ≥ 7 only:**
```php
// Hitung: berapa heavy unit (bobot ≥ 7) yang ada di afternoon?
$sc4Violations = 0;
$sc4Total = 0;
foreach ($schedule as $unitId => $a) {
    $bobot = (int) ($this->units[$unitId]['bobot_kognitif'] ?? 5);
    if ($bobot < 7) continue;
    $sc4Total++;
    $hariId = (int) $a['hari_id'];
    $slotIdx = (int) $a['slot_index'];
    $cap = $dailyCap[$hariId] ?? 1;
    $midpoint = $cap / 2.0;
    if ($slotIdx >= $midpoint) { // afternoon
        $sc4Violations++;
    }
}
$sc4 = $sc4Total > 0 ? $sc4Violations / $sc4Total : 0.0;
```

**SC-5 (light afternoon) — violation-based, bobot ≤ 3 only:**
```php
$sc5Violations = 0;
$sc5Total = 0;
foreach ($schedule as $unitId => $a) {
    $bobot = (int) ($this->units[$unitId]['bobot_kognitif'] ?? 5);
    if ($bobot > 3) continue;
    $sc5Total++;
    $hariId = (int) $a['hari_id'];
    $slotIdx = (int) $a['slot_index'];
    $cap = $dailyCap[$hariId] ?? 1;
    $midpoint = $cap / 2.0;
    if ($slotIdx < $midpoint) { // morning
        $sc5Violations++;
    }
}
$sc5 = $sc5Total > 0 ? $sc5Violations / $sc5Total : 0.0;
```

**SC-6 (load balance) — cap ke 0–1:**
```php
// BEFORE: $sc6 += $total > 0 ? $dev / $total : 0.0 (bisa > 1.0)
// AFTER: min(1.0, deviation / expected_ideal)
$sc6 += min(1.0, $total > 0 ? $dev / $total : 0.0);
```

**SC-10 (first slot rotation) — cap ke 0–1:**
```php
$sc10 = min(1.0, $sc10 / max(1, $kelasCount));
```

**SC-11 (lab balance) — normalisasi ke avg daily capacity:**
```php
$avgDailyCap = 0;
foreach ($dailyCap as $cap) { $avgDailyCap += $cap; }
$avgDailyCap = count($dailyCap) > 0 ? $avgDailyCap / count($dailyCap) : 1;

$sc11 = 0.0;
foreach ($jurusanDayLab as $days) {
    $vals = array_values($days);
    $delta = max($vals) - min($vals);
    $sc11 += min(1.0, $delta / max(1, $avgDailyCap));
}
$sc11 = $jurCount > 0 ? $sc11 / $jurCount : 0.0;
```

**SC-3 / SC-8 / SC-9 — juga di-cap `min(1.0)`** (spread mapel per kelompok, room transitions per kelas-day, mixed-guru per `kelas_mapel`). Ini melengkapi SC-1/SC-2 (per (guru/kelas, hari) terhadap `dailyCap`), SC-6 (`min(1.0, dev/total)`), SC-10 (`min(1.0, ...)`) di atas.

---

### 2. Caching & Performa Fitness Evaluation

#### [MODIFY] [GAEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/GAEngine.php)

- **Pre-compute `$dailyCap` array** di constructor dan simpan sebagai property (saat ini dihitung ulang setiap `penalties()` call).
- **Pre-compute `$numHari`** di constructor.
- **Fitness caching via spl_object_hash-like key**: Gunakan `md5(serialize($schedule))` pada elite chromosomes yang di-carry-over tanpa modifikasi → skip re-evaluation. Ini menghindari ~10% (elitism ratio) dari fitness calls.
- **Reduce population `array_map` overhead**: Ganti `array_map(fn ($c) => $this->fitness($c), $population)` dengan simple foreach + cache check.
- **Cache limit 5000**: cache fitness di-reset bila sudah 5000 entry (`count($this->fitnessCache) >= 5000`) agar tidak membengkak tanpa batas.
- **Timeout guard di inisialisasi populasi**: loop pembentukan populasi berhenti bila budget timeout hampir habis, supaya waktu tidak habis sebelum optimasi dimulai.

---

### 3. Faster isFeasible + Crossover Optimization

#### [MODIFY] [GAEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/GAEngine.php)

**Crossover — batch validation instead of per-gene:**
```php
// BEFORE: for each gene in block, trial + isFeasible (O(n) each)
// AFTER: copy block, single isFeasible check, fallback per-gene on failure
protected function crossover(array $parentA, array $parentB): array
{
    $child = $parentA;
    $ids = array_keys($parentA);
    $n = count($ids);
    if ($n < 2) return $child;

    $len = max(1, (int) floor($n * 0.3));
    $start = mt_rand(0, max(0, $n - $len));
    $block = array_slice($ids, $start, $len);

    // Try batch swap first (much faster)
    $trial = $child;
    foreach ($block as $unitId) {
        if (isset($parentB[$unitId])) {
            $trial[$unitId] = $parentB[$unitId];
        }
    }
    if ($this->isFeasible($trial)) {
        return $trial; // Single isFeasible check succeeds
    }

    // Fallback: per-gene with early-exit after N failures
    $failures = 0;
    foreach ($block as $unitId) {
        if (!isset($parentB[$unitId]) || $failures > 5) continue;
        $trial = $child;
        $trial[$unitId] = $parentB[$unitId];
        if ($this->isFeasible($trial)) {
            $child = $trial;
        } else {
            $failures++;
        }
    }
    return $child;
}
```

**Multi-swap mutation** — lebih efektif daripada single-swap:
```php
protected function mutateSchedule(array $schedule): array
{
    if ($schedule === []) return $schedule;

    // Try 1-3 random swaps in one mutation
    $swapCount = mt_rand(1, 3);
    $trial = $schedule;
    for ($i = 0; $i < $swapCount; $i++) {
        $unitId = (int) array_rand($trial);
        $candidates = $this->validCandidates($unitId, $trial);
        if ($candidates === []) continue;
        $trial[$unitId] = $candidates[array_rand($candidates)];
    }
    return $this->isFeasible($trial) ? $trial : $schedule;
}
```

---

### 4. GA Timeout Terpisah & Parameter Tuning

#### [MODIFY] [ScheduleGenerator.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/ScheduleGenerator.php)

Pisah timeout CSP dan GA + hapus hard cap:

```php
// BEFORE (line ~438, ~497):
$solverConfig = [
    'timeout_seconds'  => max(15, (int) ($config['timeout_seconds'] ?? 300)),
    ...
];
// GA: 'timeout_seconds' => min(180, $cspTimeout),

// AFTER:
$cspTimeout = max(15, min(3600, (int) ($config['csp_timeout_seconds'] ?? $config['timeout_seconds'] ?? 300)));
$gaTimeout  = max(15, min(3600, (int) ($config['ga_timeout_seconds'] ?? 300)));

$solverConfig = [
    'timeout_seconds'  => $cspTimeout,
    'csp_max_attempts' => max(1, (int) ($config['csp_max_attempts'] ?? 12)),
];

// Pass GA timeout directly:
$gaEngine = new GAEngine(array_merge($engineData, [
    ...
    'timeout_seconds' => $gaTimeout,  // NO MORE min(180, ...)
    ...
]));
```

#### [MODIFY] [ScheduleController.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Controllers/Kurikulum/ScheduleController.php)

Update `initDefaultConfig()` — rename `timeout_seconds` ke `csp_timeout_seconds`, tambah `ga_timeout_seconds`:

```php
$defaults = [
    // CSP (Fase 1)
    ...
    'csp_timeout_seconds'         => 300,   // WAS: timeout_seconds
    // GA (Fase 2)
    ...
    'ga_timeout_seconds'          => 300,   // NEW
    ...
];
```

Update `saveConfig()` whitelist — tambah `csp_timeout_seconds`, `ga_timeout_seconds`, hapus `timeout_seconds`.

#### [MODIFY] Config View

Update [config.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Views/kurikulum/schedule/config.php) — ganti input `timeout_seconds` dengan dua input terpisah `csp_timeout_seconds` dan `ga_timeout_seconds`.

---

### 5. CSP Soft-Constraint Awareness (Quick Win)

#### [MODIFY] [CSPEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/CSPEngine.php)

Pada LCV scoring di `candidateAssignments()`, tambah bonus kecil untuk gap minimization:

```php
// Non-lab candidate score (line ~482):
// BEFORE: $dayCount * 100 + $slotIndex
// AFTER: prefer adjacent slots to reduce teacher/class gaps
$adjBonus = $this->adjacencyBonus($kelasId, $guruId, $hariId, $slotIndex);
$score = $dayCount * 100 + $slotIndex - $adjBonus;
```

Tambah helper method `adjacencyBonus()`:
```php
protected function adjacencyBonus(int $kelasId, int $guruId, int $hariId, int $slotIndex): int
{
    $bonus = 0;
    // Check if guru already has adjacent slot on this day
    $guruSlots = $this->guruSlot[$guruId][$hariId] ?? [];
    foreach (array_keys($guruSlots) as $tsId) {
        // Find slot_index of existing assignment
        foreach ($this->jpSlotsByHari[$hariId] ?? [] as $slot) {
            if ((int) $slot['id'] === $tsId && abs($slot['slot_index'] - $slotIndex) <= 1) {
                $bonus += 30;
                break;
            }
        }
    }
    // Same for kelas
    $kelasSlots = $this->kelasSlot[$kelasId][$hariId] ?? [];
    foreach (array_keys($kelasSlots) as $tsId) {
        foreach ($this->jpSlotsByHari[$hariId] ?? [] as $slot) {
            if ((int) $slot['id'] === $tsId && abs($slot['slot_index'] - $slotIndex) <= 1) {
                $bonus += 20;
                break;
            }
        }
    }
    return $bonus;
}
```

---

### 6. Penalty Breakdown Logging

#### [MODIFY] [GAEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/GAEngine.php)

Return penalty detail breakdown dari `optimize()`:

```php
return [
    'assignments'       => $best,
    'fitness'           => round($bestFitness, 6),
    'generations'       => $generations,
    'violations'        => (int) round($violations * 100),
    'penalty_breakdown' => $penalties,     // NEW: raw 0-1 penalties per SC
    'weighted_sum'      => round($sum, 4), // NEW: total Σ Wi×Pi
];
```

#### [MODIFY] [ScheduleGenerator.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/ScheduleGenerator.php)

Store penalty breakdown di report:

```php
$report['stats']['ga'] = [
    'fitness'           => $gaResult['fitness'],
    'generations'       => $gaResult['generations'],
    'violations'        => $gaResult['violations'],
    'penalty_breakdown' => $gaResult['penalty_breakdown'] ?? [], // NEW
    'weighted_sum'      => $gaResult['weighted_sum'] ?? null,    // NEW
];
```

---

## Files Modified Summary

| File | Perubahan |
|------|-----------|
| [GAEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/GAEngine.php) | Fix normalisasi SC-1..SC-12, caching, crossover optimization, multi-swap mutation, penalty breakdown return, pre-computed statics |
| [ScheduleGenerator.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/ScheduleGenerator.php) | Pisah CSP/GA timeout, hapus hard cap 180s, log penalty breakdown |
| [CSPEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/CSPEngine.php) | adjacencyBonus di LCV ordering |
| [ScheduleController.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Controllers/Kurikulum/ScheduleController.php) | Default config: csp_timeout_seconds + ga_timeout_seconds, update saveConfig whitelist, clamp server-side semua key numerik (timeout 15–3600) |
| Config View | 2 input timeout terpisah |

---

## Dampak Estimasi

| Aspek | Sebelum | Setelah (Estimasi) |
|-------|---------|-------------------|
| Fitness | 0.0316 | **0.65 – 0.85** |
| Waktu total | ~53 menit | **5–15 menit** |
| Sum penalty | ~30.65 | **~0.2–0.5** |

> [!IMPORTANT]
> **Perbaikan normalisasi (item 1) adalah yang paling kritis** — ini sendiri akan mengangkat fitness dari ~0.03 ke ~0.5+ tanpa perubahan lain. Item 2–6 akan mengangkat ke ~0.7–0.85 dengan memberikan GA waktu, kecepatan, dan CSP initial quality yang lebih baik.

## Verification Plan

### Automated Tests
- Jalankan generate ulang dengan data yang sama → bandingkan fitness sebelum/sesudah
- Bandingkan waktu eksekusi

### Manual Verification
- Generate jadwal via UI, perhatikan fitness score dan waktu
- Periksa **penalty breakdown** di history detail → pastikan semua penalty 0–1
- Cek kualitas jadwal secara visual (gaps berkurang, heavy morning benar, load balance, etc.)
- Pastikan zero conflict tetap terjaga (HC integrity)
