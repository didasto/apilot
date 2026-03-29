<?php

declare(strict_types=1);

namespace Didasto\Apilot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Didasto\Apilot\OpenApi\OpenApiGenerator;

class OpenApiDocController
{
    public function __invoke(OpenApiGenerator $generator): JsonResponse
    {
        return new JsonResponse(
            data: $generator->generate(),
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }
}
