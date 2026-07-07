<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-book me-2"></i>Panduan Parameter Algoritma</h5>
        <p class="small text-muted mb-0 mt-1">Keterangan fungsi setiap parameter dan dampak jika nilainya dinaikkan atau diturunkan.</p>
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="paramGuideAccordion">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#guideCsp">
                        Parameter CSP (Fase 1)
                    </button>
                </h2>
                <div id="guideCsp" class="accordion-collapse collapse show" data-bs-parent="#paramGuideAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:18%">Parameter</th>
                                        <th style="width:28%">Fungsi</th>
                                        <th style="width:27%">Jika dinaikkan / diperketat</th>
                                        <th style="width:27%">Jika diturunkan / dilonggarkan</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <tr>
                                        <td class="fw-medium">Metode Konsistensi</td>
                                        <td>Menyaring domain slot/guru yang tidak valid sebelum penempatan (arc consistency).</td>
                                        <td><strong>AC-3:</strong> Lebih sedikit backtracking, solusi awal lebih rapi; sedikit lebih lambat di awal.</td>
                                        <td><strong>None:</strong> CSP start lebih cepat tetapi lebih banyak trial-error dan risiko gagal tempat.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Variable Ordering</td>
                                        <td>Menentukan unit JP mana yang ditempatkan lebih dulu.</td>
                                        <td><strong>MRV:</strong> Unit sulit ditempatkan lebih awal → lebih banyak unit terisi; waktu CSP bisa naik.</td>
                                        <td><strong>Degree:</strong> Lebih cepat tetapi unit sulit bisa tertunda (risiko partial).</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Value Ordering</td>
                                        <td>Memilih slot/guru paling aman untuk unit yang sedang ditempatkan.</td>
                                        <td><strong>LCV:</strong> Konflik HC lebih sedikit, jadwal awal lebih stabil.</td>
                                        <td><strong>None:</strong> Penempatan lebih acak → lebih cepat per langkah, lebih sering backtrack.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Repair Strategy</td>
                                        <td>Strategi perbaikan saat penempatan macet.</td>
                                        <td><strong>Min-Conflict:</strong> Lebih banyak unit berhasil ditempatkan saat bentrok.</td>
                                        <td><strong>None:</strong> Gagal lebih cepat; hanya cocok untuk data sangat longgar.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">CSP Max Attempts</td>
                                        <td>Berapa kali CSP diulang dengan urutan variabel berbeda jika ada unit gagal.</td>
                                        <td>Lebih banyak peluang solusi lengkap; waktu generate naik signifikan.</td>
                                        <td>Generate lebih cepat tetapi lebih sering berstatus <em>partial</em>.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guideGa">
                        Parameter Genetic Algorithm (Fase 2)
                    </button>
                </h2>
                <div id="guideGa" class="accordion-collapse collapse" data-bs-parent="#paramGuideAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:18%">Parameter</th>
                                        <th style="width:28%">Fungsi</th>
                                        <th style="width:27%">Jika dinaikkan</th>
                                        <th style="width:27%">Jika diturunkan</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <tr>
                                        <td class="fw-medium">Population Size</td>
                                        <td>Jumlah kromosom (kandidat jadwal) per generasi.</td>
                                        <td>Eksplorasi lebih luas, fitness lebih baik; RAM dan waktu naik.</td>
                                        <td>Generate lebih cepat; risiko terjebak di solusi mediocre.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Max Generations</td>
                                        <td>Batas maksimum iterasi evolusi GA.</td>
                                        <td>Optimasi SC lebih baik; waktu proses lebih lama.</td>
                                        <td>Selesai lebih cepat; gap/distribusi bisa lebih buruk.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Tournament Size (k)</td>
                                        <td>Jumlah induk yang bersaing saat seleksi.</td>
                                        <td>Seleksi lebih ketat, konvergensi lebih cepat; diversitas turun.</td>
                                        <td>Lebih banyak variasi; konvergensi lambat.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Crossover Rate</td>
                                        <td>Peluang dua induk dikombinasikan.</td>
                                        <td>Lebih banyak eksplorasi; bisa mengganggu solusi bagus jika terlalu tinggi.</td>
                                        <td>Populasi lebih stabil; kombinasi baru berkurang.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Mutation Rate</td>
                                        <td>Peluang slot diacak ulang (swap + repair HC).</td>
                                        <td>Lebih mudah keluar stagnasi; jadwal bisa berisik jika berlebihan.</td>
                                        <td>Populasi homogen; sulit memperbaiki gap/distribusi buruk.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Elitism Ratio</td>
                                        <td>Persentase kromosom terbaik yang dibawa ke generasi berikutnya.</td>
                                        <td>Solusi terbaik tidak hilang; diversitas bisa menurun.</td>
                                        <td>Lebih banyak eksperimen; elite bisa tertimpa solusi buruk.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Fitness Threshold</td>
                                        <td>GA berhenti awal jika fitness mencapai nilai ini.</td>
                                        <td>Target lebih tinggi, GA kerja lebih lama demi kualitas maksimal.</td>
                                        <td>Stop lebih cepat; jadwal cukup oke tetapi belum optimal.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Stagnation Limit</td>
                                        <td>GA berhenti jika fitness tidak membaik selama N generasi.</td>
                                        <td>Waktu lebih lama mencari perbaikan; total waktu naik.</td>
                                        <td>Early stop agresif; hemat waktu, kualitas mentok lebih awal.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Adaptive Mutation</td>
                                        <td>Otomatis menaikkan mutation rate saat stagnan.</td>
                                        <td><strong>Aktif:</strong> Lebih tahan stagnasi.</td>
                                        <td><strong>Nonaktif:</strong> Perilaku lebih predictable; mudah stuck.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Adaptive Trigger</td>
                                        <td>Generasi stagnan sebelum mutation dinaikkan.</td>
                                        <td>Menunggu lebih lama sebelum mengguncang populasi.</td>
                                        <td>Mutation adaptif aktif lebih cepat.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Adaptive Increment</td>
                                        <td>Besar kenaikan mutation rate tiap trigger.</td>
                                        <td>Lonjakan eksplorasi lebih besar.</td>
                                        <td>Perubahan mutation lebih halus.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guideSc">
                        Bobot Soft Constraint (SC-1 s/d SC-11)
                    </button>
                </h2>
                <div id="guideSc" class="accordion-collapse collapse" data-bs-parent="#paramGuideAccordion">
                    <div class="accordion-body">
                        <p class="small text-muted mb-3">
                            Bobot skala <strong>1–10</strong> menentukan prioritas relatif saat GA mengoptimalkan kualitas jadwal.
                            HC-1 s/d HC-8 tetap wajib dipenuhi.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:12%">Kode</th>
                                        <th style="width:22%">Fungsi</th>
                                        <th style="width:33%">Jika bobot dinaikkan</th>
                                        <th style="width:33%">Jika bobot diturunkan</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <tr>
                                        <td class="fw-medium">SC-1</td>
                                        <td>Minim gap jadwal guru antar slot mengajar.</td>
                                        <td>Guru lebih padat tanpa jeda panjang; bisa bentrok prioritas SC-6.</td>
                                        <td>Gap guru lebih sering muncul.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-2</td>
                                        <td>Minim gap jadwal kelas (siswa).</td>
                                        <td>Kelas lebih padat per hari; siswa kurang bolong di tengah hari.</td>
                                        <td>Siswa lebih sering punya slot kosong.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-3</td>
                                        <td>Sebarkan mapel merata sepanjang minggu.</td>
                                        <td>Mapel tidak menumpuk di satu hari.</td>
                                        <td>Mapel bisa menumpuk di hari tertentu.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-4</td>
                                        <td>Mapel berat (<code>bobot_kognitif</code> tinggi) di pagi.</td>
                                        <td>Mapel kognitif berat lebih sering di slot pagi.</td>
                                        <td>Mapel berat bisa jatuh sore.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-5</td>
                                        <td>Mapel ringan di sore.</td>
                                        <td>Mapel ringan lebih sering di akhir hari.</td>
                                        <td>Mapel ringan bisa menempati pagi.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-6</td>
                                        <td>Seimbangkan JP guru per hari.</td>
                                        <td>Beban guru merata tiap hari.</td>
                                        <td>Guru bisa sangat sibuk di beberapa hari saja.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-7</td>
                                        <td>Preferensi/hindari hari-slot dari <code>guru_preferensi</code>.</td>
                                        <td>Jadwal lebih sesuai keinginan guru.</td>
                                        <td>Preferensi guru sering diabaikan.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-8</td>
                                        <td>Minim perpindahan ruangan per hari.</td>
                                        <td>Lebih sedikit pindah lab/ruang.</td>
                                        <td>Lebih sering pindah ruang; penempatan lebih fleksibel.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-9</td>
                                        <td>Satu guru konsisten per kelas–mapel.</td>
                                        <td>Lebih ketat mempertahankan guru yang sama.</td>
                                        <td>Lebih toleran jika guru berganti (jarang terjadi).</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-10</td>
                                        <td>Rotasi mapel jam pertama antar hari.</td>
                                        <td>Jam pertama bervariasi antar hari.</td>
                                        <td>Jam pertama cenderung mapel yang sama.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">SC-11</td>
                                        <td>Seimbangkan pemakaian lab antar jurusan.</td>
                                        <td>Lab tidak didominasi satu jurusan.</td>
                                        <td>Satu jurusan bisa memonopoli lab.</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Lab preferensi</td>
                                        <td>Penalti jika penempatan tidak di lab utama (<code>kelas_mapel.lab_id</code>).</td>
                                        <td>Solver lebih patuh lab utama; overflow ke lab jurusan lain lebih jarang.</td>
                                        <td>Lebih toleran memakai lab jurusan lain saat lab utama penuh.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guideSystem">
                        Batas Sistem
                    </button>
                </h2>
                <div id="guideSystem" class="accordion-collapse collapse" data-bs-parent="#paramGuideAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:18%">Parameter</th>
                                        <th style="width:28%">Fungsi</th>
                                        <th style="width:27%">Jika dinaikkan</th>
                                        <th style="width:27%">Jika diturunkan</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <tr>
                                        <td class="fw-medium">Timeout Algoritma</td>
                                        <td>Batas total waktu (detik) untuk CSP + GA.</td>
                                        <td>Lebih banyak waktu untuk data besar; risiko timeout browser di atas ~300 dtk.</td>
                                        <td>Generate berhenti lebih cepat; lebih sering partial atau fitness belum optimal.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top bg-light small text-muted mb-0">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>Tips:</strong> Mulai dari nilai default. Jika hasil sering <em>partial</em>, naikkan <em>CSP Max Attempts</em> atau <em>Timeout</em>.
                            Jika jadwal valid tetapi banyak gap, naikkan bobot SC-1/SC-2 dan <em>Max Generations</em>.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
