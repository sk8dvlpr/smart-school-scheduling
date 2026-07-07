---
name: master-data-crud
description: Build Kurikulum CRUD modules for S3 v2.0 — User, Tahun Ajaran, Jurusan, Ruangan, Guru (with nested guru_mapel and guru_hari_blokir), Kelas (with nested kelas_mapel), Mapel, Timeslot per hari. CI4 RESTful patterns, DataTables, validation, soft delete, CSRF. No murid or pengajaran modules.
---

# Master Data CRUD Modules (v2.0 — Kurikulum)

> **PRD Reference**: `docs/PRD.md` Section 7.4, Section 9.3

## Overview

Build CRUD under `app/Controllers/Kurikulum/`. Namespace changed from `Admin\`. **Removed**: Murid, Pengajaran. **Added**: User, nested kelas_mapel, guru_mapel, guru_hari_blokir.

## CRUD Pattern

### Controller (`app/Controllers/Kurikulum/{Name}Controller.php`)

```php
namespace App\Controllers\Kurikulum;

public function index(): string
public function create()
public function show(int $id)
public function update(int $id)
public function delete(int $id)
```

### Model
- Soft deletes except `timeslot`, `hari`, `guru_hari_blokir`
- Explicit `$allowedFields`, CI4 Validation rules

### Views (`app/Views/kurikulum/{module}/index.php`)
- Extends `layouts/main`
- DataTables (Indonesian locale)
- Modal create/edit, flash messages, CSRF

## Module Specifications

### 1. User (`UserController`)
- **Table**: `users`
- **Fields**: nip, nama, email, no_telp, password (create only), role (dropdown: guru/kurikulum/kepala_sekolah), is_active
- **Actions**: CRUD + `resetPassword($id)` button
- **Note**: Creating teaching user may also create linked `guru` record (optional checkbox "Juga mengajar" → creates guru + redirect to guru_mapel setup)

### 2. Tahun Ajaran
- Same as v1.1; only one `is_active = 1`

### 3. Jurusan
- Same as v1.1; delete guard if kelas/mapel linked

### 4. Ruangan
- Same as v1.1; `jurusan_id` when `tipe = lab`

### 5. Guru (`GuruController` + nested)
- **Create flow**: Select existing `users` with role guru/kurikulum OR create user inline
- **Guru table fields**: `user_id`, `max_jam_per_hari`
- Display nama/nip from joined `users`
- **Nested `GuruMapelController`** (`/kurikulum/guru/{id}/mapel`):
  - Fields: `mapel_id`, `max_jam_per_minggu`
  - UNIQUE (guru_id, mapel_id)
- **Nested `GuruHariBlokirController`** (`/kurikulum/guru/{id}/hari-blokir`):
  - UI: 5 checkboxes (Sen–Jum) for blocked days
  - POST replaces all blocks for guru (sync pattern)
  - Empty = available all days

### 6. Kelas (`KelasController` + nested)
- Same fields as v1.1
- **Nested `KelasMapelController`** (`/kurikulum/kelas/{id}/mapel`):
  - Fields: `mapel_id`, `jam_per_minggu`, `butuh_lab`, `lab_id` (conditional)
  - UNIQUE (kelas_id, mapel_id, tahun_ajaran_id)
  - **NO guru_id, NO durasi_blok**
  - Show running total JP vs 48 weekly cap
  - Validate kejuruan mapel matches kelas jurusan

### 7. Mapel
- Same as v1.1 (umum/kejuruan rules, warna picker)

### 8. Timeslot (`TimeslotController`) — **Per hari**
- **UI**: Tab or dropdown per hari (Sen–Jum)
- **Fields**: `hari_id`, `jam_ke`, `waktu_mulai`, `waktu_selesai`, `tipe` (jp/istirahat/kegiatan_khusus), `keterangan`
- `jam_ke` resets per hari (1..N)
- Hard delete allowed
- Seed reference: PRD §2.2 / `docs/jam_sekolah.jpeg`
- Validate: only `tipe=jp` rows count toward scheduling capacity

## Removed Modules

- ~~Murid~~ — deleted in v2.0
- ~~Pengajaran~~ — replaced by `kelas_mapel` + algorithm guru assignment

## DataTables

```javascript
$('#dataTable').DataTable({
    language: { url: '//cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' },
    responsive: true
});
```

## Testing Checklist (per module)

- [ ] List, create, edit, delete work
- [ ] FK dropdowns load correct data
- [ ] Conditional fields (lab, jurusan on mapel)
- [ ] Nested kelas_mapel: JP total ≤ 48
- [ ] Nested guru_mapel: cap per mapel saved
- [ ] Nested guru_hari_blokir: blocks persist
- [ ] Timeslot CRUD per hari with correct tipe
- [ ] User reset password triggers must_change_password
- [ ] CSRF + esc() on all forms
