# Quick Start

A working CRUD API in three steps. This example builds a blog post API backed by an Eloquent model.

## Step 1: Create the Controller

```php
<?php

// app/Http/Controllers/Api/PostController.php

namespace App\Http\Controllers\Api;

use App\Models\Post;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;

class PostController extends ModelCrudController
{
    protected string $model = Post::class;

    protected array $allowedFilters = [
        'status' => AllowedFilter::EXACT,
        'title'  => AllowedFilter::PARTIAL,
    ];

    protected array $allowedSorts = ['title', 'created_at'];
}
```

Your `Post` model needs to have `$fillable` set for the fields you intend to create/update:

```php
// app/Models/Post.php
protected $fillable = ['title', 'body', 'status'];
```

## Step 2: Register the Route

```php
// routes/api.php

use App\Http\Controllers\Api\PostController;
use Didasto\Apilot\Routing\CrudRouteRegistrar;

CrudRouteRegistrar::resource('posts', PostController::class);
```

This single line registers five routes:

| Method | URI | Action |
|--------|-----|--------|
| GET | `/api/posts` | index |
| GET | `/api/posts/{id}` | show |
| POST | `/api/posts` | store |
| PUT | `/api/posts/{id}` | update |
| DELETE | `/api/posts/{id}` | destroy |

## Step 3: Test It

```bash
# List all posts (paginated)
curl http://localhost/api/posts

# Create a post
curl -X POST http://localhost/api/posts \
  -H "Content-Type: application/json" \
  -d '{"title": "Hello World", "body": "My first post.", "status": "draft"}'

# Get a single post
curl http://localhost/api/posts/1

# Update a post
curl -X PUT http://localhost/api/posts/1 \
  -H "Content-Type: application/json" \
  -d '{"title": "Updated Title", "status": "published"}'

# Delete a post
curl -X DELETE http://localhost/api/posts/1
```

## What Just Happened?

Apilot did the following automatically:

- **Routing** — Five REST routes registered from one call.
- **Pagination** — The `index` response wraps results in `data`, `meta`, and `links` keys. Clients can use `?page=2&per_page=25`.
- **Filtering** — `?filter[status]=published` and `?filter[title]=hello` work immediately because you declared them in `$allowedFilters`.
- **Sorting** — `?sort=-created_at` sorts newest first. Undeclared fields are silently ignored.
- **JSON responses** — All responses are JSON with consistent structure.
- **Error handling** — Missing records return a 404 JSON error, validation failures return 422.

---

**Next:** [Model CRUD Controller](03-model-crud-controller.md)
