<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Didasto\Apilot\OpenApi\SchemaBuilder;
use PHPUnit\Framework\TestCase;

class SchemaBuilderEdgeCasesTest extends TestCase
{
    private SchemaBuilder $schemaBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaBuilder = new SchemaBuilder();
    }

    public function testUnknownRuleTypeDefaultsToString(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['custom_rule']);

        $this->assertSame('string', $property['type']);
    }

    public function testEmptyRulesProduceEmptySchema(): void
    {
        $formRequestClass = new class extends FormRequest {
            public function authorize(): bool { return true; }
            public function rules(): array { return []; }
        };

        $schema = $this->schemaBuilder->fromFormRequest($formRequestClass::class);

        $this->assertSame('object', $schema['type']);
        $this->assertSame([], $schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testRuleObjectIsHandledGracefully(): void
    {
        $property = $this->schemaBuilder->rulesToProperty([Rule::in(['a', 'b'])]);

        // Rule::in produces an enum; the exact value representation may vary by Laravel version
        $this->assertArrayHasKey('enum', $property);
        $this->assertIsArray($property['enum']);
        $this->assertNotEmpty($property['enum']);
    }

    public function testNestedRulesAreIgnored(): void
    {
        $formRequestClass = new class extends FormRequest {
            public function authorize(): bool { return true; }
            public function rules(): array
            {
                return [
                    'items.*.name' => 'required|string',
                ];
            }
        };

        $schema = $this->schemaBuilder->fromFormRequest($formRequestClass::class);

        $this->assertArrayNotHasKey('items.*.name', $schema['properties']);
        $this->assertSame([], $schema['properties']);
    }

    public function testMultipleRulesOnSameFieldAreMerged(): void
    {
        $property = $this->schemaBuilder->rulesToProperty('required|string|min:3|max:255');

        $this->assertSame('string', $property['type']);
        $this->assertSame(3, $property['minLength']);
        $this->assertSame(255, $property['maxLength']);
    }

    public function testMultipleRulesFieldIsInRequired(): void
    {
        $formRequestClass = new class extends FormRequest {
            public function authorize(): bool { return true; }
            public function rules(): array
            {
                return [
                    'title' => 'required|string|min:3|max:255',
                ];
            }
        };

        $schema = $this->schemaBuilder->fromFormRequest($formRequestClass::class);

        $this->assertContains('title', $schema['required']);
        $this->assertSame('string', $schema['properties']['title']['type']);
        $this->assertSame(3, $schema['properties']['title']['minLength']);
        $this->assertSame(255, $schema['properties']['title']['maxLength']);
    }

    public function testConfirmedRuleIsIgnored(): void
    {
        $formRequestClass = new class extends FormRequest {
            public function authorize(): bool { return true; }
            public function rules(): array
            {
                return [
                    'password' => 'required|string|confirmed',
                ];
            }
        };

        $schema = $this->schemaBuilder->fromFormRequest($formRequestClass::class);

        $this->assertArrayHasKey('password', $schema['properties']);
        $this->assertArrayNotHasKey('password_confirmation', $schema['properties']);
    }

    public function testArrayRuleProducesArrayType(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['array']);

        $this->assertSame('array', $property['type']);
    }

    public function testSometimesRuleIsNotRequired(): void
    {
        $formRequestClass = new class extends FormRequest {
            public function authorize(): bool { return true; }
            public function rules(): array
            {
                return [
                    'nickname' => 'sometimes|string',
                ];
            }
        };

        $schema = $this->schemaBuilder->fromFormRequest($formRequestClass::class);

        $this->assertArrayHasKey('nickname', $schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }
}
