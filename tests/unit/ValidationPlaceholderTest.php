<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guards against the CI4 4.7 rule that any {id} placeholder in a validation
 * rule (e.g. is_unique[table.col,id,{id}]) requires an 'id' rule to exist,
 * otherwise fillPlaceholders() throws a LogicException when a record is
 * edited (id present in the data). This was the "edit gagal" bug.
 *
 * @internal
 */
final class ValidationPlaceholderTest extends CIUnitTestCase
{
    /**
     * Every model whose rules reference {id} must also define an 'id' rule.
     */
    public function testModelsUsingIdPlaceholderDefineIdRule(): void
    {
        $models = [
            \App\Models\JurusanModel::class,
            \App\Models\RuanganModel::class,
            \App\Models\MapelModel::class,
            \App\Models\UserModel::class,
            \App\Models\GuruModel::class,
            \App\Models\HariModel::class,
        ];

        foreach ($models as $modelClass) {
            $rules = (new $modelClass())->getValidationRules();

            $usesIdPlaceholder = false;
            foreach ($rules as $rule) {
                $ruleString = is_array($rule) ? implode('|', $rule['rules'] ?? $rule) : $rule;
                if (str_contains((string) $ruleString, '{id}')) {
                    $usesIdPlaceholder = true;
                    break;
                }
            }

            if ($usesIdPlaceholder) {
                $this->assertArrayHasKey(
                    'id',
                    $rules,
                    $modelClass . ' uses the {id} placeholder but has no "id" validation rule; editing will throw a LogicException.',
                );
            }
        }
    }
}
