# Filtering

Apilot supports an operator-based filter system with three configuration levels. Filters are applied automatically when a client sends `?filter[field]=value` or `?filter[field][operator]=value`.

## Request Formats

### Legacy Format (still supported)

```
GET /api/posts?filter[status]=published
GET /api/posts?filter[title]=laravel
```

### Operator Format

```
GET /api/products?filter[status][eq]=active
GET /api/products?filter[price][gte]=9.99
GET /api/products?filter[id][in]=1,2,3,4,5
GET /api/products?filter[created_at][between]=2025-01-01,2025-12-31
GET /api/products?filter[deleted_at][isNull]=1
```

Multiple filters are combined with `AND`:

```
GET /api/products?filter[price][gte]=10&filter[price][lte]=100&filter[name][like]=Pro
```

---

## All Operators

| Operator | Query Parameter Suffix | SQL Equivalent | Value Format | Example |
|----------|----------------------|----------------|--------------|---------|
| `eq` | `[eq]` | `WHERE field = value` | Single value | `filter[status][eq]=active` |
| `neq` | `[neq]` | `WHERE field != value` | Single value | `filter[status][neq]=draft` |
| `in` | `[in]` | `WHERE field IN (...)` | Comma-separated | `filter[id][in]=1,2,3` |
| `notIn` | `[notIn]` | `WHERE field NOT IN (...)` | Comma-separated | `filter[status][notIn]=draft,archived` |
| `gt` | `[gt]` | `WHERE field > value` | Single value | `filter[id][gt]=10` |
| `lt` | `[lt]` | `WHERE field < value` | Single value | `filter[id][lt]=100` |
| `gte` | `[gte]` | `WHERE field >= value` | Single value | `filter[price][gte]=9.99` |
| `lte` | `[lte]` | `WHERE field <= value` | Single value | `filter[price][lte]=99.99` |
| `like` | `[like]` | `WHERE field LIKE %value%` | Single value | `filter[title][like]=Laravel` |
| `notLike` | `[notLike]` | `WHERE field NOT LIKE %value%` | Single value | `filter[title][notLike]=deprecated` |
| `between` | `[between]` | `WHERE field BETWEEN a AND b` | `start,end` | `filter[created_at][between]=2025-01-01,2025-12-31` |
| `notBetween` | `[notBetween]` | `WHERE field NOT BETWEEN a AND b` | `start,end` | `filter[price][notBetween]=0,5` |
| `isNull` | `[isNull]` | `WHERE field IS NULL` | Any value (e.g. `1`) | `filter[deleted_at][isNull]=1` |
| `isNotNull` | `[isNotNull]` | `WHERE field IS NOT NULL` | Any value (e.g. `1`) | `filter[email][isNotNull]=1` |

### Legacy Operators (backwards compatible)

| Operator | SQL Equivalent |
|----------|---------------|
| `AllowedFilter::EXACT` | `WHERE field = value` (alias for `eq`) |
| `AllowedFilter::PARTIAL` | `WHERE field LIKE %value%` (alias for `like`) |
| `AllowedFilter::SCOPE` | Calls a model query scope |

---

## Configuration — 3 Levels

### Level 1 — Single Operator (Enum)

```php
use Didasto\Apilot\Enums\AllowedFilter;

protected array $allowedFilters = [
    'status' => AllowedFilter::EQUALS,
];
```

Accepts both:
- `?filter[status]=active` (legacy format, uses the configured operator as default)
- `?filter[status][eq]=active` (operator format)

### Level 2 — Multiple Operators (Array of Enums)

```php
protected array $allowedFilters = [
    'id' => [AllowedFilter::EQUALS, AllowedFilter::IN, AllowedFilter::GT, AllowedFilter::LT],
];
```

- `?filter[id]=5` → uses the **first** operator in the array as default (`EQUALS`)
- `?filter[id][in]=1,2,3` → `WHERE id IN (1, 2, 3)`
- `?filter[id][like]=test` → ignored (not in the allowed list)

### Level 3 — FilterSet Class

```php
use Didasto\Apilot\Filters\IdFilter;
use Didasto\Apilot\Filters\NumericFilter;
use Didasto\Apilot\Filters\TextFilter;
use Didasto\Apilot\Filters\DateFilter;

protected array $allowedFilters = [
    'id'         => IdFilter::class,
    'price'      => NumericFilter::class,
    'title'      => TextFilter::class,
    'created_at' => DateFilter::class,
];
```

Internally the class is instantiated and `->filters()` returns the allowed operator array. Behaviour is identical to Level 2.

### Mixing All 3 Levels

```php
protected array $allowedFilters = [
    'id'         => IdFilter::class,              // FilterSet
    'status'     => AllowedFilter::EQUALS,        // Single enum
    'price'      => [AllowedFilter::GTE, AllowedFilter::LTE],  // Array
    'title'      => TextFilter::class,            // FilterSet
    'created_at' => DateFilter::class,            // FilterSet
    'category'   => AllowedFilter::SCOPE,         // Legacy scope
];
```

---

## Built-in FilterSets

| Class | Included Operators | Typical Use Case |
|-------|--------------------|-----------------|
| `IdFilter` | `eq`, `neq`, `in`, `notIn` | Primary key / foreign key fields |
| `NumericFilter` | `eq`, `neq`, `in`, `notIn`, `gt`, `lt`, `gte`, `lte`, `between` | Prices, quantities, scores |
| `TextFilter` | `eq`, `neq`, `like`, `notLike`, `in`, `isNull`, `isNotNull` | Names, titles, descriptions |
| `DateFilter` | `eq`, `neq`, `gt`, `lt`, `gte`, `lte`, `between`, `isNull`, `isNotNull` | Dates and timestamps |
| `BooleanFilter` | `eq`, `isNull`, `isNotNull` | Boolean flags |

---

## Creating a Custom FilterSet

```php
namespace App\Filters;

use Didasto\Apilot\Filters\FilterSet;
use Didasto\Apilot\Enums\AllowedFilter;

class StatusFilter extends FilterSet
{
    protected array $filters = [
        AllowedFilter::EQUALS,
        AllowedFilter::NOT_EQUALS,
        AllowedFilter::IN,
    ];
}
```

```php
use App\Filters\StatusFilter;
use Didasto\Apilot\Filters\IdFilter;
use Didasto\Apilot\Filters\NumericFilter;

class ProductController extends ModelCrudController
{
    protected string $model = Product::class;

    protected array $allowedFilters = [
        'id'     => IdFilter::class,
        'status' => StatusFilter::class,
        'price'  => NumericFilter::class,
    ];
}
```

---

## ServiceCrudController

For `ServiceCrudController`, `allowedFilters` uses the same 3-level configuration. The controller validates which operators are allowed and passes a structured array to the service's `list()` method:

```php
// Controller
class ProductController extends ServiceCrudController
{
    protected string $serviceClass = ProductService::class;

    protected array $allowedFilters = [
        'id'   => IdFilter::class,
        'name' => TextFilter::class,
    ];
}
```

```
GET /api/products?filter[name][like]=Laravel&filter[id][gt]=5
```

The service receives:

```php
$filters = [
    'name' => ['like' => 'Laravel'],
    'id'   => ['gt'   => '5'],
];
```

Legacy format is also converted to the operator format when using the new associative `allowedFilters`:

```
GET /api/products?filter[name]=Laravel
→ $filters = ['name' => ['eq' => 'Laravel']]
```

### Legacy ServiceCrudController format (backwards compatible)

The old integer-indexed format still works unchanged:

```php
protected array $allowedFilters = ['name', 'category', 'status'];
```

In this case `extractFilters()` returns `['name' => 'Laravel']` (plain values), same as before.

---

## Backwards Compatibility

- `AllowedFilter::EXACT`, `AllowedFilter::PARTIAL`, `AllowedFilter::SCOPE` are still valid and work as before
- `?filter[field]=value` (legacy request format) works for all controller types
- Existing controllers with the old `AllowedFilter::EXACT` / `AllowedFilter::PARTIAL` configuration require no changes
- All filter values are applied via Eloquent query builder parameter binding — no raw SQL

---

## Special Behaviour Notes

- **Empty values** are silently ignored: `?filter[status]=` → no filter applied
- **Disallowed operators** are silently ignored: `?filter[id][like]=test` when `like` is not in the allowed list
- **BETWEEN** requires exactly 2 comma-separated values; otherwise the filter is ignored
- **IS NULL / IS NOT NULL** ignore the request value; any value (e.g. `1`) must be sent
- **LIKE / NOT LIKE** automatically wraps the value in `%` wildcards and escapes `%` and `_` characters in the input
- **IN / NOT IN** values are split on comma; empty segments are removed

---

**Next:** [Sorting](07-sorting.md)
