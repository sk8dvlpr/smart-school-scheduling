<?php

use App\Controllers\AuthController;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use Config\Services;

/**
 * @internal
 */
final class AuthChangePasswordGateTest extends CIUnitTestCase
{
    use ControllerTestTrait;

    public function testChangePasswordPostRejectedWithoutMustChangeFlag(): void
    {
        $session = Services::session();
        $session->set([
            'logged_in'            => true,
            'user_id'              => 1,
            'role'                 => 'kurikulum',
            'nama'                 => 'Test',
            'guru_id'              => null,
            'is_admin'             => 1,
            'must_change_password' => 0,
        ]);

        $result = $this->withUri('http://example.com/auth/change-password')
            ->controller(AuthController::class)
            ->execute('changePassword');

        $this->assertTrue($result->isRedirect());
        $this->assertStringContainsString('kurikulum/dashboard', $result->getRedirectUrl());
    }
}
