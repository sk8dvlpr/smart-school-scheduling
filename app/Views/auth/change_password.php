<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - S3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { max-width: 420px; width: 100%; border: none; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,.05); }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="card mx-auto">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-1">Ganti Password</h4>
            <p class="text-muted small mb-4">Anda wajib mengganti password sebelum melanjutkan.</p>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <form action="<?= base_url('auth/change-password') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password_baru" class="form-control" minlength="6" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" name="password_konfirmasi" class="form-control" minlength="6" required>
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
