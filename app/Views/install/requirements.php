<?= $this->extend('install/layout') ?>
<?= $this->section('content') ?>
<h2 class="h5 mb-3">Persyaratan sistem</h2>
<ul class="list-group mb-4">
    <?php foreach ($checks as $check): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong><?= esc($check['label']) ?></strong>
                <?php if ($check['hint'] !== ''): ?>
                    <div class="small text-muted"><?= esc($check['hint']) ?></div>
                <?php endif; ?>
            </div>
            <span class="badge <?= $check['ok'] ? 'bg-success' : 'bg-danger' ?>"><?= $check['ok'] ? 'OK' : 'Gagal' ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<form method="post" action="<?= site_url('install/requirements') ?>">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between">
        <a href="<?= site_url('install') ?>" class="btn btn-outline-secondary">Kembali</a>
        <button type="submit" class="btn btn-primary">Lanjut</button>
    </div>
</form>
<?= $this->endSection() ?>
