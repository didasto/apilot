<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Didasto\Apilot\ApilotServiceProvider;
use Didasto\Apilot\OpenApi\SchemaBuilder;
use Didasto\Apilot\Tests\Fixtures\Requests\StorePostRequest;
use Didasto\Apilot\Tests\Fixtures\Requests\UpdatePostRequest;
use Orchestra\Testbench\TestCase;

class SchemaBuilderRequiredFieldsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ApilotServiceProvider::class];
    }

    protected function schemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder();
    }

    protected function schemaFromRules(array $rules): array
    {
        $request = new class extends \Illuminate\Foundation\Http\FormRequest {
            public array $testRules = [];
            public function rules(): array { return $this->testRules; }
            public function authorize(): bool { return true; }
        };
        $request->testRules = $rules;

        // Build schema directly from SchemaBuilder internals
        $builder    = $this->schemaBuilder();
        $properties = [];
        $required   = [];

        foreach ($rules as $field => $rule) {
            if (str_contains((string) $field, '.')) {
                continue;
            }

            $property = $builder->rulesToProperty($rule);
            $properties[$field] = $property;

            $normalizeMethod = new \ReflectionMethod($builder, 'normalizeRules');
            $normalizedRules = $normalizeMethod->invoke($builder, $rule);
            if (in_array('required', $normalizedRules, true)) {
                $required[] = $field;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    // =========================================================================
    // Tests
    // =========================================================================

    public function testRequiredFieldAppearsInRequiredArray(): void
    {
        $schema = $this->schemaFromRules(['title' => 'required|string']);

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('title', $schema['required']);
    }

    public function testMultipleRequiredFieldsAppearInRequiredArray(): void
    {
        $schema = $this->schemaFromRules([
            'title' => 'required|string',
            'body'  => 'required|string',
        ]);

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('title', $schema['required']);
        $this->assertContains('body', $schema['required']);
    }

    public function testOptionalFieldDoesNotAppearInRequiredArray(): void
    {
        $schema = $this->schemaFromRules(['body' => 'nullable|string']);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testSometimesFieldDoesNotAppearInRequiredArray(): void
    {
        $schema = $this->schemaFromRules(['image' => 'sometimes|url']);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testRequiredIfFieldDoesNotAppearInRequiredArray(): void
    {
        $schema = $this->schemaFromRules(['subtitle' => 'required_if:type,article|string']);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testRequiredWithFieldDoesNotAppearInRequiredArray(): void
    {
        $schema = $this->schemaFromRules(['confirm' => 'required_with:password|string']);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testRequiredWithoutFieldDoesNotAppearInRequiredArray(): void
    {
        $schema = $this->schemaFromRules(['email' => 'required_without:phone|string']);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testRequiredUnlessFieldDoesNotAppearInRequiredArray(): void
    {
        $schema = $this->schemaFromRules(['name' => 'required_unless:role,admin|string']);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testRequiredAndNullableFieldIsRequiredButNullable(): void
    {
        $schema = $this->schemaFromRules(['bio' => 'required|nullable|string']);

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('bio', $schema['required']);
        $this->assertTrue($schema['properties']['bio']['nullable']);
    }

    public function testFieldWithNoRequiredRuleIsNotRequired(): void
    {
        $schema = $this->schemaFromRules(['tags' => 'array']);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testEmptyRulesProduceNoRequiredArray(): void
    {
        $schema = $this->schemaFromRules([]);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testStoreRequestSchemaHasCorrectRequiredFields(): void
    {
        $builder = $this->schemaBuilder();
        $schema  = $builder->fromFormRequest(StorePostRequest::class);

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('title', $schema['required']);
        $this->assertContains('body', $schema['required']);
        $this->assertContains('status', $schema['required']);
    }

    public function testUpdateRequestSchemaHasNoRequiredFields(): void
    {
        $builder = $this->schemaBuilder();
        $schema  = $builder->fromFormRequest(UpdatePostRequest::class);

        $this->assertArrayNotHasKey('required', $schema);
    }
}
