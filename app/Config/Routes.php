<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'AuthController::index');

// Auth routes (public)
$routes->get('auth/login', 'AuthController::index');
$routes->post('auth/login', 'AuthController::login');
$routes->post('auth/logout', 'AuthController::logout');
$routes->get('auth/change-password', 'AuthController::changePasswordForm', ['filter' => 'auth']);
$routes->post('auth/change-password', 'AuthController::changePassword', ['filter' => 'auth']);

// Profile (all roles)
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile', 'ProfileController::update');
    $routes->post('profile/password', 'ProfileController::changePassword');
});

// Backward-compat redirect
$routes->addRedirect('admin/(:any)', 'kurikulum/$1');

// Kurikulum routes
$routes->group('kurikulum', ['filter' => 'kurikulum'], function ($routes) {
    $routes->get('dashboard', 'DashboardController::kurikulum');

    $routes->resource('users', ['controller' => 'Kurikulum\UserController']);
    $routes->post('users/(:num)/reset-password', 'Kurikulum\UserController::resetPassword/$1');

    $routes->resource('tahun-ajaran', ['controller' => 'Kurikulum\TahunAjaranController']);
    $routes->resource('jurusan', ['controller' => 'Kurikulum\JurusanController']);
    $routes->resource('ruangan', ['controller' => 'Kurikulum\RuanganController']);
    $routes->resource('timeslot', ['controller' => 'Kurikulum\TimeslotController']);

    $routes->post('guru/import', 'Kurikulum\GuruController::import');
    $routes->get('guru/(:num)/mapel', 'Kurikulum\GuruMapelController::index/$1');
    $routes->post('guru/(:num)/mapel', 'Kurikulum\GuruMapelController::create/$1');
    $routes->delete('guru/(:num)/mapel/(:num)', 'Kurikulum\GuruMapelController::delete/$1/$2');
    $routes->get('guru/(:num)/hari-blokir', 'Kurikulum\GuruHariBlokirController::index/$1');
    $routes->post('guru/(:num)/hari-blokir', 'Kurikulum\GuruHariBlokirController::update/$1');
    $routes->resource('guru', ['controller' => 'Kurikulum\GuruController']);

    $routes->get('kelas/(:num)/mapel', 'Kurikulum\KelasMapelController::index/$1');
    $routes->post('kelas/(:num)/mapel', 'Kurikulum\KelasMapelController::create/$1');
    $routes->post('kelas/(:num)/mapel/(:num)', 'Kurikulum\KelasMapelController::update/$1/$2');
    $routes->delete('kelas/(:num)/mapel/(:num)', 'Kurikulum\KelasMapelController::delete/$1/$2');
    $routes->resource('kelas', ['controller' => 'Kurikulum\KelasController']);

    $routes->resource('mapel', ['controller' => 'Kurikulum\MapelController']);

    $routes->get('schedule', 'Kurikulum\ScheduleController::index');
    $routes->post('schedule/generate', 'Kurikulum\ScheduleController::generate');
    $routes->get('schedule/config', 'Kurikulum\ScheduleController::config');
    $routes->post('schedule/config', 'Kurikulum\ScheduleController::saveConfig');
    $routes->get('schedule/result', 'Kurikulum\ScheduleController::result');
    $routes->get('schedule/view/kelas/(:num)', 'Kurikulum\ScheduleController::viewByKelas/$1');
    $routes->get('schedule/view/guru/(:num)', 'Kurikulum\ScheduleController::viewByGuru/$1');
    $routes->get('schedule/view/ruangan/(:num)', 'Kurikulum\ScheduleController::viewByRuangan/$1');
    $routes->get('schedule/export/(:any)', 'Kurikulum\ScheduleController::export/$1');
    $routes->post('schedule/reset', 'Kurikulum\ScheduleController::reset');
    $routes->get('schedule/logs', 'Kurikulum\ScheduleController::logs');
    $routes->get('schedule/history/(:num)', 'Kurikulum\ScheduleController::historyDetail/$1');
    $routes->post('schedule/publish/(:num)', 'Kurikulum\ScheduleController::publish/$1');
    $routes->get('schedule/manual/options', 'Kurikulum\ScheduleController::manualOptions');
    $routes->post('schedule/manual/place', 'Kurikulum\ScheduleController::manualPlace');
    $routes->post('schedule/manual/delete/(:num)', 'Kurikulum\ScheduleController::manualDelete/$1');
    $routes->post('schedule/manual/swap-slots', 'Kurikulum\ScheduleController::manualSwapSlots');
    $routes->post('schedule/manual/swap-mapel', 'Kurikulum\ScheduleController::manualSwapMapel');
    $routes->post('schedule/manual/swap-guru', 'Kurikulum\ScheduleController::manualSwapGuru');
});

// Guru routes
$routes->group('guru', ['filter' => 'guru'], static function ($routes) {
    $routes->get('dashboard', 'Guru\DashboardController::index');
    $routes->get('jadwal', 'Guru\JadwalController::index');
    $routes->get('jadwal/export/(:segment)', 'Guru\JadwalController::export/$1');
    $routes->get('preferensi', 'Guru\PreferensiController::index');
    $routes->post('preferensi', 'Guru\PreferensiController::save');
});

// Kepala Sekolah routes
$routes->group('kepala-sekolah', ['filter' => 'kepala_sekolah'], static function ($routes) {
    $routes->get('dashboard', 'DashboardController::kepalaSekolah');
    $routes->get('jadwal', 'KepalaSekolah\JadwalController::index');
    $routes->get('jadwal/kelas/(:num)', 'KepalaSekolah\JadwalController::viewByKelas/$1');
    $routes->get('jadwal/guru/(:num)', 'KepalaSekolah\JadwalController::viewByGuru/$1');
    $routes->get('jadwal/ruangan/(:num)', 'KepalaSekolah\JadwalController::viewByRuangan/$1');
    $routes->get('jadwal/export/(:segment)', 'KepalaSekolah\JadwalController::export/$1');
    $routes->get('laporan/guru-jam', 'KepalaSekolah\LaporanController::guruJam');
    $routes->get('laporan/guru-jam/export', 'KepalaSekolah\LaporanController::export');
});
