<?php

declare(strict_types=1);

namespace Didasto\Apilot\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ResourceNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'message' => 'Resource not found.',
                'status'  => 404,
            ],
        ], 404);
    }
}
