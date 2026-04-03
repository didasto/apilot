<?php

declare(strict_types=1);

namespace Didasto\Apilot\OpenApi;

use Illuminate\Support\Str;
use Didasto\Apilot\Http\Resources\DefaultResource;
use Didasto\Apilot\Routing\RouteRegistry;

class OpenApiGenerator
{
    public function __construct(
        protected readonly RouteRegistry $registry,
        protected readonly SchemaBuilder $schemaBuilder,
        protected readonly PathBuilder $pathBuilder,
        protected readonly InfoBuilder $infoBuilder,
    ) {}

    /**
     * Generiert die komplette OpenAPI-Spec als Array.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $spec = [
            'openapi'    => '3.0.3',
            'info'       => $this->infoBuilder->build(),
            'servers'    => $this->buildServers(),
            'paths'      => $this->buildPaths(),
            'components' => [
                'schemas'         => $this->buildSchemas(),
                'securitySchemes' => $this->buildSecuritySchemes(),
            ],
        ];

        if (empty($spec['components']['securitySchemes'])) {
            unset($spec['components']['securitySchemes']);
        }

        return $spec;
    }

    /**
     * Generiert die Spec als JSON-String.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->generate(), $flags);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function buildServers(): array
    {
        $servers = config('apilot.openapi.servers', []);

        if (!empty($servers)) {
            return $servers;
        }

        $appUrl = config('app.url', 'http://localhost');

        return [['url' => $appUrl]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPaths(): array
    {
        $paths = [];

        foreach ($this->registry->all() as $entry) {
            $entryPaths = $this->pathBuilder->buildPaths($entry);
            $paths      = array_merge($paths, $entryPaths);
        }

        return $paths;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function buildSchemas(): array
    {
        $schemas = $this->buildCommonSchemas();

        foreach ($this->registry->all() as $entry) {
            $schemaName = Str::singular(Str::studly($entry->resourceName));

            // Request-Schemas aus FormRequests
            $formRequestClass   = $this->getControllerProperty($entry->controllerClass, 'formRequestClass');
            $storeRequestClass  = $this->getControllerProperty($entry->controllerClass, 'storeRequestClass');
            $updateRequestClass = $this->getControllerProperty($entry->controllerClass, 'updateRequestClass');

            $requestSchemas = $this->schemaBuilder->resolveRequestSchemas(
                $schemaName,
                $formRequestClass,
                $storeRequestClass,
                $updateRequestClass,
            );

            foreach ($requestSchemas as $name => $class) {
                $schemas[$name] = $this->schemaBuilder->fromFormRequest($class);
            }

            // Response-Schema aus Resource oder Field Visibility
            $resourceClass = $this->getControllerProperty($entry->controllerClass, 'resourceClass');
            $modelClass    = $this->getControllerProperty($entry->controllerClass, 'model');

            if ($resourceClass !== null && $resourceClass !== DefaultResource::class) {
                $schemas[$schemaName . 'Response'] = $this->schemaBuilder->fromResource($resourceClass, $modelClass);
            } else {
                $visibleFields = $this->getControllerProperty($entry->controllerClass, 'visibleFields') ?? [];
                $hiddenFields  = $this->getControllerProperty($entry->controllerClass, 'hiddenFields') ?? [];

                if (!empty($visibleFields) || !empty($hiddenFields)) {
                    $schemas[$schemaName . 'Response'] = $this->schemaBuilder->fromFieldVisibility(
                        $visibleFields,
                        $hiddenFields,
                        $modelClass,
                    );
                } else {
                    $schemas[$schemaName . 'Response'] = ['type' => 'object', 'additionalProperties' => true];
                }
            }
        }

        return $schemas;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function buildCommonSchemas(): array
    {
        return [
            'PaginationMeta' => [
                'type'       => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'last_page'    => ['type' => 'integer'],
                    'per_page'     => ['type' => 'integer'],
                    'total'        => ['type' => 'integer'],
                ],
            ],
            'PaginationLinks' => [
                'type'       => 'object',
                'properties' => [
                    'first' => ['type' => 'string', 'nullable' => true],
                    'last'  => ['type' => 'string', 'nullable' => true],
                    'prev'  => ['type' => 'string', 'nullable' => true],
                    'next'  => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'ErrorResponse' => [
                'type'       => 'object',
                'properties' => [
                    'error' => [
                        'type'       => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'status'  => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'ValidationErrorResponse' => [
                'type'       => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'errors'  => [
                        'type'                 => 'object',
                        'additionalProperties' => [
                            'type'  => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function buildSecuritySchemes(): array
    {
        $defaultSecurity = config('apilot.openapi.default_security', 'bearer');

        return match ($defaultSecurity) {
            'bearer' => [
                'BearerAuth' => [
                    'type'         => 'http',
                    'scheme'       => 'bearer',
                    'bearerFormat' => 'Token',
                ],
            ],
            'basic'  => [
                'BasicAuth' => [
                    'type'   => 'http',
                    'scheme' => 'basic',
                ],
            ],
            'apiKey' => [
                'ApiKeyAuth' => [
                    'type' => 'apiKey',
                    'in'   => 'header',
                    'name' => 'X-API-Key',
                ],
            ],
            default => [],
        };
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
