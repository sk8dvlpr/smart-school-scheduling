---
name: database-setup
description: Create all 15 database migrations and seeders for Smart School Scheduling (S3) v2.0. Covers users, guru, guru_mapel, guru_hari_blokir, tahun_ajaran, jurusan, ruangan, kelas, kelas_mapel, mapel, timeslot (per hari), hari, jadwal, schedule_config, and schedule_logs. Includes FK constraints, unique constraints, and seed data aligned with PRD v2.0.
---

# Database Setup — Migrations & Seeders (v2.0)

> **PRD Reference**: `docs/PRD.md` Section 5 (Database Design), Section 2.1–2.2 (Timeslot per hari)

## Overview

Create **15 tables** via CI4 migrations and populate realistic seed data for a 48-class SMK school. **Removed from v1.1**: `murid`, `pengajaran`.

## Prerequisites

- CodeIgniter 4.7 project set up
- Database configured in `.env`
- PHP 8.2+ with `mysqlnd`

## Migration Order (FK Dependencies)

```
1.  CreateHariTable              → No FK
2.  CreateTahunAjaranTable       → No FK
3.  CreateJurusanTable           → No FK
4.  CreateUsersTable             → No FK
5.  CreateRuanganTable           → FK: jurusan
6.  CreateGuruTable              → FK: users
7.  CreateMapelTable             → FK: jurusan
8.  CreateKelasTable             → FK: jurusan, ruangan, tahun_ajaran
9.  CreateTimeslotTable          → FK: hari
10. CreateGuruMapelTable         → FK: guru, mapel
11. CreateGuruHariBlokirTable    → FK: guru, hari
12. CreateKelasMapelTable        → FK: kelas, mapel, tahun_ajaran, ruangan(lab)
13. CreateJadwalTable            → FK: tahun_ajaran, kelas_mapel, hari, timeslot, kelas, guru, mapel, ruangan
14. CreateScheduleConfigTable    → FK: tahun_ajaran
15. CreateScheduleLogsTable      → FK: tahun_ajaran, users
```

For v1 → v2 migration on existing DB, use separate alter migrations instead of fresh create.

## Table Specifications

### 1. `hari`
- Fields: `id`, `nama`, `kode` (UNIQUE), `urutan`
- NO soft delete, NO timestamps

### 2. `tahun_ajaran`
- Fields: `id`, `nama`, `semester` (ENUM ganjil/genap), `is_active`, `tanggal_mulai`, `tanggal_selesai`, timestamps, `deleted_at`
- Only 1 `is_active = 1` at a time (enforce in Model)

### 3. `jurusan`
- Fields: `id`, `kode` (UNIQUE), `nama`, timestamps, `deleted_at`

### 4. `users` — Auth for all roles
```php
// nip(VARCHAR 30 UNIQUE), nama, email(NULL), no_telp(NULL), password,
// role ENUM('guru','kurikulum','kepala_sekolah'),
// must_change_password TINYINT default 0, is_active TINYINT default 1,
// created_at, updated_at, deleted_at
```

### 5. `ruangan`
- Fields: `id`, `kode` (UNIQUE), `nama`, `tipe` (ENUM kelas/lab), `kapasitas`, `jurusan_id` (NULL FK), timestamps, `deleted_at`

### 6. `guru` — Teaching profile (optional per user)
```php
// id, user_id(INT UNIQUE FK → users), max_jam_per_hari(INT default 8),
// created_at, updated_at, deleted_at
// NO nip/nama/password here — those live on users
```

### 7. `mapel`
- Fields: `id`, `kode` (UNIQUE), `nama`, `tipe` (umum/kejuruan), `jurusan_id` (NULL FK), `warna`, timestamps, `deleted_at`
- Rule: umum → jurusan_id NULL; kejuruan → jurusan_id required

### 8. `kelas`
- Fields: `id`, `nama`, `tingkat` (X/XI/XII), `jurusan_id`, `ruangan_id`, `tahun_ajaran_id`, timestamps, `deleted_at`
- UNIQUE: (`nama`, `tahun_ajaran_id`)

### 9. `timeslot` — Per-day slots
```php
// id, hari_id(FK), jam_ke(INT per hari), waktu_mulai(TIME), waktu_selesai(TIME),
// tipe ENUM('jp','istirahat','kegiatan_khusus'), keterangan(VARCHAR 100 NULL),
// created_at, updated_at
// NO soft delete. Only tipe='jp' is schedulable.
```

### 10. `guru_mapel`
- Fields: `id`, `guru_id`, `mapel_id`, `max_jam_per_minggu`, timestamps, `deleted_at`
- UNIQUE: (`guru_id`, `mapel_id`)

### 11. `guru_hari_blokir`
- Fields: `id`, `guru_id`, `hari_id`, `created_at`, `updated_at`
- UNIQUE: (`guru_id`, `hari_id`)
- NO soft delete (hard delete on unblock)

### 12. `kelas_mapel` — Class curriculum (replaces pengajaran)
```php
// id, kelas_id, mapel_id, tahun_ajaran_id, jam_per_minggu(INT),
// butuh_lab(TINYINT default 0), lab_id(NULL FK → ruangan),
// created_at, updated_at, deleted_at
// UNIQUE: (kelas_id, mapel_id, tahun_ajaran_id)
// NO guru_id, NO durasi_blok
```

### 13. `jadwal`
```php
// id, tahun_ajaran_id, kelas_mapel_id(FK), hari_id, timeslot_id,
// kelas_id, guru_id, mapel_id, ruangan_id, blok_group(VARCHAR 36 NULL),
// created_at, updated_at
// 3 UNIQUE constraints (HC1-HC3):
//   (hari_id, timeslot_id, kelas_id, tahun_ajaran_id)
//   (hari_id, timeslot_id, guru_id, tahun_ajaran_id)
//   (hari_id, timeslot_id, ruangan_id, tahun_ajaran_id)
```

### 14. `schedule_config`
- Fields: `id`, `tahun_ajaran_id`, `param_key`, `param_value`, `description`, timestamps

Default keys: `population_size`, `max_generations`, `crossover_rate`, `mutation_rate`, `fitness_threshold`, `max_guru_jam_per_hari`, `timeout_seconds`, `default_password`

### 15. `schedule_logs`
- Fields: `id`, `tahun_ajaran_id`, `status` (ENUM running/completed/failed/partial), `fitness_score`, `generations_run`, `total_conflicts`, `execution_time`, `error_message`, `generated_by` (FK → **users.id**), `started_at`, `completed_at`, `created_at`

## Timeslot Seeder (per `docs/jam_sekolah.jpeg`)

Weekly capacity per class: **48 JP** (10+11+10+11+6).

| Hari | JP slots | Kegiatan khusus |
|------|----------|-----------------|
| Senin | 10 | 06:45–07:25 Upacara |
| Selasa | 11 | — |
| Rabu | 10 | 06:45–07:25 Pembiasaan Baik |
| Kamis | 11 | — |
| Jumat | 6 | — |

Seed exact times from PRD §2.2. Durasi per JP varies (30–50 min).

## Other Seeders

### HariSeeder
`Senin(SEN,1)` … `Jumat(JUM,5)`

### UsersSeeder
- Kurikulum users (some with guru profile for dual-role testing)
- Guru users (each with `guru` record)
- Kepala Sekolah user(s)
- Default password: `password123` (hashed)

### GuruSeeder
- Create `guru` rows linked to teaching `users` via `user_id`
- Set `max_jam_per_hari` (default 8)

### GuruMapelSeeder
- Assign mapel + `max_jam_per_minggu` per guru
- Multiple MTK teachers with split capacity

### GuruHariBlokirSeeder (optional test data)
- 1–2 gurus blocked on specific days for HC13 testing

### KelasMapelSeeder
- Per class: list mapel + `jam_per_minggu` + lab flags
- Σ JP per class ≤ 48
- NO guru assignment here

### Jurusan, Ruangan, Kelas, Mapel, TahunAjaran
Same as v1.1 patterns but aligned with v2 schema.

## Running

```bash
php spark migrate
php spark db:seed DatabaseSeeder
```

## Validation Checklist

- [ ] 15 tables exist; `murid` and `pengajaran` absent
- [ ] All FK relationships valid
- [ ] Each class has unique homeroom
- [ ] Σ `kelas_mapel.jam_per_minggu` per kelas ≤ 48
- [ ] Timeslots seeded per hari with correct `tipe`
- [ ] Only 1 active `tahun_ajaran`
- [ ] `users.role` enum correct; login via NIP
- [ ] `jadwal.kelas_mapel_id` FK works
- [ ] `schedule_logs.generated_by` → `users.id`
