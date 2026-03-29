<?php

declare(strict_types=1);

namespace Didasto\Apilot\OpenApi;

class InfoBuilder
{
    /**
     * @return array<string, string>
     */
    public function build(): array
    {
        return [
            'title'       => config('apilot.openapi.info.title', 'API Documentation'),
            'description' => config('apilot.openapi.info.description', ''),
            'version'     => config('apilot.openapi.info.version', '1.0.0'),
        ];
    }
}
