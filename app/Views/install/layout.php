<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Instalasi') ?> — Smart School Scheduling</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: #f4f6fb; }
        .install-card { max-width: 720px; margin: 2rem auto; }
        .step-badge { width: 2rem; height: 2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; }
        .step-active { background: #2563eb; color: #fff; }
        .step-done { background: #16a34a; color: #fff; }
        .step-pending { background: #e5e7eb; color: #6b7280; }
    </style>
</head>
<body>
<div class="container install-card">
    <div class="text-center mb-4">
        <h1 class="h3 fw-bold">Smart School Scheduling</h1>
        <p class="text-muted mb-0">Wizard instalasi</p>
    </div>

    <?php
    $steps = [1 => 'Selamat datang', 2 => 'Persyaratan', 3 => 'Database', 4 => 'Sekolah & Admin', 5 => 'Instal', 6 => 'Selesai'];
    $current = (int) ($step ?? 1);
    ?>
    <div class="d-flex justify-content-between mb-4 flex-wrap gap-2">
        <?php foreach ($steps as $num => $label): ?>
            <?php
            $cls = $num < $current ? 'step-done' : ($num === $current ? 'step-active' : 'step-pending');
            ?>
            <div class="text-center flex-fill" style="min-width: 80px;">
                <span class="step-badge <?= $cls ?>"><?= $num ?></span>
                <div class="small mt-1 text-muted"><?= esc($label) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
