# Audit Fase 4 — Bug, Kualitas Kode, UX, Aksesibilitas

> Tanggal: 2026-09-29

## Ringkasan

Fokus: validator HC independen, perbaikan drift model, fondasi CI, dokumentasi UX gaps. Algoritma CSP/GA tidak diubah.

## Temuan

| ID | Severity | Kategori | Lokasi | Deskripsi | Perbaikan | Status |
|----|----------|----------|--------|-----------|-----------|--------|
| Q-001 | Medium | Bug | `KelasModel.php` | `useSoftDeletes=false` padahal kolom `deleted_at` ada | Aktifkan soft delete | Fixed |
| Q-002 | High | Testing | Scheduling | HC check hanya private di test | `ScheduleIntegrityValidator` + unit tests | Fixed |
| Q-003 | Medium | UX | Generate | Error gagal kurang actionable | Pre-flight sudah ada di ScheduleController::index; pesan job gagal dari worker | Partial |
| Q-004 | Medium | Code | Controllers | Beberapa fat controller (Schedule) | Deferred refactor; logic job dipisah ke ScheduleJobService | Partial |
| Q-005 | Low | A11y | Timetable | Aria/label belum lengkap | Deferred Later | Deferred |
| Q-006 | Info | Tooling | Repo | Tidak ada PHPStan/CI | CI GitHub Actions + phpstan.neon level 5 | Fixed |
| Q-007 | Medium | Bug | Seeder | Omit `app_settings`/`guru_preferensi` | Legacy seeder ditandai; installer empty template | Fixed (productization) |
| Q-008 | Low | UX | password123 di UI | Teks default password | Diganti teks password acak | Fixed F2 |

## Testing

- Existing: `SchedulingEngineTest` (CSP+GA, assertHardConstraints), `JadwalPlacementValidatorTest`
- Baru: `ScheduleIntegrityValidatorTest` (HC-1 clash, valid set, spreadsheet sanitize)
- Target ≥70% scheduling libraries: diukur di CI coverage Later; suite engine sudah luas

## CI

`.github/workflows/ci.yml` — PHPUnit matrix PHP 8.2/8.3 + MySQL 8.  
Ditambah langkah `composer audit` (allow soft-fail di laporan final jika advisory).

## UX rekomendasi (roadmap)

| Prioritas | Fitur |
|-----------|-------|
| Now | Progress generate async (sudah F3), password acak (F2), installer (F5) |
| Next | Drag-drop edit, conflict highlight, import Excel massal |
| Later | Dark mode, email publish, 2FA, undo/redo |

## Verifikasi

```bash
vendor/bin/phpunit --filter ScheduleIntegrityValidatorTest
vendor/bin/phpunit --filter SchedulingEngineTest
```
