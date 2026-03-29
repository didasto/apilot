<?php

declare(strict_types=1);

namespace Didasto\Apilot\Enums;

enum AllowedFilter: string
{
    case EXACT   = 'exact';    // WHERE field = value
    case PARTIAL = 'partial';  // WHERE field LIKE %value%
    case SCOPE   = 'scope';    // Ruft einen Query-Scope am Model auf
}
