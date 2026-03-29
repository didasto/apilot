<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Didasto\Apilot\Attributes\OpenApiProperty;
use Didasto\Apilot\OpenApi\SchemaBuilder;
use PHPUnit\Framework\TestCase;

class SchemaBuilderTest extends TestCase
{
    protected SchemaBuilder $schemaBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaBuilder = new SchemaBuilder();
    }

    public function testStringRuleProducesStringType(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['string']);
        $this->assertEquals('string', $property['type']);
    }

    public function testIntegerRuleProducesIntegerType(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['integer']);
        $this->assertEquals('integer', $property['type']);
    }

    public function testNumericRuleProducesNumberType(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['numeric']);
        $this->assertEquals('number', $property['type']);
    }

    public function testBooleanRuleProducesBooleanType(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['boolean']);
        $this->assertEquals('boolean', $property['type']);
    }

    public function testEmailRuleProducesEmailFormat(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['email']);
        $this->assertEquals('string', $property['type']);
        $this->assertEquals('email', $property['format']);
    }

    public function testMaxRuleProducesMaxLength(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['string', 'max:255']);
        $this->assertEquals(255, $property['maxLength']);
    }

    public function testMinRuleProducesMinLength(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['string', 'min:3']);
        $this->assertEquals(3, $property['minLength']);
    }

    public function testMaxRuleOnIntegerProducesMaximum(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['integer', 'max:150']);
        $this->assertEquals(150, $property['maximum']);
        $this->assertArrayNotHasKey('maxLength', $property);
    }

    public function testInRuleProducesEnum(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['in:a,b,c']);
        $this->assertEquals(['a', 'b', 'c'], $property['enum']);
    }

    public function testRequiredRuleAddsToRequiredArray(): void
    {
        $schema = $this->schemaBuilder->fromFormRequest(SimpleFormRequest::class);
        $this->assertContains('title', $schema['required']);
        $this->assertNotContains('body', $schema['required'] ?? []);
    }

    public function testNullableRuleSetsNullable(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['nullable', 'string']);
        $this->assertTrue($property['nullable']);
    }

    public function testDateRuleProducesDateFormat(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['date']);
        $this->assertEquals('string', $property['type']);
        $this->assertEquals('date', $property['format']);
    }

    public function testUuidRuleProducesUuidFormat(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['uuid']);
        $this->assertEquals('string', $property['type']);
        $this->assertEquals('uuid', $property['format']);
    }

    public function testPipeDelimitedRulesAreParsedCorrectly(): void
    {
        $property = $this->schemaBuilder->rulesToProperty('required|string|max:100');
        $this->assertEquals('string', $property['type']);
        $this->assertEquals(100, $property['maxLength']);
    }

    public function testArrayRulesAreParsedCorrectly(): void
    {
        $property = $this->schemaBuilder->rulesToProperty(['required', 'string', 'max:100']);
        $this->assertEquals('string', $property['type']);
        $this->assertEquals(100, $property['maxLength']);
    }

    public function testOpenApiPropertyAttributeOverridesValues(): void
    {
        $schema = $this->schemaBuilder->fromFormRequest(FormRequestWithPropertyAttribute::class);

        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertEquals('The title of the post', $schema['properties']['title']['description']);
        $this->assertEquals('My First Post', $schema['properties']['title']['example']);
    }
}

// ---------------------------------------------------------------------------
// Fixture-Klassen (nur für diesen Test)
// ---------------------------------------------------------------------------

class SimpleFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body'  => ['nullable', 'string'],
        ];
    }
}

class FormRequestWithPropertyAttribute extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[OpenApiProperty(properties: [
        'title' => ['description' => 'The title of the post', 'example' => 'My First Post'],
    ])]
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body'  => ['nullable', 'string'],
        ];
    }
}
