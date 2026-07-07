---
name: schedule-generator
description: Implement S3 v3.1 scheduling — CSP + GA with kelas_mapel units (1 JP each), automatic guru assignment from guru_mapel, per-day timeslots, HC-1..HC-8, SC-1..SC-11 weighted (SC-7 skipped). ScheduleGenerator orchestrator, CSPEngine (AC-3 + backtracking + MRV/LCV), GAEngine (tournament/OX/elitism/adaptive mutation), pre-validation, jadwal storage with kelas_mapel_id.
---

# Schedule Generator — CSP + GA (v3.1)

> **PRD Reference**: `docs/PRD.md` Section 6 + `docs/CSP-GA-Constraints-Parameters.md`

## Architecture

```
ScheduleGenerator
├── validate()     → 10 pre-checks including guru_hari_blokir capacity
├── generate()     → CSP → GA → save jadwal
├── CSPEngine      → AC-3 + backtracking + forward checking (MRV/LCV) + min-conflict repair
└── GAEngine       → optimize SC-1..SC-11, repair HC-1..HC-8
SchedulingContext  → shared helpers (JP slots, guru pool, eligibility)
```

## Data Model (v3.0)

**Variable**: One **placement unit** = 1 JP from `kelas_mapel`.

```php
class PlacementUnit {
    public int $kelas_mapel_id;
    public int $kelas_id;
    public int $mapel_id;
    public int $jurusan_id;      // from kelas
    public bool $butuh_lab;
    public ?int $lab_id;
    public int $homeroom_id;
    public int $bobot_kognitif;  // from mapel, for SC-4/SC-5
    public int $unit_index;      // 0..jam_per_minggu-1 for this kelas_mapel
}
```

Expand each `kelas_mapel` into `jam_per_minggu` units. **No durasi_blok in master** — algorithm may place 1 JP alone or consecutive JPs same mapel/day (may span istirahat since v3.1).

**Assignment tuple**: `(hari_id, timeslot_id, guru_id, ruangan_id)`

### Guru pool (per unit)
Eligible `guru_id` from `guru_mapel` WHERE:
- `mapel_id` matches
- Remaining weekly cap > 0 (HC-6)
- Mapel umum OR `kelas.jurusan_id` = `mapel.jurusan_id` (HC-7)
- `hari_id` NOT IN `guru_hari_blokir` (HC-4)

### Timeslot domain
- Only `timeslot` WHERE `hari_id` = X AND `tipe = 'jp'` (HC-8)
- Per-day slot lists differ (Sen 10, Sel 11, Jum 6, etc.)

## Hard Constraints HC-1..HC-8

| HC | Check |
|----|-------|
| HC-1 | No guru double-booking (same hari+timeslot) |
| HC-2 | No class double-booking |
| HC-3 | Lab conflict only (homerooms unique per class) |
| HC-4 | No assignment on `guru_hari_blokir` days |
| HC-5 | Σ scheduled JP per kelas_mapel = jam_per_minggu (relaxed: gaps/trailing empty OK) |
| HC-6 | Guru in guru_mapel + weekly cap `max_jam_per_minggu` |
| HC-7 | Kejuruan mapel → matching kelas jurusan; ruangan = homeroom unless butuh_lab → lab_id |
| HC-8 | Placement only in `tipe='jp'` slots |

**Dropped from v2.0**: no-gap-mid-day (was HC6) and guru max-JP-per-day (was HC9). Gaps are now soft (SC-1/SC-2), load balance is soft (SC-6).

**Dropped in v3.1**: practical block rule (old HC-8) — same mapel may span istirahat; `blok_group` is UI-only merge metadata.

## Soft Constraints SC-1..SC-11 (GA only, weights 1–10)

| SC | Weight | Rule | Penalty (normalized 0–1) |
|----|--------|------|--------------------------|
| SC-1 | 9 | Minim gap guru | gap slots between first/last teaching per guru per day |
| SC-2 | 9 | Minim gap siswa | gap slots between first/last JP per class per day |
| SC-3 | 7 | Distribusi mapel/minggu | variance of mapel occurrences across days per class |
| SC-4 | 6 | Mapel berat pagi | high `bobot_kognitif` placed in late slots |
| SC-5 | 5 | Mapel ringan sore | low `bobot_kognitif` placed in early slots |
| SC-6 | 7 | Beban guru seimbang | variance of guru JP across days |
| SC-7 | — | Preferensi guru | **NOT implemented** (needs `guru_preferensi`) |
| SC-8 | 5 | Minim perpindahan ruang | lab room transitions per class |
| SC-9 | 4 | Kontinuitas guru | auto-satisfied → 0 |
| SC-10 | 3 | Rotasi mapel jam pertama | repeated first-slot mapel across days |
| SC-11 | 6 | Load balance lab antar jurusan | variance of lab usage across days per jurusan |

**Fitness**: `1 / (1 + Σ(Wi × Penalty_i_normalized))`

## CSPEngine

```
solve():
  AC-3 domain pruning (HC-4, HC-6, HC-7, HC-8 pre-filter)
  backtrack(unassigned_units):
    pick unit (MRV — smallest remaining domain)
    for (hari, timeslot) ordered by LCV:
      for guru in eligible_gurus(unit, hari):
        if HC-1,2,3 pass with forward-checking:
          assign; propagate
          if backtrack(remaining): return solution
          unassign
    return fail
  on partial fail: min-conflict repair pass
```

Track per `(guru_id, mapel_id)` assigned count for HC-6.

## GAEngine

- Chromosome: full assignment array (all from CSP variations, not random)
- Selection: Tournament (k = `tournament_size`, default 5)
- Crossover: Order Crossover (OX), rate `crossover_rate` (0.8)
- Mutation: swap two units' slots + HC repair, rate `mutation_rate` (0.08)
- Elitism: top `elitism_ratio` (10%) carried forward
- Adaptive mutation: after `adaptive_mutation_trigger` (20) stagnant gens, mutation += `adaptive_mutation_increment` (0.02)
- Termination: fitness ≥ threshold OR `stagnation_limit` (40) gens no improvement OR max_generations OR timeout
- All offspring must pass `isFeasible()` (re-check HC-1..HC-8) or be repaired/rejected

## ScheduleGenerator

```php
public function validate(int $tahunAjaranId): array  // 10 checks per PRD §6.3

public function generate(int $tahunAjaranId, int $userId): array
  // 1. validate
  // 2. schedule_logs status=running, generated_by=userId
  // 3. Load kelas_mapel, guru_mapel, guru_hari_blokir, timeslots by hari, kelas, guru, ruangan, mapel.bobot_kognitif
  // 4. Build PlacementUnits
  // 5. CSPEngine::solve()
  // 6. GAEngine::optimize()
  // 7. DELETE existing jadwal for tahun_ajaran; INSERT new rows:
  //    kelas_mapel_id, hari_id, timeslot_id, kelas_id, guru_id, mapel_id, ruangan_id, blok_group
  // 8. Update schedule_logs

public function reset(int $tahunAjaranId): bool
```

## Config (schedule_config per tahun_ajaran)

CSP: `csp_consistency_method`, `csp_variable_ordering`, `csp_value_ordering`, `csp_repair_strategy`, `csp_max_attempts`
GA: `population_size`, `max_generations`, `tournament_size`, `crossover_rate`, `crossover_method`, `mutation_rate`, `mutation_method`, `elitism_ratio`, `stagnation_limit`, `adaptive_mutation`, `adaptive_mutation_trigger`, `adaptive_mutation_increment`, `fitness_threshold`, `timeout_seconds`
SC weights: `sc1_teacher_gap`, `sc2_student_gap`, `sc3_subject_distribution`, `sc4_heavy_morning`, `sc5_light_afternoon`, `sc6_teacher_load_balance`, `sc8_room_transition`, `sc9_teacher_continuity`, `sc10_first_slot_rotation`, `sc11_lab_load_balance`

## UI — `Kurikulum\ScheduleController`

- Path: `app/Views/kurikulum/schedule/`
- Pre-validation panel (10 checks)
- Generate, config, result, logs, reset
- Views: kelas / guru / ruangan (use `timetable-views` skill)

## Testing Checklist

- [ ] All 10 pre-validations detect bad data
- [ ] CSP finds a COMPLETE solution on seed data (all weekly JP placed)
- [ ] HC-1..HC-8 satisfied in output
- [ ] Guru auto-assigned; respects guru_mapel weekly caps (HC-6)
- [ ] HC-4: blocked guru has zero JP on that day
- [ ] HC-5: weekly JP met (gaps allowed, no over/under-fill per kelas_mapel)
- [ ] GA improves SC-1..SC-11 without breaking HC
- [ ] SC-4/SC-5 use mapel.bobot_kognitif
- [ ] jadwal uses kelas_mapel_id; logs reference users.id as generated_by
