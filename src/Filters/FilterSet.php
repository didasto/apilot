<?php

declare(strict_types=1);

namespace Didasto\Apilot\Filters;

use Didasto\Apilot\Enums\AllowedFilter;

abstract class FilterSet
{
    /**
     * Die erlaubten Filter-Operatoren dieses Sets.
     *
     * @var array<int, AllowedFilter>
     */
    protected array $filters = [];

    /**
     * Gibt die erlaubten Filter zurück.
     *
     * @return array<int, AllowedFilter>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Statische Factory-Methode für bequeme Nutzung.
     */
    public static function make(): static
    {
        return new static();
    }
}
