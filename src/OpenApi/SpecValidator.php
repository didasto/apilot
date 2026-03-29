<?php

declare(strict_types=1);

namespace Didasto\Apilot\OpenApi;

class SpecValidator
{
    /**
     * Validates the basic structure of an OpenAPI spec.
     *
     * @param array<string, mixed> $spec
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(array $spec): array
    {
        $errors = [];

        // Required top-level fields
        if (!isset($spec['openapi']) || !str_starts_with((string) $spec['openapi'], '3.0')) {
            $errors[] = 'Missing or invalid "openapi" version. Must start with "3.0".';
        }

        if (!isset($spec['info']) || !is_array($spec['info'])) {
            $errors[] = 'Missing "info" object.';
        } else {
            if (!isset($spec['info']['title'])) {
                $errors[] = 'Missing "info.title".';
            }
            if (!isset($spec['info']['version'])) {
                $errors[] = 'Missing "info.version".';
            }
        }

        if (!isset($spec['paths']) || !is_array($spec['paths'])) {
            $errors[] = 'Missing "paths" object.';
        } else {
            foreach ($spec['paths'] as $path => $pathItem) {
                $this->validatePathItem((string) $path, (array) $pathItem, $errors);
            }
        }

        if (isset($spec['components']['schemas'])) {
            foreach ($spec['components']['schemas'] as $name => $schema) {
                $this->validateSchema((string) $name, (array) $schema, $errors);
            }
        }

        // Check that all $ref references point to existing schemas
        $this->validateRefs($spec, $errors);

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, string> $errors
     */
    protected function validatePathItem(string $path, array $pathItem, array &$errors): void
    {
        $validMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];

        foreach ($pathItem as $method => $operation) {
            if (!in_array($method, $validMethods, true)) {
                continue; // Could be 'parameters', 'summary', etc.
            }

            if (!is_array($operation)) {
                continue;
            }

            if (!isset($operation['responses']) || empty($operation['responses'])) {
                $errors[] = sprintf('Operation %s %s is missing "responses".', strtoupper($method), $path);
            }

            if (isset($operation['parameters']) && is_array($operation['parameters'])) {
                foreach ($operation['parameters'] as $i => $param) {
                    if (!is_array($param) || !isset($param['name']) || !isset($param['in'])) {
                        $errors[] = sprintf('Parameter #%d in %s %s missing "name" or "in".', $i, strtoupper($method), $path);
                    }
                }
            }
        }
    }

    /**
     * @param array<int, string> $errors
     */
    protected function validateSchema(string $name, array $schema, array &$errors): void
    {
        if (
            !isset($schema['type'])
            && !isset($schema['$ref'])
            && !isset($schema['allOf'])
            && !isset($schema['oneOf'])
            && !isset($schema['anyOf'])
        ) {
            $errors[] = sprintf('Schema "%s" is missing "type".', $name);
        }
    }

    /**
     * Collect all $ref values in the spec and check that the referenced schemas exist.
     *
     * @param array<string, mixed> $spec
     * @param array<int, string> $errors
     */
    protected function validateRefs(array $spec, array &$errors): void
    {
        $refs = $this->collectRefs($spec);
        $schemas = array_keys($spec['components']['schemas'] ?? []);

        foreach ($refs as $ref) {
            if (str_starts_with($ref, '#/components/schemas/')) {
                $schemaName = str_replace('#/components/schemas/', '', $ref);
                if (!in_array($schemaName, $schemas, true)) {
                    $errors[] = sprintf('Broken $ref: "%s" references non-existent schema "%s".', $ref, $schemaName);
                }
            }
        }
    }

    /**
     * Recursively collect all $ref values from a nested array.
     *
     * @param array<mixed> $data
     * @return array<int, string>
     */
    protected function collectRefs(array $data): array
    {
        $refs = [];

        foreach ($data as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                $refs[] = $value;
            } elseif (is_array($value)) {
                $refs = array_merge($refs, $this->collectRefs($value));
            }
        }

        return $refs;
    }
}
