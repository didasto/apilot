<?php

declare(strict_types=1);

namespace Didasto\Apilot\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class NotImplementedException extends RuntimeException
{
    public function __construct(string $method)
    {
        parent::__construct(
            sprintf('Method %s is not implemented.', $method)
        );
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            data: [
                'error' => [
                    'message' => $this->getMessage(),
                    'status'  => 501,
                ],
            ],
            status: 501,
        );
    }
}
