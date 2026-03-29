<?php

declare(strict_types=1);

namespace Didasto\Apilot\Routing;

class RouteRegistry
{
    /** @var array<int, RouteEntry> */
    protected array $entries = [];

    public function register(RouteEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return array<int, RouteEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
