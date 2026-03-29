<?php

declare(strict_types=1);

namespace Didasto\Apilot\Console;

use Illuminate\Console\Command;
use Didasto\Apilot\OpenApi\OpenApiGenerator;
use Didasto\Apilot\OpenApi\SpecValidator;

class GenerateOpenApiSpecCommand extends Command
{
    protected $signature = 'apilot:generate-spec
        {--path= : Custom output path (overrides config)}
        {--stdout : Output to stdout instead of file}
        {--validate : Validate the generated spec before saving}';

    protected $description = 'Generate OpenAPI 3.0.3 specification from registered CRUD routes.';

    public function handle(OpenApiGenerator $generator): int
    {
        if ($this->option('validate')) {
            $validator = app(SpecValidator::class);
            $result = $validator->validate($generator->generate());

            if (!$result['valid']) {
                $this->error('OpenAPI spec validation failed:');
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }

            $this->info('Spec validation passed.');
        }

        $json = $generator->toJson();

        if ($this->option('stdout')) {
            $this->line($json);

            return self::SUCCESS;
        }

        $path = $this->option('path') ?? config('apilot.openapi.export_path');

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $json);

        $this->info('OpenAPI spec generated: ' . $path);

        return self::SUCCESS;
    }
}
