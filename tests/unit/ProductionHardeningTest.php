<?php

use App\Libraries\CssColor;
use App\Libraries\InstallerService;
use App\Libraries\SafeImageUpload;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ProductionHardeningTest extends CIUnitTestCase
{
    public function testSafeImageUploadRejectsPhpExtension(): void
    {
        $this->assertNull(SafeImageUpload::resolveExtension('php'));
        $this->assertNull(SafeImageUpload::resolveExtension(null, 'php'));
        $this->assertNull(SafeImageUpload::resolveExtension('phtml'));
        $this->assertFalse(SafeImageUpload::isAllowedExtension('php'));
        // MIME-derived png wins over malicious client filename extension.
        $this->assertSame('png', SafeImageUpload::resolveExtension('png', 'php'));
    }

    public function testSafeImageUploadAllowsImageExtensions(): void
    {
        $this->assertSame('png', SafeImageUpload::resolveExtension('png'));
        $this->assertSame('jpg', SafeImageUpload::resolveExtension('jpg'));
        $this->assertSame('jpeg', SafeImageUpload::resolveExtension('jpeg'));
        $this->assertSame('webp', SafeImageUpload::resolveExtension('webp'));
        $this->assertSame('png', SafeImageUpload::resolveExtension(null, 'png'));
    }

    public function testCssColorAllowsHexOnly(): void
    {
        $this->assertSame('#abc', CssColor::hexOrEmpty('#abc'));
        $this->assertSame('#AABBCC', CssColor::hexOrEmpty('#AABBCC'));
        $this->assertSame('', CssColor::hexOrEmpty('red'));
        $this->assertSame('', CssColor::hexOrEmpty('url(javascript:alert(1))'));
        $this->assertSame('', CssColor::hexOrEmpty('#ggg'));
    }

    public function testInstallerDisabledByEnvWithoutLock(): void
    {
        $lock = InstallerService::LOCK_FILE;
        $hadLock = is_file($lock);
        $backup  = $hadLock ? file_get_contents($lock) : null;

        if ($hadLock) {
            @unlink($lock);
        }

        $prevEnv = $_ENV['installer.enabled'] ?? null;
        $prevSrv = $_SERVER['installer.enabled'] ?? null;

        try {
            $_ENV['installer.enabled']    = 'false';
            $_SERVER['installer.enabled'] = 'false';
            putenv('installer.enabled=false');

            $this->assertTrue(InstallerService::isAppInstalled());

            $_ENV['installer.enabled']    = 'true';
            $_SERVER['installer.enabled'] = 'true';
            putenv('installer.enabled=true');

            $this->assertFalse(InstallerService::isAppInstalled());
        } finally {
            if ($prevEnv === null) {
                unset($_ENV['installer.enabled']);
            } else {
                $_ENV['installer.enabled'] = $prevEnv;
            }
            if ($prevSrv === null) {
                unset($_SERVER['installer.enabled']);
            } else {
                $_SERVER['installer.enabled'] = $prevSrv;
            }
            putenv('installer.enabled');

            if ($hadLock && $backup !== null) {
                file_put_contents($lock, $backup);
            }
        }
    }
}
