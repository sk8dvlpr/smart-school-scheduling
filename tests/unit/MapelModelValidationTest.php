<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MapelModelValidationTest extends CIUnitTestCase
{
    public function testJamPerMingguRequired(): void
    {
        $rules = (new \App\Models\MapelModel())->getValidationRules();

        $this->assertStringContainsString('required', (string) $rules['jam_per_minggu']);
        $this->assertStringContainsString('greater_than[0]', (string) $rules['jam_per_minggu']);
    }
}
