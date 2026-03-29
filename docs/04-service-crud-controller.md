# Service CRUD Controller

`ServiceCrudController` provides the same five CRUD endpoints as `ModelCrudController`, but delegates data access to a service class instead of querying Eloquent directly.

## When to Use This Controller

Use `ServiceCrudController` when:

- Data comes from an **external REST API** (e.g. a payment provider, product catalogue, or microservice).
- The data is **not backed by an Eloquent model** (legacy database, key-value store, in-memory data).
- You need **complex business logic** in the data access layer that does not fit neatly into Eloquent scopes.
- You are building a **proxy** that aggregates or transforms data from multiple sources.

## CrudServiceInterface

Your service must implement `Didasto\Apilot\Contracts\CrudServiceInterface`:

```php
interface CrudServiceInterface
{
    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult;
    public function find(int|string $id): mixed;
    public function create(array $data): mixed;
    public function update(int|string $id, array $data): mixed;
    public function delete(int|string $id): bool;
}
```

### Method Reference

| Method | Parameters | Return | Called by |
|--------|------------|--------|-----------|
| `list` | `$filters`, `$sorting`, `$pagination` | `PaginatedResult` | `index` |
| `find` | `$id` | Item or `null` | `show`, `update`, `destroy` |
| `create` | `$data` | Item | `store` |
| `update` | `$id`, `$data` | Item | `update` |
| `delete` | `$id` | `bool` | `destroy` |

## DTOs

### `PaginationParams`

Passed to `list()`. Contains:

| Property | Type | Description |
|----------|------|-------------|
| `$page` | `int` | Current page number (1-based) |
| `$perPage` | `int` | Items per page |

### `PaginatedResult`

Returned from `list()`. Constructor:

```php
new PaginatedResult(
    items: $items,       // array — the items for the current page
    total: $total,       // int   — total number of items across all pages
    perPage: $perPage,   // int   — items per page
    currentPage: $page,  // int   — current page number
);
```

`lastPage()` is a computed method: `(int) max(1, ceil($total / $perPage))`.

## Controller Properties

Same as `ModelCrudController`:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$serviceClass` | `string` | *(required)* | Fully-qualified service class name |
| `$formRequestClass` | `?string` | `null` | FormRequest for store/update validation |
| `$resourceClass` | `?string` | `null` | JsonResource for response transformation |
| `$allowedFilters` | `array` | `[]` | List of filterable field names |
| `$allowedSorts` | `array` | `[]` | List of sortable field names |
| `$defaultPerPage` | `?int` | `null` | Per-page count override |

> **Note:** For `ServiceCrudController`, `$allowedFilters` is a simple list of field names (`['name', 'slug']`), not an associative array with `AllowedFilter` types. The service itself decides how to apply each filter.

## Differences vs. ModelCrudController

| Aspect | ModelCrudController | ServiceCrudController |
|--------|---------------------|----------------------|
| Data source | Eloquent model | `CrudServiceInterface` |
| Filter config | `['field' => AllowedFilter::TYPE]` | `['field1', 'field2']` |
| Filtering applied by | Apilot (SQL WHERE clauses) | Your service |
| Sorting applied by | Apilot (SQL ORDER BY) | Your service |
| Pagination applied by | Apilot (`->paginate()`) | Your service |
| `modifyIndexQuery` input | Eloquent Builder | `array $filters` |

## Complete Example

**Service** (`app/Services/ProductService.php`):

```php
<?php

namespace App\Services;

use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use Illuminate\Support\Facades\Http;

class ProductService implements CrudServiceInterface
{
    private string $baseUrl = 'https://api.example.com';

    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        $params = ['page' => $pagination->page, 'per_page' => $pagination->perPage];
        foreach ($filters as $key => $value) {
            $params["filter[{$key}]"] = $value;
        }

        $response = Http::get("{$this->baseUrl}/products", $params)->json();

        return new PaginatedResult(
            items: $response['data'] ?? [],
            total: $response['meta']['total'] ?? 0,
            perPage: $pagination->perPage,
            currentPage: $pagination->page,
        );
    }

    public function find(int|string $id): mixed
    {
        return Http::get("{$this->baseUrl}/products/{$id}")->json('data');
    }

    public function create(array $data): mixed
    {
        return Http::post("{$this->baseUrl}/products", $data)->json('data');
    }

    public function update(int|string $id, array $data): mixed
    {
        return Http::put("{$this->baseUrl}/products/{$id}", $data)->json('data');
    }

    public function delete(int|string $id): bool
    {
        return Http::delete("{$this->baseUrl}/products/{$id}")->successful();
    }
}
```

**Controller** (`app/Http/Controllers/Api/ProductController.php`):

```php
<?php

namespace App\Http\Controllers\Api;

use App\Services\ProductService;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use Didasto\Apilot\Controllers\ServiceCrudController;

class ProductController extends ServiceCrudController
{
    protected string $serviceClass = ProductService::class;
    protected ?string $formRequestClass = ProductRequest::class;
    protected ?string $resourceClass = ProductResource::class;
    protected array $allowedFilters = ['name', 'category'];
    protected array $allowedSorts = ['name', 'price'];
}
```

**Route** (`routes/api.php`):

```php
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use App\Http\Controllers\Api\ProductController;

CrudRouteRegistrar::resource('products', ProductController::class)
    ->only(['index', 'show']);
```

---

**Next:** [Route Registration](05-route-registration.md)
