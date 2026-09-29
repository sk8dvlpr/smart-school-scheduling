<?= $this->extend('install/layout') ?>
<?= $this->section('content') ?>
<h2 class="h5 mb-3 text-success">Instalasi selesai</h2>
<p>Aplikasi siap digunakan. Login dengan akun kurikulum admin yang Anda buat<?php if ($email): ?> (<strong><?= esc($email) ?></strong>)<?php endif; ?>.</p>
<p class="text-muted small">Untuk keamanan, hapus atau lindungi akses ke wizard instalasi — file <code>writable/installed.lock</code> sudah dibuat.</p>
<a href="<?= site_url('auth/login') ?>" class="btn btn-primary">Ke halaman login</a>
<?= $this->endSection() ?>
