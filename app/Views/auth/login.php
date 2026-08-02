<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $branding = $branding ?? \App\Libraries\BrandingService::get();
        $logoUrl = base_url('imgs/logo.jpeg');
        $bgUrl = base_url('imgs/background.jpeg');
    ?>
    <title>Login - <?= esc($branding['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= esc($logoUrl) ?>" type="image/jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --login-accent: #B42318;
            --login-accent-hover: #912018;
            --login-text: #1F2937;
            --login-muted: #6B7280;
            --login-border: #E5E7EB;
            --login-panel: #FFFFFF;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--login-text);
            background: var(--login-panel);
        }

        .login-page {
            display: flex;
            min-height: 100vh;
        }

        .login-hero {
            flex: 1 1 58%;
            position: relative;
            background: #374151 url('<?= esc($bgUrl) ?>') center center / cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 2.5rem;
        }

        .login-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                180deg,
                rgba(17, 24, 39, 0.15) 0%,
                rgba(17, 24, 39, 0.55) 100%
            );
        }

        .login-hero-content {
            position: relative;
            z-index: 1;
            max-width: 32rem;
            color: #fff;
        }

        .login-hero-content h2 {
            font-size: 1.625rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            line-height: 1.25;
        }

        .login-hero-content p {
            margin: 0;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.88);
        }

        .login-panel {
            flex: 0 0 42%;
            max-width: 520px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 2rem;
            background: var(--login-panel);
            border-left: 1px solid var(--login-border);
        }

        .login-panel-inner {
            width: 100%;
            max-width: 360px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin-bottom: 1rem;
        }

        .login-brand h1 {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0 0 0.25rem;
            color: var(--login-text);
            line-height: 1.35;
        }

        .login-brand .login-subtitle {
            font-size: 0.8125rem;
            color: var(--login-muted);
            margin: 0;
            font-weight: 500;
        }

        .login-form-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--login-text);
            margin-bottom: 0.375rem;
        }

        .login-input-group {
            display: flex;
            align-items: stretch;
            border: 1px solid var(--login-border);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .login-input-group:focus-within {
            border-color: var(--login-accent);
            box-shadow: 0 0 0 3px rgba(180, 35, 24, 0.12);
        }

        .login-input-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            flex-shrink: 0;
            color: var(--login-muted);
            background: #F9FAFB;
            border-right: 1px solid var(--login-border);
            font-size: 1rem;
        }

        .login-input-group .form-control {
            border: none;
            box-shadow: none;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
            background: transparent;
        }

        .login-input-group .form-control:focus {
            box-shadow: none;
        }

        .btn-login {
            width: 100%;
            padding: 0.6875rem 1rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #fff;
            background: var(--login-accent);
            border: 1px solid var(--login-accent);
            border-radius: 8px;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .btn-login:hover,
        .btn-login:focus {
            color: #fff;
            background: var(--login-accent-hover);
            border-color: var(--login-accent-hover);
        }

        .login-alert {
            font-size: 0.875rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--login-muted);
        }

        @media (max-width: 991.98px) {
            .login-page {
                flex-direction: column;
                background: #374151 url('<?= esc($bgUrl) ?>') center center / cover no-repeat fixed;
            }

            .login-hero {
                flex: 0 0 auto;
                min-height: 11rem;
                padding: 1.5rem;
                background: transparent;
            }

            .login-hero::before {
                background: linear-gradient(
                    180deg,
                    rgba(17, 24, 39, 0.35) 0%,
                    rgba(17, 24, 39, 0.7) 100%
                );
            }

            .login-hero-content h2 {
                font-size: 1.25rem;
            }

            .login-panel {
                flex: 1 1 auto;
                max-width: none;
                border-left: none;
                border-top-left-radius: 1.25rem;
                border-top-right-radius: 1.25rem;
                margin-top: auto;
                padding: 2rem 1.5rem 2.5rem;
                box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.12);
            }
        }

        @media (max-width: 575.98px) {
            .login-panel {
                padding: 1.75rem 1.25rem 2rem;
            }

            .login-logo {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>

<div class="login-page">
    <section class="login-hero" aria-hidden="true">
        <div class="login-hero-content">
            <h2><?= esc($branding['nama_sekolah']) ?></h2>
            <p>Sistem penjadwalan mata pelajaran untuk mendukung operasional kurikulum sekolah.</p>
        </div>
    </section>

    <main class="login-panel">
        <div class="login-panel-inner">
            <div class="login-brand">
                <img src="<?= esc($logoUrl) ?>" alt="Logo <?= esc($branding['nama_sekolah']) ?>" class="login-logo">
                <h1><?= esc($branding['nama_sekolah']) ?></h1>
                <p class="login-subtitle">Smart School Scheduling</p>
            </div>

            <?php if (session()->getFlashdata('error')) : ?>
                <div class="alert alert-danger login-alert py-2 mb-0">
                    <?= esc(session()->getFlashdata('error')) ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/login') ?>" method="post" class="mt-3">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="email" class="login-form-label d-block">Email</label>
                    <div class="login-input-group">
                        <span class="login-input-icon"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="nama@sekolah.sch.id" autocomplete="email">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="login-form-label d-block">Password</label>
                    <div class="login-input-group">
                        <span class="login-input-icon"><i class="bi bi-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password" autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-login">Masuk</button>
            </form>

            <div class="login-footer">
                &copy; <?= date('Y') ?> <?= esc($branding['nama_sekolah']) ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
