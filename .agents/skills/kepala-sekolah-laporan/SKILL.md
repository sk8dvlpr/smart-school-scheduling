---
name: kepala-sekolah-laporan
description: Implement Kepala Sekolah module for S3 v2.0 — view all school schedules (kelas/guru/ruangan), laporan jam mengajar per guru from generated jadwal for manual salary calculation, filter by tahun ajaran, export PDF/Excel.
---

# Kepala Sekolah — Jadwal & Laporan (v2.0)

> **PRD Reference**: `docs/PRD.md` Section 7.7, Section 9.5

## Overview

Read-only access for `role = kepala_sekolah`: browse all schedules + **laporan jam mengajar guru** derived from latest `jadwal` generate (for manual gaji calculation).

## Controllers

### KepalaSekolah\JadwalController

```php
namespace App\Controllers\KepalaSekolah;

public function index(): string              // GET /kepala-sekolah/jadwal — picker UI
public function byKelas(int $id): string   // GET .../jadwal/kelas/{id}
public function byGuru(int $id): string
public function byRuangan(int $id): string
```

- Reuse `components/timetable` partial (see `timetable-views` skill)
- Active `tahun_ajaran` or dropdown filter
- No edit/generate — view only

### KepalaSekolah\LaporanController

```php
public function guruJam(): string           // GET /kepala-sekolah/laporan/guru-jam
public function export(): Response          // GET .../laporan/guru-jam/export?format=pdf|excel
```

## Laporan Jam Mengajar — Data Query

Aggregate from `jadwal` for active `tahun_ajaran_id`:

```sql
-- Conceptual (use Query Builder)
SELECT
  g.id AS guru_id,
  u.nama AS nama_guru,
  u.nip,
  m.kode AS mapel_kode,
  m.nama AS mapel_nama,
  COUNT(j.id) AS total_jp
FROM jadwal j
JOIN guru g ON g.id = j.guru_id
JOIN users u ON u.id = g.user_id
JOIN mapel m ON m.id = j.mapel_id
WHERE j.tahun_ajaran_id = ?
GROUP BY g.id, m.id
ORDER BY u.nama, m.nama
```

**Summary row per guru**: Σ total_jp across all mapel.

Optional drill-down: JP per hari per guru (second tab or expandable row).

## View (`kepala_sekolah/laporan/guru_jam.php`)

| NIP | Nama Guru | Mapel | JP/Minggu |
|-----|-----------|-------|-----------|
| ... | ... | MTK | 12 |
| ... | ... | Fisika | 8 |
| | **Subtotal** | | **20** |

- DataTable with search/sort
- Filter: tahun ajaran (if multiple logs)
- Note footer: "Data dari jadwal generate terakhir — untuk perhitungan gaji manual"
- Export buttons: PDF, Excel

## Export — LaporanGuruJamExporter

Separate from timetable export (`export-feature` skill):

### PDF
- Portrait A4
- Table: NIP, Nama, Mapel, JP
- Subtotal per guru
- Header: sekolah, tahun ajaran, tanggal cetak

### Excel
- Sheet "Rekap Guru"
- Columns: NIP, Nama Guru, Kode Mapel, Nama Mapel, JP Mingguan
- Summary section with SUM per guru
- Optional sheet "Detail per Hari" for drill-down

```php
class LaporanGuruJamExporter
{
    public function toPdf(array $rows, array $meta): string
    public function toExcel(array $rows, array $meta): string
}
```

## Dashboard Integration

`dashboard/kepala_sekolah.php`:
- Card: "Total Guru Terjadwal" (COUNT DISTINCT guru_id in jadwal)
- Quick link: `/kepala-sekolah/laporan/guru-jam`
- Quick link: `/kepala-sekolah/jadwal`

## Routes

```
GET /kepala-sekolah/dashboard
GET /kepala-sekolah/jadwal
GET /kepala-sekolah/jadwal/kelas/(:num)
GET /kepala-sekolah/jadwal/guru/(:num)
GET /kepala-sekolah/jadwal/ruangan/(:num)
GET /kepala-sekolah/laporan/guru-jam
GET /kepala-sekolah/laporan/guru-jam/export
```

All behind `KepalaSekolahFilter`.

## Business Rules

- Report reflects **current `jadwal` table** (last successful generate)
- If no jadwal → show empty state with message
- JP count = COUNT of `jadwal` rows (1 row = 1 JP)
- Do not use `guru_mapel.max_jam_per_minggu` for report — use actual assigned schedule

## Testing Checklist

- [ ] Kepala Sekolah cannot access `/kurikulum/*`
- [ ] Laporan shows correct JP totals per guru per mapel
- [ ] Subtotal matches sum of jadwal rows
- [ ] Export PDF/Excel downloads correctly
- [ ] Empty jadwal handled gracefully
- [ ] Timetable views work for all kelas/guru
