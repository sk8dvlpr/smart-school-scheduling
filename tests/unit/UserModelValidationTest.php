<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UserModelValidationTest extends CIUnitTestCase
{
    public function testNipOptionalEmailRequired(): void
    {
        $rules = (new \App\Models\UserModel())->getValidationRules();

        $this->assertStringContainsString('permit_empty', (string) $rules['nip']);
        $this->assertStringContainsString('required', (string) $rules['email']);
        $this->assertStringContainsString('is_unique[users.email', (string) $rules['email']);
    }
}
