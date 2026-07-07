---
name: ui-layout
description: Build base UI layout and dashboards for S3 v2.0 — sidebar for Kurikulum, Guru, and Kepala Sekolah roles, dual-role Kurikulum+mengajar menu, Bootstrap 5, Inter font, role-specific dashboards. No murid or admin role.
---

# UI Layout & Design System (v2.0)

> **PRD Reference**: `docs/PRD.md` Section 8, Section 7.3

## Design System

Same CDN stack as v1.1: Bootstrap 5.3, Bootstrap Icons, Inter, Chart.js, DataTables.

CSS variables in `public/css/app.css`: `--s3-primary`, sidebar dark theme, card shadows, responsive sidebar collapse < 992px.

## Layout (`app/Views/layouts/main.php`)

```
Sidebar (role-based nav) | Header (breadcrumb, user, logout)
                         | <?= $this->renderSection('content') ?>
```

Session display: `nama`, role badge (`Kurikulum` / `Guru` / `Kepala Sekolah`).

## Sidebar Navigation

### Kurikulum (default)
- Dashboard
- **Manajemen User**
- Master Data: Tahun Ajaran, Jurusan, Ruangan, Guru, Kelas, Mata Pelajaran, Timeslot
- Penjadwalan: Generate, Lihat Jadwal, Parameter, Log
- Profil

### Kurikulum + mengajar (`session('guru_id')` not null)
Add section **Jadwal Saya** → `/guru/jadwal` (or embedded link in sidebar)

### Guru
- Dashboard
- Jadwal Mengajar
- Profil

### Kepala Sekolah
- Dashboard
- Lihat Jadwal (kelas / guru / ruangan)
- Laporan Jam Mengajar
- Profil

## Dashboards

### Kurikulum (`dashboard/kurikulum.php`)
- Stats: Total User, Guru (with profile), Kelas, Mapel
- Schedule status + fitness score
- Quick actions: Generate, Master Data
- Recent `schedule_logs`
- If `guru_id`: mini "Jadwal hari ini" widget

### Guru (`dashboard/guru.php`)
- Jadwal hari ini
- Total JP minggu ini (from `jadwal` count)
- Weekly timetable preview

### Kepala Sekolah (`dashboard/kepala_sekolah.php`)
- Status jadwal sekolah
- Quick links: Lihat per kelas, Laporan guru-jam
- Optional chart: JP distribution

## DashboardController

```php
public function kurikulum(): string
public function guru(): string       // uses session('guru_id')
public function kepalaSekolah(): string
```

## Removed

- `dashboard/admin.php`, `dashboard/murid.php`
- Sidebar items: Murid, Pengajaran, Admin
- Stats: Total Murid

## Auth Pages

- `auth/login.php` — NIP + password
- `auth/change_password.php` — minimal layout when `must_change_password`

## Testing Checklist

- [ ] Correct sidebar per role
- [ ] Kurikulum dual-role shows Jadwal Saya
- [ ] Dashboards load real DB stats
- [ ] Mobile sidebar collapse
- [ ] Active nav highlight
- [ ] Logout works from header
