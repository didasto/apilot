<?php

declare(strict_types=1);

namespace Didasto\Apilot\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class ActionNotAllowedException extends RuntimeException
{
    public function __construct(
        string $message = 'Action not allowed.',
        protected readonly int $statusCode = 403,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            data: [
                'error' => [
                    'message' => $this->getMessage(),
                    'status'  => $this->statusCode,
                ],
            ],
            status: $this->statusCode,
        );
    }
}
