# Filtering

Apilot supports three filter strategies. Filters are declared per-controller and applied automatically when a client sends `?filter[field]=value`.

## Filter Types

| Type | Enum | SQL Equivalent | Use When |
|------|------|---------------|----------|
| Exact match | `AllowedFilter::EXACT` | `WHERE field = value` | Status, category, boolean flags |
| Partial match | `AllowedFilter::PARTIAL` | `WHERE field LIKE %value%` | Search strings, names, titles |
| Scope | `AllowedFilter::SCOPE` | Calls `$query->field($value)` | Complex logic in a model scope |

## Declaring Allowed Filters

```php
use Didasto\Apilot\Enums\AllowedFilter;

protected array $allowedFilters = [
    'status'   => AllowedFilter::EXACT,
    'title'    => AllowedFilter::PARTIAL,
    'category' => AllowedFilter::EXACT,
];
```

Any field not in `$allowedFilters` is silently ignored. Clients cannot filter on undeclared fields.

## Request Format

```
GET /api/posts?filter[status]=published
GET /api/posts?filter[title]=laravel
GET /api/posts?filter[status]=published&filter[title]=laravel
```

### Multiple Filters

Multiple filters are combined with `AND`:

```
GET /api/posts?filter[status]=published&filter[category]=php
```

Generates:

```sql
WHERE status = 'published' AND category = 'php'
```

## SCOPE Filter

The `SCOPE` type calls a named [Eloquent local scope](https://laravel.com/docs/eloquent#local-scopes) on the model, passing the filter value as the first argument.

```php
// Controller
protected array $allowedFilters = [
    'published_after' => AllowedFilter::SCOPE,
];
```

```php
// App\Models\Post
public function scopePublishedAfter(Builder $query, string $date): Builder
{
    return $query->where('published_at', '>=', $date);
}
```

```
GET /api/posts?filter[published_after]=2024-01-01
```

The scope name must match the filter key exactly. The `scope` prefix is added by Eloquent automatically.

## Empty Values Are Ignored

If a client sends an empty filter value, Apilot silently skips it — no empty `WHERE` clause is added:

```
GET /api/posts?filter[status]=
```

The `status` filter is not applied. All posts are returned.

## Non-Array Values Are Ignored

If a client sends the filter as a plain string instead of an array, it is silently ignored:

```
GET /api/posts?filter=status
```

This does not cause an error.

## ServiceCrudController Differences

For `ServiceCrudController`, `$allowedFilters` is a plain list of field names — not an associative array:

```php
protected array $allowedFilters = ['name', 'category', 'status'];
```

Apilot passes the active filters as an associative `array $filters` to your service's `list()` method. Your service applies them however it sees fit (e.g., forwarding them as query parameters to an external API).

See [Service CRUD Controller](04-service-crud-controller.md) for the full interface.

---

**Next:** [Sorting](07-sorting.md)
