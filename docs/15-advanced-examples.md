# Advanced Examples

Complete, production-ready examples covering common real-world scenarios.

## 1. Multi-Tenant Blog API

Posts scoped to the authenticated user, with full validation and OpenAPI metadata.

**Model** (`app/Models/Post.php`):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Post extends Model
{
    protected $fillable = ['title', 'body', 'status', 'published_at', 'user_id'];

    protected $casts = ['published_at' => 'datetime'];

    public function scopePublishedAfter(Builder $query, string $date): Builder
    {
        return $query->where('published_at', '>=', $date);
    }
}
```

**FormRequest** (`app/Http/Requests/PostRequest.php`):

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Didasto\Apilot\Attributes\OpenApiProperty;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[OpenApiProperty(properties: [
        'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'example' => 'draft'],
        'published_at' => ['type' => 'string', 'format' => 'date-time', 'example' => '2024-03-01T10:00:00Z'],
    ])]
    public function rules(): array
    {
        return [
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'status'       => 'required|in:draft,published',
            'published_at' => 'nullable|date',
        ];
    }
}
```

**Resource** (`app/Http/Resources/PostResource.php`):

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'excerpt'      => str($this->body)->limit(150)->toString(),
            'status'       => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Controller** (`app/Http/Controllers/Api/PostController.php`):

```php
<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Http\Requests\PostRequest;
use App\Http\Resources\PostResource;
use Didasto\Apilot\Attributes\OpenApiMeta;
use Didasto\Apilot\Controllers\ModelCrudController;
use Didasto\Apilot\Enums\AllowedFilter;

#[OpenApiMeta(tag: 'Posts', description: 'Blog post management.')]
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $formRequestClass = PostRequest::class;
    protected ?string $resourceClass = PostResource::class;

    protected array $allowedFilters = [
        'status'          => AllowedFilter::EXACT,
        'title'           => AllowedFilter::PARTIAL,
        'published_after' => AllowedFilter::SCOPE,
    ];

    protected array $allowedSorts = ['title', 'created_at', 'published_at'];
    protected ?int $defaultPerPage = 20;

    protected function modifyIndexQuery(mixed $query, Request $request): mixed
    {
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

**Route** (`routes/api.php`):

```php
use Didasto\Apilot\Routing\CrudRouteRegistrar;
use App\Http\Controllers\Api\PostController;

CrudRouteRegistrar::resource('posts', PostController::class)
    ->middleware(['auth:sanctum', 'apilot.json']);
```

---

## 2. Read-Only External Product Catalogue

Proxy an external REST API as a read-only Apilot endpoint.

**Service** (`app/Services/ProductCatalogueService.php`):

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;

class ProductCatalogueService implements CrudServiceInterface
{
    private string $baseUrl = 'https://api.supplier.com/v2';

    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        $cacheKey = 'products:' . md5(serialize([$filters, $sorting, $pagination]));

        return Cache::remember($cacheKey, 60, function () use ($filters, $sorting, $pagination) {
            $params = [
                'page'     => $pagination->page,
                'per_page' => $pagination->perPage,
            ];

            foreach ($filters as $key => $value) {
                $params["filter[{$key}]"] = $value;
            }

            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/products", $params)
                ->json();

            return new PaginatedResult(
                items:       $response['data'] ?? [],
                total:       $response['meta']['total'] ?? 0,
                perPage:     $pagination->perPage,
                currentPage: $pagination->page,
            );
        });
    }

    public function find(int|string $id): mixed
    {
        return Cache::remember("product:{$id}", 300, function () use ($id) {
            $response = Http::timeout(10)->get("{$this->baseUrl}/products/{$id}");

            return $response->successful() ? $response->json('data') : null;
        });
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

use App\Services\ProductCatalogueService;
use Didasto\Apilot\Controllers\ServiceCrudController;

class ProductController extends ServiceCrudController
{
    protected string $serviceClass = ProductCatalogueService::class;
    protected array $allowedFilters = ['name', 'category', 'brand'];
    protected array $allowedSorts = ['name', 'price'];
}
```

**Route:**

```php
CrudRouteRegistrar::resource('products', ProductController::class)
    ->only(['index', 'show']);
```

---

## 3. Soft Deletes

Standard Laravel SoftDeletes work without any Apilot-specific configuration. The `destroy` endpoint soft-deletes the record; soft-deleted records are automatically excluded from `index` and `show`.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = ['body', 'post_id', 'user_id'];
}
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Comment;
use Didasto\Apilot\Controllers\ModelCrudController;

class CommentController extends ModelCrudController
{
    protected string $model = Comment::class;
}
```

No controller changes are required. `delete()` on a SoftDeletes model sets `deleted_at` instead of removing the row.

---

## 4. Customizing Status Codes

Return `202 Accepted` for async create operations and `200` for deletes that return a confirmation body.

```php
<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Job;
use Didasto\Apilot\Controllers\ModelCrudController;

class JobController extends ModelCrudController
{
    protected string $model = Job::class;

    protected function beforeStore(array $data, Request $request): array
    {
        $data['status'] = 'queued';
        return $data;
    }

    protected function afterStore(mixed $item, Request $request): mixed
    {
        dispatch(new \App\Jobs\ProcessJob($item));
        return $item;
    }

    protected function getStatusCode(string $action): int
    {
        return match ($action) {
            'store' => 202,     // Accepted — processing async
            default => parent::getStatusCode($action),
        };
    }
}
```

---

## 5. Multiple API Versions

Register the same controllers under two prefixes by publishing different config files or using route groups:

```php
// routes/api.php

use Didasto\Apilot\Routing\CrudRouteRegistrar;
use App\Http\Controllers\Api\V1\PostController as PostV1;
use App\Http\Controllers\Api\V2\PostController as PostV2;

// V1: full CRUD
CrudRouteRegistrar::resource('v1/posts', PostV1::class)
    ->middleware(['auth:sanctum']);

// V2: read-only with different resource shape
CrudRouteRegistrar::resource('v2/posts', PostV2::class)
    ->only(['index', 'show'])
    ->middleware(['auth:sanctum']);
```

With `prefix = 'api'` in config, this generates:

- `GET /api/v1/posts`
- `POST /api/v1/posts`
- `GET /api/v2/posts`
- `GET /api/v2/posts/{id}`

The OpenAPI spec includes both sets of paths, each tagged separately via `#[OpenApiMeta]`.

---

**Back to:** [Overview](README.md)
