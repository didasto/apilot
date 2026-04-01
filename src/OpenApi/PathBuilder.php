<?php

declare(strict_types=1);

namespace Didasto\Apilot\OpenApi;

use Illuminate\Support\Str;
use Didasto\Apilot\Attributes\OpenApiMeta;
use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Filters\FilterSet;
use Didasto\Apilot\Routing\RouteEntry;

class PathBuilder
{
    public function __construct(
        protected readonly SchemaBuilder $schemaBuilder,
    ) {}

    /**
     * Erzeugt alle Path-Items für einen RouteEntry.
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildPaths(RouteEntry $entry): array
    {
        $paths  = [];
        $prefix = '/' . trim($entry->prefix, '/');

        $collectionPath = $prefix . '/' . $entry->resourceName;
        $itemPath       = $prefix . '/' . $entry->resourceName . '/{id}';

        $schemaName      = Str::singular(Str::studly($entry->resourceName));
        $tag             = $this->getTag($entry->controllerClass, $entry->resourceName);
        $security        = $this->buildSecurity($entry->middleware);
        $deprecated      = $this->getDeprecated($entry->controllerClass);
        $formRequestClass = $this->getControllerProperty($entry->controllerClass, 'formRequestClass');
        $allowedFilters  = $this->getControllerProperty($entry->controllerClass, 'allowedFilters') ?? [];
        $allowedSorts    = $this->getControllerProperty($entry->controllerClass, 'allowedSorts') ?? [];

        $storeRequestClass  = $this->getControllerProperty($entry->controllerClass, 'storeRequestClass');
        $updateRequestClass = $this->getControllerProperty($entry->controllerClass, 'updateRequestClass');

        $storeClass  = $storeRequestClass ?? $formRequestClass;
        $updateClass = $updateRequestClass ?? $formRequestClass;

        if ($storeClass !== null && $updateClass !== null && $storeClass === $updateClass) {
            $storeSchemaRef  = $schemaName . 'Request';
            $updateSchemaRef = $schemaName . 'Request';
        } else {
            $storeSchemaRef  = $storeClass !== null  ? $schemaName . 'StoreRequest'  : null;
            $updateSchemaRef = $updateClass !== null ? $schemaName . 'UpdateRequest' : null;
        }

        $base = ['tags' => [$tag]];

        if ($deprecated) {
            $base['deprecated'] = true;
        }

        if ($security !== null) {
            $base['security'] = $security;
        }

        $collectionOps = [];
        $itemOps       = [];

        foreach ($entry->actions as $action) {
            switch ($action) {
                case 'index':
                    $collectionOps['get'] = $this->buildIndexOp($entry, $schemaName, $base, $allowedFilters, $allowedSorts);
                    break;
                case 'store':
                    $collectionOps['post'] = $this->buildStoreOp($entry, $schemaName, $base, $storeSchemaRef);
                    break;
                case 'show':
                    $itemOps['get'] = $this->buildShowOp($entry, $schemaName, $base);
                    break;
                case 'update':
                    $itemOps['put'] = $this->buildUpdateOp($entry, $schemaName, $base, $updateSchemaRef);
                    break;
                case 'destroy':
                    $itemOps['delete'] = $this->buildDestroyOp($entry, $schemaName, $base);
                    break;
            }
        }

        if (!empty($collectionOps)) {
            $paths[$collectionPath] = $collectionOps;
        }

        if (!empty($itemOps)) {
            $paths[$itemPath] = $itemOps;
        }

        return $paths;
    }

    /**
     * @param array<string, AllowedFilter>|array<int, string> $allowedFilters
     * @param array<int, string>                              $allowedSorts
     * @return array<string, mixed>
     */
    protected function buildIndexOp(RouteEntry $entry, string $schemaName, array $base, array $allowedFilters, array $allowedSorts): array
    {
        $singularName = Str::singular(Str::studly($entry->resourceName));
        $pluralName   = Str::studly($entry->resourceName);

        $parameters = [
            [
                'name'     => 'page',
                'in'       => 'query',
                'required' => false,
                'schema'   => ['type' => 'integer', 'default' => 1],
            ],
            [
                'name'     => 'per_page',
                'in'       => 'query',
                'required' => false,
                'schema'   => [
                    'type'    => 'integer',
                    'default' => 15,
                    'maximum' => 100,
                ],
            ],
        ];

        $parameters[] = $this->buildSortParameter($allowedSorts);

        foreach ($this->buildFilterParameters($allowedFilters) as $filterParam) {
            $parameters[] = $filterParam;
        }

        $wrapper    = config('apilot.response_wrapper');
        $itemsKey   = $wrapper ?? 'items';

        return array_merge($base, [
            'summary'     => 'List all ' . $pluralName,
            'operationId' => $entry->resourceName . '.index',
            'parameters'  => $parameters,
            'responses'   => [
                '200' => [
                    'description' => 'Paginated list of ' . $pluralName,
                    'content'     => [
                        'application/json' => [
                            'schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    $itemsKey => [
                                        'type'  => 'array',
                                        'items' => ['$ref' => '#/components/schemas/' . $schemaName . 'Response'],
                                    ],
                                    'meta'    => ['$ref' => '#/components/schemas/PaginationMeta'],
                                    'links'   => ['$ref' => '#/components/schemas/PaginationLinks'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function buildShowOp(RouteEntry $entry, string $schemaName, array $base): array
    {
        $singularName = Str::singular(Str::studly($entry->resourceName));
        $wrapper      = config('apilot.response_wrapper');

        if ($wrapper === null) {
            $responseSchema = ['$ref' => '#/components/schemas/' . $schemaName . 'Response'];
        } else {
            $responseSchema = [
                'type'       => 'object',
                'properties' => [
                    $wrapper => ['$ref' => '#/components/schemas/' . $schemaName . 'Response'],
                ],
            ];
        }

        return array_merge($base, [
            'summary'     => 'Get a single ' . $singularName,
            'operationId' => $entry->resourceName . '.show',
            'parameters'  => [
                [
                    'name'     => 'id',
                    'in'       => 'path',
                    'required' => true,
                    'schema'   => ['type' => 'string'],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Single ' . $singularName . ' resource',
                    'content'     => [
                        'application/json' => [
                            'schema' => $responseSchema,
                        ],
                    ],
                ],
                '404' => $this->notFoundResponse(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function buildStoreOp(RouteEntry $entry, string $schemaName, array $base, ?string $storeSchemaRef): array
    {
        $singularName = Str::singular(Str::studly($entry->resourceName));
        $wrapper      = config('apilot.response_wrapper');

        if ($wrapper === null) {
            $responseSchema = ['$ref' => '#/components/schemas/' . $schemaName . 'Response'];
        } else {
            $responseSchema = [
                'type'       => 'object',
                'properties' => [
                    $wrapper => ['$ref' => '#/components/schemas/' . $schemaName . 'Response'],
                ],
            ];
        }

        return array_merge($base, [
            'summary'     => 'Create a new ' . $singularName,
            'operationId' => $entry->resourceName . '.store',
            'requestBody' => $this->buildRequestBody($storeSchemaRef),
            'responses'   => [
                '201' => [
                    'description' => 'Created ' . $singularName . ' resource',
                    'content'     => [
                        'application/json' => [
                            'schema' => $responseSchema,
                        ],
                    ],
                ],
                '422' => $this->validationErrorResponse(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function buildUpdateOp(RouteEntry $entry, string $schemaName, array $base, ?string $updateSchemaRef): array
    {
        $singularName = Str::singular(Str::studly($entry->resourceName));
        $wrapper      = config('apilot.response_wrapper');

        if ($wrapper === null) {
            $responseSchema = ['$ref' => '#/components/schemas/' . $schemaName . 'Response'];
        } else {
            $responseSchema = [
                'type'       => 'object',
                'properties' => [
                    $wrapper => ['$ref' => '#/components/schemas/' . $schemaName . 'Response'],
                ],
            ];
        }

        return array_merge($base, [
            'summary'     => 'Update an existing ' . $singularName,
            'operationId' => $entry->resourceName . '.update',
            'parameters'  => [
                [
                    'name'     => 'id',
                    'in'       => 'path',
                    'required' => true,
                    'schema'   => ['type' => 'string'],
                ],
            ],
            'requestBody' => $this->buildRequestBody($updateSchemaRef),
            'responses'   => [
                '200' => [
                    'description' => 'Updated ' . $singularName . ' resource',
                    'content'     => [
                        'application/json' => [
                            'schema' => $responseSchema,
                        ],
                    ],
                ],
                '404' => $this->notFoundResponse(),
                '422' => $this->validationErrorResponse(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function buildDestroyOp(RouteEntry $entry, string $schemaName, array $base): array
    {
        $singularName = Str::singular(Str::studly($entry->resourceName));

        return array_merge($base, [
            'summary'     => 'Delete a ' . $singularName,
            'operationId' => $entry->resourceName . '.destroy',
            'parameters'  => [
                [
                    'name'     => 'id',
                    'in'       => 'path',
                    'required' => true,
                    'schema'   => ['type' => 'string'],
                ],
            ],
            'responses' => [
                '204' => ['description' => 'Successfully deleted'],
                '404' => $this->notFoundResponse(),
            ],
        ]);
    }

    /**
     * @param array<string, AllowedFilter|array<int, AllowedFilter>|class-string>|array<int, string> $allowedFilters
     * @return array<int, array<string, mixed>>
     */
    protected function buildFilterParameters(array $allowedFilters): array
    {
        $params = [];

        foreach ($allowedFilters as $field => $filterConfig) {
            if (is_int($field)) {
                // Legacy ServiceCrudController-Stil: ['name', 'slug']
                $params[] = [
                    'name'        => 'filter[' . $filterConfig . ']',
                    'in'          => 'query',
                    'required'    => false,
                    'description' => 'Filter by ' . $filterConfig,
                    'schema'      => ['type' => 'string'],
                ];
                continue;
            }

            $operators = $this->resolveOperatorsForOpenApi($filterConfig);

            if (count($operators) === 1) {
                // Einzelner Operator → Legacy-Stil ohne Operator-Suffix
                $operator = $operators[0];
                $params[] = [
                    'name'        => 'filter[' . $field . ']',
                    'in'          => 'query',
                    'required'    => false,
                    'description' => 'Filter by ' . $field . ' (' . $this->operatorDescription($operator) . ')',
                    'schema'      => ['type' => 'string'],
                ];
            } else {
                // Mehrere Operatoren → Operator-Suffix pro Operator
                foreach ($operators as $operator) {
                    $param = [
                        'name'        => 'filter[' . $field . '][' . $operator->queryKey() . ']',
                        'in'          => 'query',
                        'required'    => false,
                        'description' => 'Filter by ' . $field . ' (' . $this->operatorDescription($operator) . ')',
                        'schema'      => ['type' => 'string'],
                    ];

                    if ($operator === AllowedFilter::IN || $operator === AllowedFilter::NOT_IN) {
                        $param['schema']['example'] = '1,2,3';
                    } elseif ($operator === AllowedFilter::BETWEEN || $operator === AllowedFilter::NOT_BETWEEN) {
                        $param['schema']['example'] = '2025-01-01,2025-12-31';
                    }

                    $params[] = $param;
                }
            }
        }

        return $params;
    }

    /**
     * Löst die Operatoren für einen Filter-Config-Wert auf (für OpenAPI-Generierung).
     *
     * @return array<int, AllowedFilter>
     */
    private function resolveOperatorsForOpenApi(mixed $filterConfig): array
    {
        if ($filterConfig instanceof AllowedFilter) {
            return [$filterConfig];
        }

        if (is_string($filterConfig) && class_exists($filterConfig) && is_subclass_of($filterConfig, FilterSet::class)) {
            return (new $filterConfig())->filters();
        }

        if (is_array($filterConfig)) {
            return $filterConfig;
        }

        return [];
    }

    /**
     * Gibt eine lesbare Beschreibung für einen Operator zurück.
     */
    private function operatorDescription(AllowedFilter $operator): string
    {
        return match ($operator) {
            AllowedFilter::EXACT, AllowedFilter::EQUALS     => 'equals',
            AllowedFilter::NOT_EQUALS                        => 'not equals',
            AllowedFilter::IN                                => 'in list, comma-separated',
            AllowedFilter::NOT_IN                            => 'not in list, comma-separated',
            AllowedFilter::GT                                => 'greater than',
            AllowedFilter::LT                                => 'less than',
            AllowedFilter::GTE                               => 'greater than or equal',
            AllowedFilter::LTE                               => 'less than or equal',
            AllowedFilter::PARTIAL, AllowedFilter::LIKE      => 'contains (partial match)',
            AllowedFilter::NOT_LIKE                          => 'does not contain',
            AllowedFilter::BETWEEN                           => 'between two values, comma-separated',
            AllowedFilter::NOT_BETWEEN                       => 'not between two values, comma-separated',
            AllowedFilter::IS_NULL                           => 'is null',
            AllowedFilter::IS_NOT_NULL                       => 'is not null',
            AllowedFilter::SCOPE                             => 'scope filter',
        };
    }

    /**
     * @param array<int, string> $allowedSorts
     * @return array<string, mixed>
     */
    protected function buildSortParameter(array $allowedSorts): array
    {
        if (!empty($allowedSorts)) {
            $fields      = implode(', ', $allowedSorts);
            $description = 'Sort by: ' . $fields . '. Prefix with - for descending.';
            $examples    = [
                'ascending'  => ['value' => $allowedSorts[0]],
                'descending' => ['value' => '-' . $allowedSorts[0]],
            ];
            if (count($allowedSorts) >= 2) {
                $examples['multiple'] = ['value' => $allowedSorts[0] . ',-' . $allowedSorts[1]];
            }
        } else {
            $description = 'Sort field. Prefix with - for descending.';
            $examples    = [];
        }

        $param = [
            'name'        => 'sort',
            'in'          => 'query',
            'required'    => false,
            'description' => $description,
            'schema'      => ['type' => 'string'],
        ];

        if (!empty($examples)) {
            $param['examples'] = $examples;
        }

        return $param;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestBody(?string $schemaRefName): array
    {
        if ($schemaRefName !== null) {
            $schema = ['$ref' => '#/components/schemas/' . $schemaRefName];
        } else {
            $schema = ['type' => 'object', 'additionalProperties' => true];
        }

        return [
            'required' => true,
            'content'  => [
                'application/json' => ['schema' => $schema],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function notFoundResponse(): array
    {
        return [
            'description' => 'Resource not found',
            'content'     => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function validationErrorResponse(): array
    {
        return [
            'description' => 'Validation error',
            'content'     => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                ],
            ],
        ];
    }

    /**
     * Liest den Tag-Namen aus dem OpenApiMeta-Attribut oder leitet ihn vom Ressource-Namen ab.
     */
    protected function getTag(string $controllerClass, string $resourceName): string
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);
            $attributes = $reflection->getAttributes(OpenApiMeta::class);

            if (!empty($attributes)) {
                $meta = $attributes[0]->newInstance();

                if ($meta->tag !== null) {
                    return $meta->tag;
                }
            }
        } catch (\Throwable) {
        }

        return Str::studly($resourceName);
    }

    /**
     * Prüft ob der Controller das deprecated-Flag gesetzt hat.
     */
    protected function getDeprecated(string $controllerClass): bool
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);
            $attributes = $reflection->getAttributes(OpenApiMeta::class);

            if (!empty($attributes)) {
                $meta = $attributes[0]->newInstance();

                return $meta->deprecated;
            }
        } catch (\Throwable) {
        }

        return false;
    }

    /**
     * Gibt das Security-Array zurück, wenn Auth-Middleware vorhanden ist.
     *
     * @param array<int, string>         $middleware
     * @return array<int, array<string, array<int, mixed>>>|null
     */
    protected function buildSecurity(array $middleware): ?array
    {
        $defaultSecurity = config('apilot.openapi.default_security', 'bearer');

        if ($defaultSecurity === null) {
            return null;
        }

        foreach ($middleware as $m) {
            if (str_starts_with($m, 'auth')) {
                return match ($defaultSecurity) {
                    'bearer' => [['BearerAuth' => []]],
                    'basic'  => [['BasicAuth' => []]],
                    'apiKey' => [['ApiKeyAuth' => []]],
                    default  => null,
                };
            }
        }

        return null;
    }

    /**
     * Liest eine Controller-Property via Reflection (nur Properties mit Default-Werten).
     */
    protected function getControllerProperty(string $controllerClass, string $property): mixed
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);

            if (!$reflection->hasProperty($property)) {
                return null;
            }

            $prop = $reflection->getProperty($property);

            if ($prop->hasDefaultValue()) {
                return $prop->getDefaultValue();
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
