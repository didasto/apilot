# Middleware

## ForceJsonResponse

### The Problem

By default, Laravel's validation errors are returned as an HTML redirect if the request does not include an `Accept: application/json` header. API clients that omit this header (e.g., `curl` without `-H "Accept: application/json"`) receive an HTML error page instead of a JSON response.

Additionally, API responses should always carry the `Content-Type: application/json` header so clients know how to parse the body.

### The Solution

Apilot ships with `ForceJsonResponse` middleware (alias `apilot.json`) that:

1. Sets `Accept: application/json` on the **request** — ensuring Laravel returns JSON for all error responses (validation errors, auth errors, etc.)
2. Sets `Content-Type: application/json` on the **response** — ensuring clients always receive a properly typed JSON body

```php
namespace Didasto\Apilot\Http\Middleware;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
```

### Automatic Application (Default Behavior)

By default (`'force_json' => true` in `config/apilot.php`), Apilot **automatically applies** `apilot.json` as the first middleware on:

- All routes registered via `CrudRouteRegistrar::resource()`
- All routes registered via the `#[ApiResource]` attribute
- The `/api/doc` OpenAPI documentation route

You do not need to add it manually.

```php
// This route automatically gets apilot.json prepended to its middleware:
CrudRouteRegistrar::resource('posts', PostController::class)
    ->middleware(['auth:sanctum']);

// Effective middleware stack: ['apilot.json', 'api', 'auth:sanctum']
```

### Disabling Automatic Application

Set `force_json` to `false` in `config/apilot.php` to opt out of automatic middleware injection:

```php
// config/apilot.php
'force_json' => false,
```

When disabled, you can still apply the middleware manually wherever needed.

### Manual Application

When `force_json` is `false`, or to apply the middleware to non-Apilot routes, add it explicitly.

#### Option 1: Per resource

```php
CrudRouteRegistrar::resource('posts', PostController::class)
    ->middleware(['apilot.json', 'auth:sanctum']);
```

#### Option 2: Global API middleware (recommended for API-only apps)

In `app/Http/Kernel.php` (Laravel 10 and below):

```php
protected $middlewareGroups = [
    'api' => [
        // ... existing middleware
        \Didasto\Apilot\Http\Middleware\ForceJsonResponse::class,
    ],
];
```

In `bootstrap/app.php` (Laravel 11+):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('api', \Didasto\Apilot\Http\Middleware\ForceJsonResponse::class);
})
```

#### Option 3: Via config (applies to all Apilot routes)

```php
// config/apilot.php
'middleware' => ['api', 'apilot.json'],
```

### Effect

Without the middleware, a POST with a missing required field and no `Accept` header returns an HTML redirect:

```
HTTP/1.1 302 Found
Location: /
```

With the middleware, the same request returns JSON:

```json
{
    "message": "The title field is required.",
    "errors": {
        "title": ["The title field is required."]
    }
}
```

And all responses carry the correct `Content-Type: application/json` header.

---

**Next:** [Error Handling](12-error-handling.md)
