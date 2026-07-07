---
name: timetable-views
description: Build dynamic per-day timetable grids for S3 v2.0 — variable JP rows per weekday column, kegiatan khusus rows, color-coded mapel, merged multi-JP cells, views for Kurikulum, Guru (own schedule), and Kepala Sekolah. No murid views.
---

# Timetable Views (v2.0)

> **PRD Reference**: `docs/PRD.md` Section 7.6, Section 2.2

## Overview

Reusable timetable component with **dynamic row counts per day column** (not a uniform 10-row grid). Timeslots loaded per `hari_id`.

| Hari | JP rows |
|------|---------|
| Senin | 10 |
| Selasa | 11 |
| Kamis | 11 |
| Rabu | 10 |
| Jumat | 6 |

Plus non-schedulable rows: istirahat, kegiatan_khusus (upacara, pembiasaan).

## Shared Partial (`app/Views/components/timetable.php`)

```php
<?= view('components/timetable', [
    'jadwal'       => $jadwalData,
    'hari'         => $hariList,
    'timeslots'    => $timeslotsByHari,  // keyed by hari_id → array of slots
    'viewType'     => 'kelas',           // kelas | guru | ruangan
    'title'        => 'X TKJ 1',
    'showGuru'     => true,
]) ?>
```

### Rendering Strategy

**Option A (recommended)**: Build row index per day independently — each column has its own vertical stack of slots ordered by `jam_ke`. Cells align by row groups where possible; use `rowspan` within each day's column for merged JP blocks.

**Option B**: Master row list = union of all slot times across days (sparse cells where day has no slot at that row). More complex alignment.

Use **Option A**: iterate per hari column with that day's `timeslots` ordered by `jam_ke`.

```php
foreach ($hariList as $hari) {
    foreach ($timeslotsByHari[$hari->id] as $slot) {
        if ($slot->tipe === 'istirahat' || $slot->tipe === 'kegiatan_khusus') {
            // render non-schedulable row (gray bar, keterangan)
        } elseif ($slot->tipe === 'jp') {
            // render cell with jadwal or empty
        }
    }
}
```

### Cell Content by viewType

**kelas**: Mapel (warna), Guru, Ruangan (lab only badge)

**guru**: Mapel, Kelas, Ruangan — empty = free period; footer total JP/minggu

**ruangan**: Mapel, Kelas, Guru

### Multi-JP Merge

Group by `blok_group` UUID — first slot gets `rowspan`, subsequent skipped.

## View Pages

| Path | Audience |
|------|----------|
| `kurikulum/schedule/view_kelas.php` | Kurikulum — dropdown kelas |
| `kurikulum/schedule/view_guru.php` | Kurikulum — dropdown guru |
| `kurikulum/schedule/view_ruangan.php` | Kurikulum — dropdown ruangan |
| `guru/jadwal/index.php` | Guru — own schedule (`session('guru_id')`) |
| `kepala_sekolah/jadwal/*.php` | Kepala Sekolah — all schedules |

## Controllers

### Guru\JadwalController
```php
public function index(): string
    $guruId = session('guru_id');
    // Fail if null (kurikulum non-mengajar should not reach here)
    return timetable for guru_id + active tahun_ajaran
```

### KepalaSekolah\JadwalController
```php
public function index()       // picker: kelas / guru / ruangan
public function byKelas($id)
public function byGuru($id)
public function byRuangan($id)
```

## JadwalModel Helpers

```php
public function getByKelas(int $kelasId, int $tahunAjaranId): array
public function getByGuru(int $guruId, int $tahunAjaranId): array
public function getByRuangan(int $ruanganId, int $tahunAjaranId): array
public function countJpByGuru(int $guruId, int $tahunAjaranId): int
```

Join: `users.nama` (via guru→users), `mapel.nama`, `mapel.warna`, `ruangan.kode`, `timeslot.*`, `hari.*`.

Load timeslots:
```php
public function getTimeslotsGroupedByHari(): array
```

## Styling

- Cell background from `mapel.warna`
- `.timetable-break`, `.timetable-kegiatan` for non-JP rows
- `.badge-lab` for lab indicator
- Responsive: horizontal scroll + sticky time column; mobile "hari ini" card list

## Removed

- `murid/jadwal/*` views and controller
- Uniform 10-row assumption
- Global timeslot list (use `hari_id` filter)

## Testing Checklist

- [ ] Sen column shows 10 JP + upacara row
- [ ] Jumat column shows 6 JP only
- [ ] Istirahat/kegiatan rows non-clickable
- [ ] Merged cells for blok_group
- [ ] Guru view shows total JP
- [ ] Kepala Sekolah can view any kelas/guru
- [ ] Guru sees only own schedule
