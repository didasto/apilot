<?php

declare(strict_types=1);

namespace Didasto\Apilot\OpenApi;

use Illuminate\Http\Request;
use Didasto\Apilot\Attributes\OpenApiProperty;

class SchemaBuilder
{
    /**
     * Erzeugt ein OpenAPI-Schema aus einer FormRequest-Klasse.
     *
     * @param string $formRequestClass — Vollqualifizierter Klassenname
     * @return array<string, mixed>
     */
    public function fromFormRequest(string $formRequestClass): array
    {
        try {
            $instance = new $formRequestClass();
            $rules    = $instance->rules();
        } catch (\Throwable) {
            return ['type' => 'object', 'additionalProperties' => true];
        }

        $overrides  = $this->getOpenApiPropertyOverrides($formRequestClass);
        $properties = [];
        $required   = [];

        foreach ($rules as $field => $rule) {
            // Verschachtelte Felder ignorieren (z.B. 'items.*.name')
            if (str_contains((string) $field, '.')) {
                continue;
            }

            $property = $this->rulesToProperty($rule);

            // Overrides aus OpenApiProperty-Attribut einmischen
            if (isset($overrides[$field])) {
                $property = array_merge($property, $overrides[$field]);
            }

            $properties[$field] = $property;

            // Required-Felder sammeln
            $normalizedRules = $this->normalizeRules($rule);
            if (in_array('required', $normalizedRules, true)) {
                $required[] = $field;
            }
        }

        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Ermittelt die Schema-Namen und -Klassen für eine Resource.
     *
     * @return array<string, string> — Key = Schema-Name, Value = FormRequest-Klassenname
     */
    public function resolveRequestSchemas(
        string $resourceName,
        ?string $formRequestClass,
        ?string $storeRequestClass,
        ?string $updateRequestClass,
    ): array {
        $schemas = [];

        $storeClass  = $storeRequestClass ?? $formRequestClass;
        $updateClass = $updateRequestClass ?? $formRequestClass;

        if ($storeClass !== null && $updateClass !== null && $storeClass === $updateClass) {
            $schemas["{$resourceName}Request"] = $storeClass;
        } else {
            if ($storeClass !== null) {
                $schemas["{$resourceName}StoreRequest"] = $storeClass;
            }
            if ($updateClass !== null) {
                $schemas["{$resourceName}UpdateRequest"] = $updateClass;
            }
        }

        return $schemas;
    }

    /**
     * Erzeugt ein OpenAPI-Schema aus visibleFields/hiddenFields-Konfiguration.
     *
     * @param array<int, string> $visibleFields
     * @param array<int, string> $hiddenFields
     * @param string|null        $modelClass
     * @return array<string, mixed>
     */
    public function fromFieldVisibility(array $visibleFields, array $hiddenFields, ?string $modelClass): array
    {
        $allFields = $this->getModelFields($modelClass);

        if (empty($allFields)) {
            // No model fields derivable — use visibleFields as property list if set
            if (!empty($visibleFields)) {
                $properties = [];
                foreach ($visibleFields as $field) {
                    $properties[$field] = ['type' => 'string'];
                }
                return ['type' => 'object', 'properties' => $properties];
            }
            return ['type' => 'object', 'additionalProperties' => true];
        }

        // Apply whitelist
        if (!empty($visibleFields)) {
            $allFields = array_intersect_key($allFields, array_flip($visibleFields));
        }

        // Apply blacklist
        if (!empty($hiddenFields)) {
            $allFields = array_diff_key($allFields, array_flip($hiddenFields));
        }

        if (empty($allFields)) {
            return ['type' => 'object', 'additionalProperties' => true];
        }

        $properties = [];
        foreach ($allFields as $field => $type) {
            $properties[$field] = ['type' => $type];
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * Ermittelt die Felder eines Models mit ihren OpenAPI-Typen.
     *
     * @param string|null $modelClass
     * @return array<string, string>
     */
    private function getModelFields(?string $modelClass): array
    {
        if ($modelClass === null || !class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass();
            $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];
            $fillable = method_exists($model, 'getFillable') ? $model->getFillable() : [];

            $fields = ['id' => 'integer'];

            foreach ($fillable as $field) {
                $type = 'string';
                if (isset($casts[$field])) {
                    $type = $this->castToOpenApiType($casts[$field]);
                }
                $fields[$field] = $type;
            }

            $fields['created_at'] = 'string';
            $fields['updated_at'] = 'string';

            return $fields;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Konvertiert einen Laravel-Cast-Typ in einen OpenAPI-Typ.
     */
    private function castToOpenApiType(string $cast): string
    {
        return match (true) {
            str_starts_with($cast, 'decimal') => 'number',
            in_array($cast, ['int', 'integer'], true) => 'integer',
            in_array($cast, ['float', 'double', 'real'], true) => 'number',
            in_array($cast, ['bool', 'boolean'], true) => 'boolean',
            in_array($cast, ['array', 'json', 'collection'], true) => 'array',
            default => 'string',
        };
    }

    /**
     * Erzeugt ein OpenAPI-Schema aus einer Resource-Klasse.
     *
     * @param string      $resourceClass
     * @param string|null $modelClass
     * @return array<string, mixed>
     */
    public function fromResource(string $resourceClass, ?string $modelClass = null): array
    {
        if ($modelClass === null) {
            return ['type' => 'object', 'additionalProperties' => true];
        }

        try {
            $model    = new $modelClass();
            $resource = new $resourceClass($model);
            $toArray  = $resource->toArray(new Request());

            if (!is_array($toArray)) {
                return ['type' => 'object', 'additionalProperties' => true];
            }

            $properties = [];
            foreach ($toArray as $key => $value) {
                $properties[$key] = $this->inferTypeFromValue($value);
            }

            return [
                'type'       => 'object',
                'properties' => $properties,
            ];
        } catch (\Throwable) {
            return ['type' => 'object', 'additionalProperties' => true];
        }
    }

    /**
     * Konvertiert Laravel-Validation-Rules zu einer OpenAPI-Property-Definition.
     *
     * @param array<int, mixed>|string $rules
     * @return array<string, mixed>
     */
    public function rulesToProperty(array|string $rules): array
    {
        $ruleList = $this->normalizeRules($rules);

        $type      = 'string';
        $format    = null;
        $nullable  = false;
        $isNumeric = false;
        $max       = null;
        $min       = null;
        $enum      = null;

        foreach ($ruleList as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            [$ruleName, $ruleValue] = $this->splitRule($rule);

            switch ($ruleName) {
                case 'integer':
                    $type      = 'integer';
                    $isNumeric = true;
                    break;
                case 'numeric':
                    $type      = 'number';
                    $isNumeric = true;
                    break;
                case 'boolean':
                    $type = 'boolean';
                    break;
                case 'array':
                    $type = 'array';
                    break;
                case 'date':
                    $type   = 'string';
                    $format = 'date';
                    break;
                case 'date_format':
                    if ($ruleValue === 'Y-m-d') {
                        $type   = 'string';
                        $format = 'date';
                    }
                    break;
                case 'email':
                    $type   = 'string';
                    $format = 'email';
                    break;
                case 'url':
                    $type   = 'string';
                    $format = 'uri';
                    break;
                case 'uuid':
                    $type   = 'string';
                    $format = 'uuid';
                    break;
                case 'nullable':
                    $nullable = true;
                    break;
                case 'max':
                    if ($ruleValue !== null) {
                        $max = (int) $ruleValue;
                    }
                    break;
                case 'min':
                    if ($ruleValue !== null) {
                        $min = (int) $ruleValue;
                    }
                    break;
                case 'in':
                    if ($ruleValue !== null) {
                        $enum = explode(',', $ruleValue);
                    }
                    break;
            }
        }

        $property = ['type' => $type];

        if ($format !== null) {
            $property['format'] = $format;
        }

        if ($nullable) {
            $property['nullable'] = true;
        }

        if ($max !== null) {
            $property[$isNumeric ? 'maximum' : 'maxLength'] = $max;
        }

        if ($min !== null) {
            $property[$isNumeric ? 'minimum' : 'minLength'] = $min;
        }

        if ($enum !== null) {
            $property['enum'] = $enum;
        }

        return $property;
    }

    /**
     * Normalisiert Rules auf ein Array von Strings.
     *
     * @param array<int, mixed>|string $rules
     * @return array<int, string>
     */
    protected function normalizeRules(array|string $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        $normalized = [];
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $normalized[] = $rule;
            } elseif (is_object($rule)) {
                try {
                    $str = (string) $rule;
                    if ($str !== '') {
                        $normalized[] = $str;
                    }
                } catch (\Throwable) {
                    // Unbekannte Rule-Objekte ignorieren
                }
            }
        }

        return $normalized;
    }

    /**
     * Teilt eine Rule-String in Name und optionalen Wert.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function splitRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);

        return [strtolower($parts[0]), $parts[1] ?? null];
    }

    /**
     * Liest OpenApiProperty-Overrides von der rules()-Methode einer FormRequest-Klasse.
     *
     * @param string $formRequestClass
     * @return array<string, array<string, mixed>>
     */
    protected function getOpenApiPropertyOverrides(string $formRequestClass): array
    {
        try {
            $reflection = new \ReflectionClass($formRequestClass);

            if (!$reflection->hasMethod('rules')) {
                return [];
            }

            $method     = $reflection->getMethod('rules');
            $attributes = $method->getAttributes(OpenApiProperty::class);

            if (empty($attributes)) {
                return [];
            }

            $attr = $attributes[0]->newInstance();

            return $attr->properties;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Leitet den OpenAPI-Typ aus einem PHP-Wert ab.
     *
     * @return array{type: string}
     */
    protected function inferTypeFromValue(mixed $value): array
    {
        return match (true) {
            is_int($value)   => ['type' => 'integer'],
            is_float($value) => ['type' => 'number'],
            is_bool($value)  => ['type' => 'boolean'],
            is_array($value) => ['type' => 'array'],
            default          => ['type' => 'string'],
        };
    }
}
