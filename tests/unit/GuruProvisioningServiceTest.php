<?php

use App\Libraries\GuruProvisioningService;
use App\Models\GuruModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class GuruProvisioningServiceTest extends CIUnitTestCase
{
    public function testNormalizeUserFieldsDefaultsRoleGuru(): void
    {
        $fields = GuruProvisioningService::normalizeUserFields([
            'email' => '  Test@Example.COM ',
            'nama'  => ' Budi ',
            'nip'   => '',
            'role'  => 'kepala_sekolah',
        ]);

        $this->assertSame('test@example.com', $fields['email']);
        $this->assertSame('Budi', $fields['nama']);
        $this->assertNull($fields['nip']);
        $this->assertSame('guru', $fields['role']);
    }

    public function testProvisionNewGuruRejectsEmptyEmail(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email dan nama wajib diisi');

        (new GuruProvisioningService())->provisionNewGuru([
            'email' => '',
            'nama'  => 'Guru Baru',
        ]);
    }

    public function testAttachGuruToUserRejectsMissingUser(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->method('find')->with(99)->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User tidak ditemukan');

        (new GuruProvisioningService($userModel, new GuruModel()))->attachGuruToUser(99);
    }

    public function testAttachGuruToUserRejectsDuplicateProfile(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->method('find')->with(1)->willReturn(['id' => 1, 'role' => 'guru']);

        $guruModel = new class extends GuruModel {
            public function where($key, $value = null, $escape = null)
            {
                return $this;
            }

            public function first()
            {
                return ['id' => 10, 'user_id' => 1];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah memiliki profil guru');

        (new GuruProvisioningService($userModel, $guruModel))->attachGuruToUser(1);
    }
}
