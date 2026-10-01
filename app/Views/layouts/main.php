<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta id="csrf-token" name="<?= csrf_token() ?>" content="<?= csrf_hash() ?>">
    <?php $branding = \App\Libraries\BrandingService::get(); ?>
    <title><?= esc($title ?? $branding['nama_sekolah']) ?></title>
    <?php if (! empty($branding['logo_url'])): ?>
        <link rel="icon" href="<?= esc($branding['logo_url']) ?>" type="image/png">
    <?php else: ?>
        <link rel="icon" href="<?= base_url('imgs/logo-default.svg') ?>" type="image/svg+xml">
    <?php endif; ?>

    <script>
        (function () {
            try {
                if (localStorage.getItem('darkMode') === 'enabled') {
                    document.documentElement.classList.add('dark-mode');
                }
            } catch (e) {}
        })();
    </script>

    <link href="<?= base_url('vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('vendor/bootstrap-icons/font/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('css/style.css?v=' . filemtime(FCPATH . 'css/style.css')) ?>" rel="stylesheet">

    <?= $this->renderSection('styles') ?>
</head>
<body>
<script>
    (function () {
        try {
            if (localStorage.getItem('darkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
            }
        } catch (e) {}
    })();
</script>

    <?php
    $role = session()->get('role');
    $roleLabels = [
        'kurikulum'      => 'Kurikulum',
        'guru'           => 'Guru',
        'kepala_sekolah' => 'Kepala Sekolah',
    ];
    $nama = (string) session()->get('nama');
    $initial = $nama !== '' ? mb_strtoupper(mb_substr($nama, 0, 1)) : '?';
    ?>

    <div class="wrapper">
        <div class="s3-sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

        <nav id="sidebar" aria-label="Navigasi utama">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <div class="sidebar-logo-wrap">
                        <img src="<?= esc($branding['logo_url'] ?? base_url('imgs/logo-default.svg')) ?>" alt="Logo <?= esc($branding['nama_sekolah']) ?>" class="sidebar-logo-img">
                    </div>
                    <div class="sidebar-brand-name"><?= esc($branding['nama_sekolah']) ?></div>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <span class="sidebar-section-label">Menu</span>
                </li>

                <?php if ($role === 'kurikulum'): ?>
                    <li class="<?= url_is('kurikulum/dashboard') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="<?= url_is('kurikulum/users*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/users') ?>"><i class="bi bi-people"></i> Manajemen User</a>
                    </li>
                    <li>
                        <span class="sidebar-section-label mt-3 d-block">Master Data</span>
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
                        <a href="<?= base_url('kurikulum/kelas') ?>"><i class="bi bi-building"></i> Rombel</a>
                    </li>
                    <li class="<?= url_is('kurikulum/mapel*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/mapel') ?>"><i class="bi bi-book"></i> Mata Pelajaran</a>
                    </li>
                    <li class="<?= url_is('kurikulum/timeslot*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/timeslot') ?>"><i class="bi bi-clock"></i> Timeslot</a>
                    </li>
                    <li>
                        <span class="sidebar-section-label mt-3 d-block">Penjadwalan</span>
                    </li>
                    <li class="<?= url_is('kurikulum/schedule*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/schedule') ?>"><i class="bi bi-cpu"></i> Generator Jadwal</a>
                    </li>
                    <?php if ((int) session()->get('is_admin') === 1): ?>
                    <li class="<?= url_is('kurikulum/pengaturan*') ? 'active' : '' ?>">
                        <a href="<?= base_url('kurikulum/pengaturan') ?>"><i class="bi bi-gear"></i> Pengaturan</a>
                    </li>
                    <?php endif; ?>
                    <?php if (session()->get('guru_id')): ?>
                        <li>
                            <span class="sidebar-section-label mt-3 d-block">Mengajar</span>
                        </li>
                        <li class="<?= url_is('guru/hari-blokir*') ? 'active' : '' ?>">
                            <a href="<?= base_url('guru/hari-blokir') ?>"><i class="bi bi-calendar-x"></i> Hari Tidak Mengajar</a>
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
                    <li class="<?= url_is('guru/hari-blokir*') ? 'active' : '' ?>">
                        <a href="<?= base_url('guru/hari-blokir') ?>"><i class="bi bi-calendar-x"></i> Hari Tidak Mengajar</a>
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

        <div id="content">
            <header class="s3-topbar">
                <div class="d-flex align-items-center gap-2 w-100">
                    <button type="button" id="sidebarCollapse" class="s3-icon-btn d-lg-none" aria-label="Buka menu">
                        <i class="bi bi-list"></i>
                    </button>

                    <div class="s3-topbar-title flex-grow-1 text-truncate">
                        <?= esc($title ?? 'Dashboard') ?>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="s3-icon-btn" id="darkModeToggle" aria-label="Mode gelap">
                            <i class="bi bi-moon"></i>
                        </button>

                        <div class="dropdown">
                            <a class="s3-user-toggle dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="s3-avatar"><?= esc($initial) ?></span>
                                <span class="fw-semibold d-none d-md-inline" style="font-size: 0.9rem;"><?= esc($nama) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <div class="dropdown-item-text px-3 py-2">
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
            </header>

            <div class="s3-main">
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>

    <script src="<?= base_url('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('vendor/jquery/jquery.min.js') ?>"></script>
    <script>
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
            const $sidebar = $('#sidebar');
            const $backdrop = $('#sidebarBackdrop');

            function closeSidebar() {
                $sidebar.removeClass('active');
                $backdrop.removeClass('show').attr('aria-hidden', 'true');
                $('body').css('overflow', '');
            }

            function openSidebar() {
                $sidebar.addClass('active');
                $backdrop.addClass('show').attr('aria-hidden', 'false');
                $('body').css('overflow', 'hidden');
            }

            $('#sidebarCollapse').on('click', function () {
                if ($sidebar.hasClass('active')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            $backdrop.on('click', closeSidebar);

            $(window).on('resize', function () {
                if (window.innerWidth >= 992) {
                    closeSidebar();
                }
            });

            const toggleBtn = $('#darkModeToggle');
            const icon = toggleBtn.find('i');

            if ($('body').hasClass('dark-mode') || document.documentElement.classList.contains('dark-mode')) {
                $('body').addClass('dark-mode');
                document.documentElement.classList.add('dark-mode');
                icon.removeClass('bi-moon').addClass('bi-sun');
            }

            toggleBtn.on('click', function () {
                $('body').toggleClass('dark-mode');
                document.documentElement.classList.toggle('dark-mode');

                if ($('body').hasClass('dark-mode')) {
                    localStorage.setItem('darkMode', 'enabled');
                    icon.removeClass('bi-moon').addClass('bi-sun');
                } else {
                    localStorage.setItem('darkMode', 'disabled');
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
