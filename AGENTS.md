<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **smart-school-scheduling** (1779 symbols, 3311 relationships, 142 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> Index stale? Run `node .gitnexus/run.cjs analyze` from the project root — it auto-selects an available runner. No `.gitnexus/run.cjs` yet? `npx gitnexus analyze` (npm 11 crash → `npm i -g gitnexus`; #1939).

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows. For regression review, compare against the default branch: `detect_changes({scope: "compare", base_ref: "main"})`.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `context({name: "symbolName"})`.

## Never Do

- NEVER edit a function, class, or method without first running `impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `rename` which understands the call graph.
- NEVER commit changes without running `detect_changes()` to check affected scope.

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/smart-school-scheduling/context` | Codebase overview, check index freshness |
| `gitnexus://repo/smart-school-scheduling/clusters` | All functional areas |
| `gitnexus://repo/smart-school-scheduling/processes` | All execution flows |
| `gitnexus://repo/smart-school-scheduling/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->

---

# Smart School Scheduling (S3) — Project Rules

> **PRD Reference**: `docs/PRD.md` (v3.2) — Always consult the PRD before making architectural decisions.

## Project Overview

Aplikasi dashboard sekolah SMK untuk auto-generate jadwal mata pelajaran menggunakan **CSP + GA** hybrid algorithm. Dibangun dengan **CodeIgniter 4.7**, **PHP 8.2+**, dan **MySQL/MariaDB**.

**Roles**: `guru`, `kurikulum`, `kepala_sekolah` — tidak ada role admin/murid.

## Tech Stack & Conventions

### Framework Rules
- **CodeIgniter 4.7** — Follow CI4 coding standards strictly
- Use **CI4 Query Builder** for all database queries (never raw SQL)
- Use **CI4 Validation Library** for all server-side input validation
- Use **CI4 Filters** for route protection (`AuthFilter`, `KurikulumFilter`, `GuruFilter`, `KepalaSekolahFilter`)
- Use **CI4 native session** for authentication via tabel `users` (no external auth library)
- Use `password_hash()` / `password_verify()` (bcrypt) for passwords
- Use **CI4 Migrations** for all database schema changes
- Use **CI4 Seeders** for test/default data
- Views use **native PHP templates** (no Blade, no Twig)
- Frontend uses **Bootstrap 5** (CDN), **Bootstrap Icons**, **Inter font** (Google Fonts), **Chart.js**, **DataTables.js**

### Naming Conventions
- **Controllers**: PascalCase, suffixed with `Controller` → `GuruController.php`
- **Models**: PascalCase, suffixed with `Model` → `GuruModel.php`
- **Database tables**: snake_case, singular → `guru`, `mapel`, `tahun_ajaran`
- **Database columns**: snake_case → `max_jam_per_minggu`, `must_change_password`
- **Views**: snake_case, organized by module → `kurikulum/guru/index.php`
- **Libraries/Services**: PascalCase → `ScheduleGenerator.php`, `LaporanGuruJamExporter.php`
- **Routes**: kebab-case URLs → `/kurikulum/tahun-ajaran`, `/kepala-sekolah/laporan/guru-jam`

### Folder Structure
```
app/Controllers/Kurikulum/   → Kurikulum CRUD + schedule generation
app/Controllers/Guru/        → Guru dashboard + own jadwal
app/Controllers/KepalaSekolah/ → Read-only jadwal + laporan jam mengajar
app/Libraries/             → ScheduleGenerator, CSPEngine, GAEngine, PdfExporter, LaporanGuruJamExporter
app/Filters/               → Auth & role filters
app/Views/layouts/         → Main layout template
app/Views/auth/            → Login + change password
app/Views/dashboard/       → Role-specific dashboards
app/Views/kurikulum/       → Kurikulum module views
app/Views/guru/            → Guru module views
app/Views/kepala_sekolah/  → Kepala Sekolah module views
app/Views/components/      → Shared partials (timetable.php)
```

## Database Rules

### 16 Tables (v3.2)
`users`, `guru`, `guru_mapel`, `guru_hari_blokir`, `guru_preferensi`, `tahun_ajaran`, `jurusan`, `ruangan`, `kelas`, `kelas_mapel`, `mapel`, `timeslot`, `hari`, `jadwal`, `schedule_config`, `schedule_logs`

### Critical Constraints
- Master data tables use **soft delete** (`deleted_at`) — EXCEPT `timeslot` and `hari`
- Auth via `users` table; `guru` is optional teaching profile linked by `user_id`
- `kelas_mapel` defines weekly JP demand per class (replaces `pengajaran`)
- `guru_mapel` defines teacher capacity per subject (HC-6)
- `guru_hari_blokir` blocks teacher on specific days (HC-4)
- `jadwal` uses `kelas_mapel_id` and has 3 unique conflict keys (kelas, guru, ruangan per slot)

## Scheduling Algorithm Rules (CRITICAL)

### Hard Constraints HC-1–HC-8 (v3.1)
| Code | Rule |
|------|------|
| HC-1 | No teacher conflict (same slot) |
| HC-2 | No class conflict (same slot) |
| HC-3 | No lab conflict (homerooms unique per class) |
| HC-4 | Teacher availability — no schedule on `guru_hari_blokir` days |
| HC-5 | Weekly JP per `kelas_mapel` met (relaxed: trailing/mid empty slots OK) |
| HC-6 | Guru–mapel eligibility from `guru_mapel` + weekly cap `max_jam_per_minggu` |
| HC-7 | Room/jurusan match — kejuruan mapel → matching kelas; homeroom unless `kelas_mapel.butuh_lab = 1` → lab dari **pool jurusan** (`ruangan.jurusan_id`); `kelas_mapel.lab_id` = lab utama/preferensi; **satu lab per (kelas_mapel, hari)**; antar hari boleh lab berbeda |
| HC-8 | School hours — placement only in `timeslot.tipe = 'jp'` slots |

> **Dropped in v3.0**: old "no empty slots mid-day" and "teacher max JP/day" hard rules — now handled as soft constraints (SC-1/SC-2 gaps, SC-6 load balance). This is the key change enabling the solver to always complete a full week.

> **Dropped in v3.1**: practical block rule (old HC-8) — same mapel may span istirahat/kegiatan_khusus; `blok_group` remains for timetable UI merge only.

### Soft Constraints SC-1–SC-11 (GA only, weight scale 1–10)
| Code | Weight | Rule |
|------|--------|------|
| SC-1 | 9 | Minimize teacher gaps (empty slots between teaching) |
| SC-2 | 9 | Minimize student (class) gaps mid-day |
| SC-3 | 7 | Spread each mapel across the week |
| SC-4 | 6 | High `bobot_kognitif` mapel in morning slots |
| SC-5 | 5 | Low `bobot_kognitif` mapel in afternoon slots |
| SC-6 | 7 | Balance guru JP load across days |
| SC-7 | 5 | Teacher day/time preference via `guru_preferensi` (Guru UI + GA penalty) |
| SC-8 | 5 | Minimize room transitions (lab moves) |
| SC-9 | 4 | Teacher continuity per class — auto-satisfied (penalty 0) |
| SC-10 | 3 | Rotate first-slot mapel across days |
| SC-11 | 6 | Balance lab usage across jurusan |

> GA config juga memuat `sc_lab_preference` (default 5) — penalti jika lab aktual ≠ `kelas_mapel.lab_id` (lab utama).

Fitness: `1 / (1 + Σ(Wi × Penalty_i_normalized))`, each penalty normalized to 0–1.

### Algorithm Architecture
- `ScheduleGenerator.php` — Orchestrator (CSP → GA pipeline)
- `CSPEngine.php` — Initial valid solution (AC-3 + backtracking + forward checking, MRV/LCV, min-conflict repair)
- `GAEngine.php` — Optimize SC-1..SC-11 (tournament k=5, OX crossover, elitism, adaptive mutation, HC repair)
- `SchedulingContext.php` — Shared helpers (JP slots per hari, guru pool, eligibility, break detection)
- `JadwalPlacementValidator.php` — HC-1..HC-8 validation for manual placement & swap
- `JadwalManualService.php` — Post-generate manual place/delete/swap jadwal rows (Kurikulum tab Kelas)
- `ScheduleHistoryService.php` / `HistoryRepairEngine.php` — Multi-history generate & publish
- Input: `kelas_mapel` units + auto guru assignment from `guru_mapel`; `mapel.bobot_kognitif` for SC-4/SC-5

## Authentication & Authorization

### Login Flow
1. User enters **email** + password
2. System checks `users` table (`email`, `is_active = 1`)
3. Verify password with `password_verify()`
4. Set session: `user_id`, `role`, `nama`, `guru_id` (nullable)
5. Redirect by role; enforce `must_change_password` if set

### Route Protection
- `/kurikulum/*` → `KurikulumFilter` (`role === 'kurikulum'`)
- `/guru/*` → `GuruFilter` (`role === 'guru'` OR kurikulum with `guru_id`)
- `/kepala-sekolah/*` → `KepalaSekolahFilter` (`role === 'kepala_sekolah'`)
- `/auth/*` → Public
- All other protected routes → `AuthFilter`

### Kepala Sekolah Module
- `GET /kepala-sekolah/jadwal` — view schedules by kelas/guru/ruangan (read-only, published log only)
- `GET /kepala-sekolah/laporan/guru-jam` — JP report from generated `jadwal` + PDF/Excel export

### Guru Module (beyond jadwal)
- `GET /guru/preferensi` — atur preferensi/hindari hari-slot (SC-7)

## Export
- **PDF/Excel**: Jadwal per kelas/guru/ruangan (Kurikulum, Kepala Sekolah, Guru own)
- **PDF/Excel**: Laporan jam mengajar guru (Kepala Sekolah only) via `LaporanGuruJamExporter`

## Code Quality Rules
- Always add `@return` type hints to controller methods
- Always validate input on both client-side (JS) and server-side (CI4 Validation)
- Use CI4 `esc()` on ALL view outputs without exception
- Write descriptive commit messages in Indonesian or English
- Keep controllers thin — business logic goes in Models or Libraries
- Use CI4 `Services` pattern for dependency injection where appropriate
