# Sorting

Clients can request sorted results via the `sort` query parameter. Apilot only applies sorts for fields declared in `$allowedSorts`.

## Declaring Allowed Sorts

```php
protected array $allowedSorts = ['title', 'created_at', 'status', 'price'];
```

Any field not in this list is silently ignored. Clients cannot sort on undeclared fields.

## Request Format

### Ascending (default)

```
GET /api/posts?sort=title
GET /api/posts?sort=created_at
```

### Descending

Prefix the field name with `-`:

```
GET /api/posts?sort=-created_at
GET /api/posts?sort=-price
```

### Multiple Fields

Comma-separate multiple sort fields:

```
GET /api/posts?sort=status,-created_at
```

This sorts by `status` ascending first, then by `created_at` descending within each status group.

SQL equivalent:

```sql
ORDER BY status ASC, created_at DESC
```

## Silently Ignored Cases

- A field not in `$allowedSorts` → skipped, no error.
- `?sort[]=title` (array injection) → ignored entirely.
- `?sort=` (empty value) → no sorting applied.

## ServiceCrudController

For `ServiceCrudController`, Apilot extracts the sort fields and passes them as `array $sorting` to your service's `list()` method. Each entry is a `['field' => string, 'direction' => 'asc'|'desc']` array. Your service applies the sorting.

```php
public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
{
    $query = $this->buildQuery();

    foreach ($sorting as $sort) {
        $query->orderBy($sort['field'], $sort['direction']);
    }

    // ...
}
```

## Config

The sort parameter name can be changed in `config/apilot.php`:

```php
'sorting' => [
    'param' => 'sort', // ?sort=field → change to 'order_by' for ?order_by=field
],
```

---

**Next:** [Pagination](08-pagination.md)
