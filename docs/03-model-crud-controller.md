# Model CRUD Controller

`ModelCrudController` provides a fully working CRUD API backed by an Eloquent model. Extend it, set the required property, and optionally configure filtering, sorting, validation, and resource transformation.

## Required Property

### `$model`

The fully-qualified class name of the Eloquent model.

```php
protected string $model = \App\Models\Post::class;
```

If `$model` is not set or does not point to an existing class, a `LogicException` is thrown when any endpoint is called.

## Optional Properties

### `$formRequestClass`

A `FormRequest` class used for validating `store` and `update` requests.

```php
protected ?string $formRequestClass = \App\Http\Requests\PostRequest::class;
```

- **When set:** The request is validated against the FormRequest's `rules()`. A failed validation returns 422 with structured error messages.
- **When `null` (default):** No validation is applied; `request()->all()` is used as the data payload. Suitable for quick prototypes or when validation is handled elsewhere.

### `$resourceClass`

A `JsonResource` class for transforming model instances before they are returned in the response.

```php
protected ?string $resourceClass = \App\Http\Resources\PostResource::class;
```

- **When set:** Every model in the response is passed through this resource.
- **When `null` (default):** `DefaultResource` is used, which returns all model attributes as-is.

### `$allowedFilters`

An associative array declaring which fields can be filtered and how. See [Filtering](06-filtering.md).

```php
protected array $allowedFilters = [
    'status' => AllowedFilter::EXACT,
    'title'  => AllowedFilter::PARTIAL,
];
```

### `$allowedSorts`

A list of fields that clients may sort by. See [Sorting](07-sorting.md).

```php
protected array $allowedSorts = ['title', 'created_at', 'status'];
```

### `$defaultPerPage`

Overrides the `pagination.default_per_page` config value for this specific controller.

```php
protected ?int $defaultPerPage = 20;
```

When `null` (default), the global config value is used.

## CRUD Methods

### `GET /api/{resource}` — index

Lists all resources, paginated and optionally filtered/sorted.

**Request parameters:** `?page=1&per_page=15&filter[status]=published&sort=-created_at`

**Response (200):**
```json
{
    "data": [
        { "id": 1, "title": "Hello World", "status": "published" }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 73
    },
    "links": {
        "first": "http://example.com/api/posts?page=1",
        "last":  "http://example.com/api/posts?page=5",
        "prev":  null,
        "next":  "http://example.com/api/posts?page=2"
    }
}
```

**Possible errors:** None from Apilot (empty result is still 200).

---

### `GET /api/{resource}/{id}` — show

Returns a single resource by its primary key.

**Response (200):**
```json
{
    "data": { "id": 1, "title": "Hello World", "status": "published" }
}
```

**Possible errors:** 404 if the record does not exist.

---

### `POST /api/{resource}` — store

Creates a new resource.

**Request body (JSON):**
```json
{ "title": "New Post", "body": "Content here.", "status": "draft" }
```

**Response (201):**
```json
{
    "data": { "id": 42, "title": "New Post", "status": "draft" }
}
```

**Possible errors:** 422 if validation fails (when `$formRequestClass` is set).

---

### `PUT /api/{resource}/{id}` — update

Updates an existing resource.

**Response (200):** Same format as `show`.

**Possible errors:** 404, 422.

---

### `DELETE /api/{resource}/{id}` — destroy

Deletes a resource.

**Response:** 204 No Content (empty body).

**Possible errors:** 404. 403 if [`beforeDestroy`](09-hooks.md) returns `false`.

## Complete Example

```php
<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Http\Requests\PostRequest;
use App\Http\Resources\PostResource;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;

class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EXACT,
        'title'  => AllowedFilter::PARTIAL,
    ];

    protected array $allowedSorts = ['title', 'created_at', 'status'];
    protected ?int $defaultPerPage = 20;

    protected function modifyIndexQuery(mixed $query, Request $request): mixed
    {
        // Scope to authenticated user's posts
        return $query->where('user_id', $request->user()->id);
    }

    protected function beforeStore(array $data, Request $request): array
    {
        $data['user_id'] = $request->user()->id;
        return $data;
    }

    protected function beforeDestroy(mixed $item, Request $request): bool
    {
        return $item->user_id === $request->user()->id;
    }
}
```

For a detailed explanation of all available hooks, see [Hooks](09-hooks.md).

---

**Next:** [Service CRUD Controller](04-service-crud-controller.md)
