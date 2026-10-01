<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password — Smart School Scheduling</title>
    <link href="<?= base_url('vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('css/style.css?v=' . filemtime(FCPATH . 'css/style.css')) ?>" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse 70% 45% at 0% 0%, rgba(232, 93, 76, 0.12), transparent),
                radial-gradient(ellipse 50% 40% at 100% 100%, rgba(11, 31, 58, 0.08), transparent),
                var(--s3-surface);
        }
        .cp-card {
            max-width: 420px;
            width: 100%;
            border: 1px solid var(--s3-line);
            border-radius: var(--radius-lg);
            background: var(--s3-card);
            box-shadow: var(--shadow-md);
        }
        .cp-card h1 {
            font-family: var(--font-display);
            font-size: 1.35rem;
            font-weight: 650;
            letter-spacing: -0.03em;
            color: var(--s3-navy);
            margin: 0 0 0.25rem;
        }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="cp-card mx-auto">
        <div class="p-4">
            <h1>Ganti Password</h1>
            <p class="text-muted small mb-4">Anda wajib mengganti password sebelum melanjutkan.</p>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <form action="<?= base_url('auth/change-password') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password_baru" class="form-control" minlength="8" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" name="password_konfirmasi" class="form-control" minlength="8" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
