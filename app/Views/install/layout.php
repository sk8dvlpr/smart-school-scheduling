<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Instalasi') ?> — Smart School Scheduling</title>
    <link href="<?= base_url('vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('vendor/bootstrap-icons/font/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('css/style.css?v=' . (@file_exists(FCPATH . 'css/style.css') ? filemtime(FCPATH . 'css/style.css') : time())) ?>" rel="stylesheet">
</head>
<body>
<div class="install-wrap">
    <div class="install-card">
        <div class="install-brand">
            <p class="install-eyebrow">Wizard instalasi</p>
            <h1>Smart School Scheduling</h1>
        </div>

        <?php
        $steps = [1 => 'Selamat datang', 2 => 'Persyaratan', 3 => 'Database', 4 => 'Sekolah & Admin', 5 => 'Instal', 6 => 'Selesai'];
        $current = (int) ($step ?? 1);
        $totalSteps = count($steps);
        ?>
        <nav class="install-steps" aria-label="Progres instalasi">
            <ol class="install-steps-track">
                <?php foreach ($steps as $num => $label): ?>
                    <?php
                    $cls = $num < $current ? 'is-done' : ($num === $current ? 'is-active' : 'is-pending');
                    ?>
                    <li class="install-step <?= $cls ?>">
                        <span class="step-badge" aria-hidden="true"><?= $num < $current ? '✓' : $num ?></span>
                        <span class="install-step-label"><?= esc($label) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="install-steps-mobile">
                Langkah <?= $current ?> dari <?= $totalSteps ?>
                <strong><?= esc($steps[$current] ?? '') ?></strong>
            </p>
        </nav>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('errors')): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach (session()->getFlashdata('errors') as $err): ?>
                                <li><?= esc($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>
</div>
<script src="<?= base_url('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
