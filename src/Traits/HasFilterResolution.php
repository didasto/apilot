<?php

declare(strict_types=1);

namespace Didasto\Apilot\Traits;

use Didasto\Apilot\Enums\AllowedFilter;
use Didasto\Apilot\Filters\FilterSet;

trait HasFilterResolution
{
    /**
     * Ermittelt die erlaubten Operatoren für ein Feld aus der $allowedFilters-Konfiguration.
     *
     * @return array<int, AllowedFilter>
     */
    private function resolveAllowedOperators(string $field): array
    {
        $config = $this->allowedFilters[$field];

        // Ebene 1: Einzelner Enum-Wert
        if ($config instanceof AllowedFilter) {
            return [$config];
        }

        // Ebene 3: FilterSet-Klasse (String = Klassenname)
        if (is_string($config) && class_exists($config) && is_subclass_of($config, FilterSet::class)) {
            return (new $config())->filters();
        }

        // Ebene 2: Array von Enum-Werten
        if (is_array($config)) {
            return $config;
        }

        return [];
    }

    /**
     * Ermittelt den Default-Operator für Legacy-Requests.
     * Bei einem einzelnen Enum: dieser Enum.
     * Bei einem Array/FilterSet: der erste Operator im Array.
     */
    private function resolveDefaultOperator(string $field, array $allowedOperators): AllowedFilter
    {
        $config = $this->allowedFilters[$field];

        if ($config instanceof AllowedFilter) {
            return $config;
        }

        return $allowedOperators[0] ?? AllowedFilter::EQUALS;
    }
}
