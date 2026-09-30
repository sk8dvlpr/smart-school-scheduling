<?= $this->extend('install/layout') ?>
<?= $this->section('content') ?>
<h2 class="h5 mb-3">Koneksi database</h2>
<p class="text-muted small">Database akan dibuat otomatis jika belum ada (MySQL user perlu hak CREATE).</p>

<form method="post" action="<?= site_url('install/database') ?>" id="db-form">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Host</label>
            <input type="text" name="hostname" class="form-control" value="<?= esc(old('hostname', $wizard['db']['hostname'] ?? 'localhost')) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Port</label>
            <input type="number" name="port" class="form-control" value="<?= esc(old('port', $wizard['db']['port'] ?? '3306')) ?>" required>
        </div>
        <div class="col-12">
            <label class="form-label">Nama database</label>
            <input type="text" name="database" class="form-control" value="<?= esc(old('database', $wizard['db']['database'] ?? 'smart_school_scheduling')) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" value="<?= esc(old('username', $wizard['db']['username'] ?? 'root')) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" value="<?= esc(old('password', '')) ?>" autocomplete="new-password">
        </div>
        <div class="col-12">
            <label class="form-label">URL aplikasi (baseURL)</label>
            <input type="url" name="baseURL" class="form-control" value="<?= esc(old('baseURL', $wizard['baseURL'] ?? site_url())) ?>" required>
            <div class="form-text">Contoh: http://localhost:8080</div>
        </div>
    </div>
    <div class="mt-3">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-test">Uji koneksi</button>
        <span id="test-result" class="ms-2 small"></span>
    </div>
    <div class="d-flex justify-content-between mt-4">
        <a href="<?= site_url('install/requirements') ?>" class="btn btn-outline-secondary">Kembali</a>
        <button type="submit" class="btn btn-primary">Lanjut</button>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('btn-test')?.addEventListener('click', async () => {
    const form = document.getElementById('db-form');
    const fd = new FormData(form);
    const el = document.getElementById('test-result');
    el.textContent = 'Menghubungkan…';
    el.className = 'ms-2 small text-muted';
    try {
        const res = await fetch('<?= site_url('install/test-database') ?>', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.csrf?.name && data.csrf?.hash) {
            let input = form.querySelector('input[name="' + data.csrf.name + '"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = data.csrf.name;
                form.prepend(input);
            }
            input.value = data.csrf.hash;
        }
        if (data.ok) {
            el.textContent = 'Koneksi berhasil.';
            el.className = 'ms-2 small text-success';
        } else {
            el.textContent = 'Koneksi gagal.';
            el.className = 'ms-2 small text-danger';
        }
    } catch (e) {
        el.textContent = 'Permintaan gagal.';
        el.className = 'ms-2 small text-danger';
    }
});
</script>
<?= $this->endSection() ?>
