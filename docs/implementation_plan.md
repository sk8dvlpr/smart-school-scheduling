# Maksimalkan Algoritma Penjadwalan — Zero Conflict

## Problem

Algoritma CSP saat ini sering menghasilkan **unplaced units** (unit JP yang tidak terjadwal) karena beberapa keterbatasan dalam strategi pencarian solusi. Ini menyebabkan jadwal "partial" dengan conflict/lubang yang harus diperbaiki manual.

## Root Cause Analysis

Setelah analisis mendalam pada [CSPEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/CSPEngine.php), [GAEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/GAEngine.php), dan [ScheduleGenerator.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/ScheduleGenerator.php), berikut bottleneck utama:

| # | Bottleneck | Impact |
|---|-----------|--------|
| 1 | **Backtracking budget terlalu kecil** (`max(2000, count * 40)`) | Solver menyerah terlalu cepat per kelas, langsung fallback ke greedy yang tidak optimal |
| 2 | **Candidate limit 12** per unit | Terlalu sedikit pilihan, banyak feasible slot yang tidak pernah dicoba |
| 3 | **Repair sweep hanya 1 pass greedy** | Tidak ada mekanisme untuk menggeser placement yang sudah ada demi memberi ruang unit yang stuck |
| 4 | **Class order pada attempt > 1 = shuffle** | Tidak cukup informatif; seharusnya kelas yang gagal di attempt sebelumnya diprioritaskan |
| 5 | **Guru lock (SC-9) terlalu agresif** | Satu kali assign langsung lock guru per kelas_mapel, padahal guru lain bisa lebih fit |
| 6 | **Tidak ada min-conflict repair** | Setelah CSP gagal partial, tidak ada strategi untuk "evict & re-place" unit yang blocking |

## Proposed Changes

### CSPEngine.php — Penguatan Solver

#### 1. Naikkan Backtracking Budget (Impact: HIGH)
- Budget per kelas dari `max(2000, n*40)` → `max(8000, n*150)`
- Memberikan solver ruang lebih untuk backtrack dan menemukan solusi valid

#### 2. Naikkan Candidate Limit (Impact: MEDIUM)
- Dari `12` → `24` untuk backtracking, repair sweep tetap ambil `firstCandidate`
- Lebih banyak variasi slot yang dicoba

#### 3. Multi-Pass Repair Sweep dengan Min-Conflict (Impact: HIGH)
- Repair sweep saat ini hanya 1x pass greedy
- Tambahkan **3 pass** repair:
  - **Pass 1**: Greedy (sama seperti sekarang)
  - **Pass 2**: Evict-and-reinsert — untuk setiap unit yang masih stuck, coba "usir" unit yang menempati slot terbaik, lalu re-place unit yang diusir ke slot lain
  - **Pass 3**: Pairwise swap — coba tukar placement 2 unit untuk membuka slot baru

#### 4. Smart Class Re-ordering pada Retry (Impact: MEDIUM)
- Attempt > 1: kelas yang punya unplaced units di attempt sebelumnya diprioritaskan (bukan random shuffle)
- Ini memastikan kelas yang "sulit" dicoba lebih dulu saat state masih bersih

#### 5. Delayed Guru Lock (Impact: MEDIUM)
- Jangan lock guru ke kelas_mapel langsung di CSP
- Biarkan CSP lebih fleksibel memilih guru; guru lock diterapkan di GA phase saja (SC-9)
- Tambahkan flag `delayGuruLock` yang diaktifkan mulai attempt ke-2

### ScheduleGenerator.php — Naikkan Default Attempts

#### 6. Default `csp_max_attempts` dari 8 → 12 (Impact: LOW)
- Lebih banyak percobaan = peluang zero conflict lebih tinggi

> [!IMPORTANT]
> Semua perubahan ini **hanya memperkuat CSP** untuk menemukan feasible solution (HC-1..HC-8). GA phase (SC-1..SC-12) tidak diubah karena GA hanya mengoptimasi soft constraint — bukan penyebab conflict.

## Open Questions

> [!NOTE]
> **Pertanyaan mengenai performance:** Perubahan ini akan membuat proses CSP lebih lama (estimasi 2-4x lipat dari waktu saat ini). Mengingat Anda sudah mengalami timeout di hosting (~2 menit), apakah Anda berencana generate jadwal dari **lokal** atau sudah ada solusi timeout di hosting?

## Files Modified

### CSPEngine

#### [MODIFY] [CSPEngine.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/CSPEngine.php)
- Naikkan backtracking budget → `max(8000, n*150)`
- Naikkan candidate limit → `24`
- Tambah multi-pass repair: `repairSweepAdvanced()` dengan evict-and-reinsert + pairwise swap
- Smart class re-ordering: prioritaskan kelas gagal pada retry
- Delayed guru lock: flag `delayGuruLock` mulai attempt ke-2

---

### ScheduleGenerator

#### [MODIFY] [ScheduleGenerator.php](file:///d:/Programming%20Area/Projects/smart-school-scheduling/app/Libraries/ScheduleGenerator.php)
- Default `csp_max_attempts` dari `8` → `12`

## Verification Plan

### Manual Verification
- Generate jadwal dari lokal (`php spark serve`) lalu bandingkan:
  - Jumlah unplaced units sebelum vs sesudah
  - Status `completed` vs `partial`
  - Waktu eksekusi (diperkirakan naik 2-4x)
