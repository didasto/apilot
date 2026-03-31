<?php

declare(strict_types=1);

namespace Didasto\Apilot\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Didasto\Apilot\Enums\AllowedFilter;

trait HasFiltering
{
    use HasFilterResolution;

    protected function applyFiltering(Builder $query, Request $request): Builder
    {
        $filterInput = $request->input(config('apilot.filtering.param', 'filter'), []);

        if (!is_array($filterInput)) {
            return $query;
        }

        foreach ($filterInput as $field => $value) {
            if (!array_key_exists($field, $this->allowedFilters)) {
                continue;
            }

            $allowedOperators = $this->resolveAllowedOperators($field);

            if (is_array($value)) {
                // Operator-Format: ?filter[field][operator]=value
                foreach ($value as $operatorKey => $operatorValue) {
                    $operator = AllowedFilter::tryFrom((string) $operatorKey);
                    if ($operator === null || !in_array($operator, $allowedOperators, true)) {
                        continue;
                    }
                    $this->applyFilterOperator($query, $field, $operator, $operatorValue);
                }
            } else {
                // Legacy-Format: ?filter[field]=value
                if ($value === '' || $value === null) {
                    continue;
                }
                $defaultOperator = $this->resolveDefaultOperator($field, $allowedOperators);
                $this->applyFilterOperator($query, $field, $defaultOperator, $value);
            }
        }

        return $query;
    }

    private function applyFilterOperator(Builder $query, string $field, AllowedFilter $operator, mixed $value): void
    {
        // Scope-Filter: Spezialbehandlung
        if ($operator === AllowedFilter::SCOPE) {
            if (method_exists($query->getModel(), 'scope' . ucfirst($field))) {
                $query->{$field}($value);
            }
            return;
        }

        // IS NULL / IS NOT NULL: Kein Wert nötig
        if ($operator === AllowedFilter::IS_NULL) {
            $query->whereNull($field);
            return;
        }

        if ($operator === AllowedFilter::IS_NOT_NULL) {
            $query->whereNotNull($field);
            return;
        }

        // Leere Werte ignorieren
        if ($value === '' || $value === null) {
            return;
        }

        // IN / NOT IN: Komma-separierte Werte in Array umwandeln
        if ($operator === AllowedFilter::IN) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn (string $v) => $v !== ''));
            $query->whereIn($field, $values);
            return;
        }

        if ($operator === AllowedFilter::NOT_IN) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn (string $v) => $v !== ''));
            $query->whereNotIn($field, $values);
            return;
        }

        // BETWEEN / NOT BETWEEN: Komma-separierter Start,End-Wert
        if ($operator === AllowedFilter::BETWEEN) {
            $parts = array_map('trim', explode(',', (string) $value));
            if (count($parts) === 2) {
                $query->whereBetween($field, [$parts[0], $parts[1]]);
            }
            return;
        }

        if ($operator === AllowedFilter::NOT_BETWEEN) {
            $parts = array_map('trim', explode(',', (string) $value));
            if (count($parts) === 2) {
                $query->whereNotBetween($field, [$parts[0], $parts[1]]);
            }
            return;
        }

        // LIKE / NOT LIKE: Wert mit Wildcards wrappen, Wildcards im Wert escapen (mit ESCAPE '\')
        if ($operator === AllowedFilter::LIKE || $operator === AllowedFilter::PARTIAL) {
            $escapedValue = str_replace(['%', '_'], ['\\%', '\\_'], (string) $value);
            $wrapped = $query->getQuery()->getGrammar()->wrap($field);
            $query->whereRaw("{$wrapped} LIKE ? ESCAPE '\\'", ["%{$escapedValue}%"]);
            return;
        }

        if ($operator === AllowedFilter::NOT_LIKE) {
            $escapedValue = str_replace(['%', '_'], ['\\%', '\\_'], (string) $value);
            $wrapped = $query->getQuery()->getGrammar()->wrap($field);
            $query->whereRaw("{$wrapped} NOT LIKE ? ESCAPE '\\'", ["%{$escapedValue}%"]);
            return;
        }

        // Alle anderen: Einfacher Vergleich (EXACT/EQUALS → =, NOT_EQUALS → !=, GT → >, usw.)
        $query->where($field, $operator->toOperator(), $value);
    }
}
