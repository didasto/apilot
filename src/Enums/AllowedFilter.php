<?php

declare(strict_types=1);

namespace Didasto\Apilot\Enums;

enum AllowedFilter: string
{
    // ===== Bestehend (Rückwärtskompatibel) =====
    case EXACT   = 'exact';     // Alias für EQUALS — WHERE field = value
    case PARTIAL = 'partial';   // Alias für LIKE — WHERE field LIKE %value%
    case SCOPE   = 'scope';     // Ruft einen Query-Scope am Model auf

    // ===== Vergleichsoperatoren =====
    case EQUALS     = 'eq';     // WHERE field = value
    case NOT_EQUALS = 'neq';    // WHERE field != value

    // ===== Mengenoperatoren =====
    case IN     = 'in';         // WHERE field IN (value1, value2, ...)
    case NOT_IN = 'notIn';      // WHERE field NOT IN (value1, value2, ...)

    // ===== Größenvergleiche =====
    case GT  = 'gt';            // WHERE field > value
    case LT  = 'lt';            // WHERE field < value
    case GTE = 'gte';           // WHERE field >= value
    case LTE = 'lte';           // WHERE field <= value

    // ===== Textsuche =====
    case LIKE     = 'like';     // WHERE field LIKE %value%
    case NOT_LIKE = 'notLike';  // WHERE field NOT LIKE %value%

    // ===== Bereichsoperatoren =====
    case BETWEEN     = 'between';       // WHERE field BETWEEN value1 AND value2
    case NOT_BETWEEN = 'notBetween';    // WHERE field NOT BETWEEN value1 AND value2

    // ===== Null-Prüfungen =====
    case IS_NULL     = 'isNull';        // WHERE field IS NULL
    case IS_NOT_NULL = 'isNotNull';     // WHERE field IS NOT NULL

    /**
     * Gibt den SQL-Operator zurück, der für diesen Filter-Typ verwendet wird.
     */
    public function toOperator(): string
    {
        return match ($this) {
            self::EXACT, self::EQUALS         => '=',
            self::NOT_EQUALS                  => '!=',
            self::GT                          => '>',
            self::LT                          => '<',
            self::GTE                         => '>=',
            self::LTE                         => '<=',
            self::PARTIAL, self::LIKE         => 'LIKE',
            self::NOT_LIKE                    => 'NOT LIKE',
            default                           => '=',
        };
    }

    /**
     * Prüft ob dieser Filter-Typ einen einzelnen Wert erwartet.
     */
    public function isSingleValue(): bool
    {
        return match ($this) {
            self::IN, self::NOT_IN, self::BETWEEN, self::NOT_BETWEEN => false,
            self::IS_NULL, self::IS_NOT_NULL                         => false,
            default                                                   => true,
        };
    }

    /**
     * Prüft ob dieser Filter-Typ keinen Wert erwartet (IS NULL, IS NOT NULL).
     */
    public function isNoValue(): bool
    {
        return match ($this) {
            self::IS_NULL, self::IS_NOT_NULL => true,
            default                          => false,
        };
    }

    /**
     * Gibt den Query-Parameter-Suffix zurück, der im Request verwendet wird.
     */
    public function queryKey(): string
    {
        return $this->value;
    }
}
