<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $branding = $branding ?? \App\Libraries\BrandingService::get(); ?>
    <title>Login - <?= esc($branding['nama_sekolah']) ?></title>
    <?php if (! empty($branding['logo_url'])): ?>
        <link rel="icon" href="<?= esc($branding['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: none;
            background: #FFFFFF;
        }
        .login-header {
            background-color: #4F46E5;
            color: white;
            padding: 30px 20px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .btn-primary {
            background-color: #4F46E5;
            border-color: #4F46E5;
            padding: 10px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #3730A3;
            border-color: #3730A3;
        }
        .login-logo {
            max-height: 64px;
            max-width: 120px;
            object-fit: contain;
            margin-bottom: 10px;
            background: #fff;
            border-radius: 8px;
            padding: 4px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 d-flex justify-content-center">
            <div class="card login-card">
                <div class="login-header">
                    <?php if (! empty($branding['logo_url'])): ?>
                        <img src="<?= esc($branding['logo_url']) ?>" alt="Logo" class="login-logo">
                    <?php else: ?>
                        <div class="mb-2"><i class="bi bi-calendar-check fs-1"></i></div>
                    <?php endif; ?>
                    <h4 class="mb-0 fw-bold"><?= esc($branding['nama_sekolah']) ?></h4>
                    <p class="mb-0 text-white-50 small mt-1">Smart School Scheduling</p>
                </div>
                <div class="login-body">
                    
                    <?php if (session()->getFlashdata('error')) : ?>
                        <div class="alert alert-danger py-2">
                            <?= esc(session()->getFlashdata('error')) ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?= base_url('auth/login') ?>" method="post">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label text-muted small fw-bold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="Masukkan email">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label text-muted small fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan Password">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                    <div class="text-center mt-4 mb-2">
                        <small class="text-muted"><?= esc($branding['nama_sekolah']) ?> &copy; <?= date('Y') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
