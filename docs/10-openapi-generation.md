# OpenAPI Generation

Apilot automatically generates an OpenAPI 3.0.3 specification from your registered routes. No manual YAML or JSON authoring required.

## Live Spec Route

By default, the spec is served at:

```
GET /api/doc
```

The path is determined by `config('apilot.prefix')` + `config('apilot.openapi.path')`. With defaults `prefix = 'api'` and `openapi.path = 'doc'`, this resolves to `/api/doc`.

The response is a JSON document conforming to OpenAPI 3.0.3.

### Swagger UI

Point any Swagger UI instance at your live spec URL:

```html
<script>
SwaggerUIBundle({
    url: 'https://your-app.com/api/doc',
    dom_id: '#swagger-ui',
});
</script>
```

Or use the [Swagger UI CDN](https://swagger.io/tools/swagger-ui/) locally in development.

### Disabling the Route

```php
// config/apilot.php
'openapi' => [
    'enabled' => false,
],
```

## Artisan Command

Generate and export the spec to a file:

```bash
php artisan apilot:generate-spec
```

By default, writes to `storage/app/openapi.json` (configurable via `openapi.export_path`).

### Options

| Option | Description |
|--------|-------------|
| `--path=/custom/path.json` | Override the output path |
| `--stdout` | Print the JSON to stdout instead of writing a file |
| `--validate` | Validate the spec before saving; exits with failure if invalid |

### Examples

```bash
# Export to a custom path
php artisan apilot:generate-spec --path=public/api-spec.json

# Print to stdout (pipe into a file or SDK generator)
php artisan apilot:generate-spec --stdout > openapi.json

# Validate before exporting (useful in CI)
php artisan apilot:generate-spec --validate

# Validate and print to stdout
php artisan apilot:generate-spec --validate --stdout
```

### SDK Generation (Example with openapi-generator-cli)

```bash
php artisan apilot:generate-spec --stdout \
    | docker run --rm -i openapitools/openapi-generator-cli generate \
        -i /dev/stdin \
        -g typescript-axios \
        -o /tmp/sdk
```

## What Gets Generated

For each registered route entry, Apilot generates:

- **Path items** for each active action (index, show, store, update, destroy)
- **Request schemas** derived from your `$formRequestClass` validation rules
- **Response schemas** derived from your `$resourceClass` (or a generic object if not set)
- **Path parameters** (e.g., `{id}` for show/update/destroy)
- **Query parameters** for filtering, sorting, and pagination on `index`
- **Common schemas**: `PaginationMeta`, `PaginationLinks`, `ErrorResponse`, `ValidationErrorResponse`
- **Security schemes** based on `openapi.default_security` config

## Spec Info Block

Configured in `config/apilot.php`:

```php
'openapi' => [
    'info' => [
        'title'       => env('APP_NAME', 'API') . ' Documentation',
        'description' => 'Auto-generated API documentation.',
        'version'     => '1.0.0',
    ],
],
```

## OpenAPI Attributes

### `#[OpenApiMeta]`

Applied to a controller class. Overrides the tag name, description, summary, and deprecation status for all operations in that controller.

```php
use Didasto\Apilot\Attributes\OpenApiMeta;

#[OpenApiMeta(
    tag: 'Blog Posts',
    description: 'Manage published and draft blog posts.',
    deprecated: false,
)]
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `tag` | `?string` | OpenAPI tag for grouping operations |
| `description` | `?string` | Description shown in the spec |
| `summary` | `?string` | Short summary |
| `deprecated` | `bool` | Mark all operations as deprecated |

### `#[OpenApiProperty]`

Applied to a FormRequest method (typically `rules()`). Overrides schema properties for specific fields.

```php
use Didasto\Apilot\Attributes\OpenApiProperty;

class PostRequest extends FormRequest
{
    #[OpenApiProperty(properties: [
        'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'example' => 'draft'],
        'body'   => ['type' => 'string', 'description' => 'Full post content in Markdown'],
    ])]
    public function rules(): array
    {
        return [
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
            'status' => 'required|in:draft,published',
        ];
    }
}
```

Property override keys (all optional):

| Key | Type | Description |
|-----|------|-------------|
| `type` | string | OpenAPI type (`string`, `integer`, `boolean`, etc.) |
| `format` | string | OpenAPI format (`date-time`, `email`, `uuid`, etc.) |
| `description` | string | Property description |
| `example` | mixed | Example value |
| `enum` | array | Allowed values |

## Schema Naming

Schema names are derived from the resource name passed to `CrudRouteRegistrar::resource()`:

| Resource Name | Request Schema | Response Schema |
|---------------|---------------|-----------------|
| `posts` | `PostRequest` | `PostResponse` |
| `blog-entries` | `BlogEntryRequest` | `BlogEntryResponse` |
| `api/v1/comments` | `CommentRequest` | `CommentResponse` |

Apilot uses `Str::singular(Str::studly($resourceName))` for the base name.

---

**Next:** [Middleware](11-middleware.md)
