# Audit Fase 3 — Laporan Performa (Background Generate)

> Tanggal: 2026-09-29  
> Scope: Orkestrasi generate jadwal (bukan perubahan HC/SC di CSPEngine/GAEngine)

## Perubahan arsitektur: sinkron → async

| Sebelum | Sesudah |
|---------|---------|
| `POST kurikulum/schedule/generate` memanggil `ScheduleGenerator::generate()` inline (request HTTP menahan sampai selesai) | Endpoint enqueue job ke tabel `schedule_jobs`, respons JSON cepat `{ job_id }` |
| Risiko timeout web server / double-submit race | Satu job `queued`/`running` per tahun ajaran aktif (mutex di `ScheduleJobService::enqueue`) |
| Progress UI simulasi timer | Poll `GET kurikulum/schedule/job/{id}` setiap 2s: `progress`, `generation`, `best_fitness` |

Worker CLI: `php spark schedule:worker` (loop `claimNext` → `ScheduleGenerator::generate`).  
Fallback shared hosting: `POST kurikulum/schedule/job/{id}/tick` memproses satu job milik sesi (tanpa daemon).

## Penyimpanan jadwal

`ScheduleGenerator` sudah memakai `JadwalModel::insertBatch()` per chunk 100 baris setelah GA — tidak diubah di Fase 3. Bottleneck utama tetap CSP + GA CPU-bound, bukan INSERT row-by-row.

## Rekomendasi lanjutan

1. **Jalankan worker sebagai proses terpisah** (supervisor/systemd/cron `--once`) di production; jangan andalkan `tick` kecuali hosting tanpa CLI.
2. **Callback progress GA** (opsional F4): hook di `GAEngine::optimize` untuk `updateProgress` tanpa mengubah logika fitness/constraint.
3. **Indeks & retention**: pertimbangkan purge `schedule_jobs` completed > N hari; index `(status, tahun_ajaran_id)` sudah ada di migrasi.
4. **Benchmark**: `php spark benchmark:generate --classes=N --seed=S` untuk stub timing; ukur riil via `schedule_logs.execution_time` setelah generate.
5. **Timeout PHP worker**: set `set_time_limit(0)` di worker/tick (sudah di tick controller); pastikan `csp_timeout_seconds` / `ga_timeout_seconds` selaras dengan SLA sekolah.

## Dampak keamanan (terkait SEC-003)

Generate lock per TA dipusatkan di antrian job — selaras temuan audit Fase 2 (CSRF tetap wajib pada POST generate/cancel/tick).
