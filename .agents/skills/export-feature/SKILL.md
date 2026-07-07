---
name: export-feature
description: PDF and Excel export for S3 v2.0 using DomPDF and PhpSpreadsheet — dynamic per-day timetable grids, schedule per kelas/guru/ruangan, guru own schedule export, Kurikulum and Kepala Sekolah full access. Supports variable JP rows per weekday.
---

# Export Feature — PDF & Excel (v2.0)

> **PRD Reference**: `docs/PRD.md` Section 7.6, Section 9.3–9.5

## Installation

```bash
composer require dompdf/dompdf
composer require phpoffice/phpspreadsheet
```

## Export Types

| Type | Format | Roles |
|------|--------|-------|
| Jadwal per Kelas | PDF/Excel | Kurikulum, Kepala Sekolah |
| Jadwal per Guru | PDF/Excel | Kurikulum, Kepala Sekolah, Guru (own) |
| Jadwal per Ruangan | PDF/Excel | Kurikulum, Kepala Sekolah |
| Laporan Jam Guru | PDF/Excel | Kepala Sekolah only (see `kepala-sekolah-laporan` skill) |

**Removed**: Murid export.

## Libraries

### PdfExporter (`app/Libraries/PdfExporter.php`)

```php
public function exportByKelas(int $kelasId, int $tahunAjaranId): string
public function exportByGuru(int $guruId, int $tahunAjaranId): string
public function exportByRuangan(int $ruanganId, int $tahunAjaranId): string
private function renderDynamicTimetableHtml(array $jadwal, array $timeslotsByHari, array $meta): string
```

- A4 landscape
- Inline CSS for DomPDF
- **Per-day column**: render each hari's slots independently (variable row count)
- Mark istirahat / kegiatan_khusus rows
- School header, tahun ajaran, generated timestamp

### ExcelExporter (`app/Libraries/ExcelExporter.php`)

```php
public function exportByKelas(int $kelasId, int $tahunAjaranId): string
public function exportByGuru(int $guruId, int $tahunAjaranId): string
public function exportByRuangan(int $ruanganId, int $tahunAjaranId): string
```

- Per-hari columns may have different row counts — use separate row blocks or max-row grid with merged empty cells
- Cell fill from `mapel.warna`
- Multi-JP: merge cells via `blok_group`
- Multi-sheet "all classes" optional for Kurikulum

## Controller Integration

### Kurikulum\ScheduleController
```php
public function export(string $type): Response
// type: pdf-kelas-{id}, excel-guru-{id}, etc.
```

### Guru\JadwalController
```php
public function export(): Response
// ?format=pdf|excel — only session('guru_id')
```

### KepalaSekolah\JadwalController
Same export patterns as Kurikulum for schedule grids.

## Dynamic Grid Notes

v2.0 timeslots are **not uniform** across days. Export must:
1. Load `timeslot` grouped by `hari_id`
2. For each day column, iterate that day's slots in `jam_ke` order
3. Skip scheduling into `istirahat` / `kegiatan_khusus` rows
4. Jumat ends at 6 JP — do not pad to 11 rows

## Download Response

```php
return $this->response
    ->setHeader('Content-Type', 'application/pdf')
    ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
    ->setBody($content);
```

## Testing Checklist

- [ ] PDF renders variable columns (Sen 10 vs Jum 6)
- [ ] Excel colors and merges work
- [ ] Guru cannot export other guru's schedule
- [ ] Kepala Sekolah can export any view
- [ ] Filename includes entity name + tahun ajaran
