# Pagination

All `index` responses are paginated. Clients control the page and page size via query parameters.

## Request Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `page` | `1` | Page number (1-based) |
| `per_page` | `15` | Items per page |

```
GET /api/posts?page=2&per_page=25
```

## Response Format

```json
{
    "data": [
        { "id": 16, "title": "Post 16" },
        { "id": 17, "title": "Post 17" }
    ],
    "meta": {
        "current_page": 2,
        "last_page": 10,
        "per_page": 25,
        "total": 243
    },
    "links": {
        "first": "http://example.com/api/posts?page=1",
        "last":  "http://example.com/api/posts?page=10",
        "prev":  "http://example.com/api/posts?page=1",
        "next":  "http://example.com/api/posts?page=3"
    }
}
```

### `meta` Fields

| Field | Type | Description |
|-------|------|-------------|
| `current_page` | integer | The current page number |
| `last_page` | integer | The last available page |
| `per_page` | integer | Items returned per page |
| `total` | integer | Total number of items across all pages |

### `links` Fields

| Field | Type | Description |
|-------|------|-------------|
| `first` | string | URL to the first page |
| `last` | string | URL to the last page |
| `prev` | string\|null | URL to the previous page, `null` on page 1 |
| `next` | string\|null | URL to the next page, `null` on the last page |

## Limits and Defaults

| Config Key | Default | Description |
|------------|---------|-------------|
| `pagination.default_per_page` | `15` | Default items per page when `per_page` is not specified |
| `pagination.max_per_page` | `100` | Maximum allowed value for `per_page` |
| `pagination.per_page_param` | `per_page` | Query parameter name |

If a client requests more than `max_per_page`, the value is silently capped:

```
GET /api/posts?per_page=9999  →  returns 100 items (max_per_page)
```

## Per-Controller Override

Override the default per-page value for a specific controller:

```php
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?int $defaultPerPage = 20;
}
```

This overrides `pagination.default_per_page` for this controller only. The global `max_per_page` still applies.

## Edge Cases

| Input | Behavior |
|-------|----------|
| `?per_page=abc` | Uses `default_per_page` |
| `?per_page=-5` | Clamped to `1` (minimum), then further to `default_per_page` if less than 1 |
| `?per_page=0` | Treated as invalid, uses `default_per_page` |
| `?page=abc` | Defaults to page `1` |
| `?page=-1` | Defaults to page `1` |
| `?page=9999` | Returns empty `data`, `meta.total` still reflects the real count |

## Config

```php
// config/apilot.php
'pagination' => [
    'default_per_page' => 15,
    'max_per_page'     => 100,
    'per_page_param'   => 'per_page',
],
```

---

**Next:** [Hooks](09-hooks.md)
