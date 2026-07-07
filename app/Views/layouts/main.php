<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta id="csrf-token" name="<?= csrf_token() ?>" content="<?= csrf_hash() ?>">
    <title><?= $title ?? 'S3 Dashboard' ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= base_url('css/style.css?v=' . filemtime(FCPATH . 'css/style.css')) ?>" rel="stylesheet">
    
    <?= $this->renderSection('styles') ?>
</head>
<body>

    <div class="wrapper">
        <!-- Sidebar  -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check"></i> S3 System</h5>
            </div>

            <ul class="list-unstyled components">
                <li class="px-3 mb-2">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Menu</small>
                </li>

                <?php
                $role = session()->get('role');
                $roleLabels = [
                    'kurikulum'      => 'Kurikulum',
                    'guru'           => 'Guru',
                    'kepala_sekolah' => 'Kepala Sekolah',
                ];
                ?>

                <?php if ($role === 'kurikulum'): ?>
                    <li class="<?= url_is('kurikulum/dashboard') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="<?= url_is('kurikulum/users*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/users') ?>"><i class="bi bi-people"></i> Manajemen User</a>
                    </li>
                    <li class="px-3 mt-4 mb-2">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Master Data</small>
                    </li>
                    <li class="<?= url_is('kurikulum/tahun-ajaran*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/tahun-ajaran') ?>"><i class="bi bi-calendar3"></i> Tahun Ajaran</a>
                    </li>
                    <li class="<?= url_is('kurikulum/jurusan*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/jurusan') ?>"><i class="bi bi-journal-bookmark"></i> Jurusan</a>
                    </li>
                    <li class="<?= url_is('kurikulum/ruangan*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/ruangan') ?>"><i class="bi bi-door-open"></i> Ruangan</a>
                    </li>
                    <li class="<?= url_is('kurikulum/guru*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/guru') ?>"><i class="bi bi-person-badge"></i> Guru</a>
                    </li>
                    <li class="<?= url_is('kurikulum/kelas*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/kelas') ?>"><i class="bi bi-building"></i> Kelas</a>
                    </li>
                    <li class="<?= url_is('kurikulum/mapel*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/mapel') ?>"><i class="bi bi-book"></i> Mata Pelajaran</a>
                    </li>
                    <li class="<?= url_is('kurikulum/timeslot*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/timeslot') ?>"><i class="bi bi-clock"></i> Timeslot</a>
                    </li>
                    <li class="px-3 mt-4 mb-2">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Penjadwalan</small>
                    </li>
                    <li class="<?= url_is('kurikulum/schedule*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/schedule') ?>"><i class="bi bi-cpu"></i> Generator Jadwal</a>
                    </li>
                    <?php if (session()->get('guru_id')): ?>
                        <li class="px-3 mt-4 mb-2">
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Mengajar</small>
                        </li>
                        <li class="<?= url_is('guru/preferensi*') ? 'active' : '' ?>">
                            <a href="<?= base_url('guru/preferensi') ?>"><i class="bi bi-sliders"></i> Preferensi Jadwal</a>
                        </li>
                        <li class="<?= url_is('guru/jadwal*') ? 'active' : '' ?>">
                            <a href="<?= base_url('guru/jadwal') ?>"><i class="bi bi-calendar-week"></i> Jadwal Saya</a>
                        </li>
                    <?php endif; ?>
                    <li class="<?= url_is('profile*') ? 'active' : '' ?>">
                        <a href="<?= base_url('profile') ?>"><i class="bi bi-person-circle"></i> Profil</a>
                    </li>
                <?php endif; ?>

                <?php if ($role === 'guru'): ?>
                    <li class="<?= url_is('guru/dashboard') ? 'active' : '' ?>">
                        <a href="<?= base_url('guru/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="<?= url_is('guru/jadwal*') ? 'active' : '' ?>">
                        <a href="<?= base_url('guru/jadwal') ?>"><i class="bi bi-calendar-week"></i> Jadwal Mengajar</a>
                    </li>
                    <li class="<?= url_is('profile*') ? 'active' : '' ?>">
                        <a href="<?= base_url('profile') ?>"><i class="bi bi-person-circle"></i> Profil</a>
                    </li>
                <?php endif; ?>

                <?php if ($role === 'kepala_sekolah'): ?>
                    <li class="<?= url_is('kepala-sekolah/dashboard') ? 'active' : '' ?>">
                        <a href="<?= base_url('kepala-sekolah/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="<?= url_is('kepala-sekolah/jadwal*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kepala-sekolah/jadwal') ?>"><i class="bi bi-calendar-week"></i> Lihat Jadwal</a>
                    </li>
                    <li class="<?= url_is('kepala-sekolah/laporan*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kepala-sekolah/laporan/guru-jam') ?>"><i class="bi bi-bar-chart-line"></i> Laporan Jam Mengajar</a>
                    </li>
                    <li class="<?= url_is('profile*') ? 'active' : '' ?>">
                        <a href="<?= base_url('profile') ?>"><i class="bi bi-person-circle"></i> Profil</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Page Content  -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-light d-lg-none">
                        <i class="bi bi-list"></i>
                    </button>
                    
                    <div class="d-none d-lg-block fw-semibold text-muted">
                        <?= $title ?? 'S3 Dashboard' ?>
                    </div>

                    <div class="ms-auto d-flex align-items-center">
                        <button class="btn btn-link text-dark me-3" id="darkModeToggle">
                            <i class="bi bi-moon"></i>
                        </button>
                        
                        <div class="dropdown">
                            <a class="text-decoration-none text-dark dropdown-toggle d-flex align-items-center" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="bg-primary-custom text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                    <?= substr(session()->get('nama'), 0, 1) ?>
                                </div>
                                <span class="fw-medium d-none d-md-inline"><?= session()->get('nama') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userDropdown">
                                <li>
                                    <div class="dropdown-item-text">
                                        <small class="text-muted d-block">Role</small>
                                        <span class="fw-bold"><?= esc($roleLabels[$role] ?? $role) ?></span>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="<?= base_url('auth/logout') ?>" method="post">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="p-4">
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (Needed for DataTables later) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // ponytail: CSRF cookie is HttpOnly — JS reads meta tag, not document.cookie
        (function () {
            window.s3CsrfToken = function () {
                const meta = document.getElementById('csrf-token');
                return meta ? meta.getAttribute('content') : '';
            };
            window.s3CsrfTokenName = function () {
                const meta = document.getElementById('csrf-token');
                return meta ? meta.getAttribute('name') : '';
            };
            window.s3CsrfTouch = function (hash) {
                const meta = document.getElementById('csrf-token');
                if (meta && hash) {
                    meta.setAttribute('content', hash);
                }
            };
            $.ajaxPrefilter(function (options) {
                const method = ((options.type || options.method) || 'GET').toUpperCase();
                if (/^(GET|HEAD|OPTIONS|TRACE)$/.test(method)) {
                    return;
                }
                const token = window.s3CsrfToken();
                const name = window.s3CsrfTokenName();
                if (!token || !name) {
                    return;
                }
                options.headers = $.extend({}, options.headers, { 'X-CSRF-TOKEN': token });
                if (options.data instanceof FormData) {
                    options.data.set(name, token);
                    return;
                }
                if (typeof options.data === 'string') {
                    options.data += (options.data ? '&' : '') + encodeURIComponent(name) + '=' + encodeURIComponent(token);
                } else {
                    options.data = options.data || {};
                    if (typeof options.data === 'object') {
                        options.data[name] = token;
                    }
                }
            });
            $(document).ajaxComplete(function (_event, xhr) {
                const res = xhr.responseJSON;
                if (res && res.csrf_hash) {
                    window.s3CsrfTouch(res.csrf_hash);
                }
            });
        })();
    </script>
    
    <script>
        $(document).ready(function () {
            // Sidebar Toggle
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
            
            // Dark Mode Toggle
            const toggleBtn = $('#darkModeToggle');
            const icon = toggleBtn.find('i');
            
            // Check LocalStorage
            if (localStorage.getItem('darkMode') === 'enabled') {
                $('body').addClass('dark-mode');
                icon.removeClass('bi-moon').addClass('bi-sun');
            }
            
            toggleBtn.on('click', function() {
                $('body').toggleClass('dark-mode');
                
                if ($('body').hasClass('dark-mode')) {
                    localStorage.setItem('darkMode', 'enabled');
                    icon.removeClass('bi-moon').addClass('bi-sun');
                } else {
                    localStorage.setItem('darkMode', null);
                    icon.removeClass('bi-sun').addClass('bi-moon');
                }
            });

            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    
    <?= $this->renderSection('scripts') ?>
</body>
</html>
