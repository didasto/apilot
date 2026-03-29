# Middleware

## ForceJsonResponse

### The Problem

By default, Laravel's validation errors are returned as an HTML redirect if the request does not include an `Accept: application/json` header. API clients that omit this header (e.g., `curl` without `-H "Accept: application/json"`) receive an HTML error page instead of a JSON response.

### The Solution

Apilot ships with `ForceJsonResponse` middleware that sets the `Accept` header to `application/json` on every request, ensuring all error responses are returned as JSON.

```php
namespace Didasto\Apilot\Http\Middleware;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
```

The middleware is registered as `apilot.json` by the service provider. It is **not applied automatically** — you add it where needed.

### Setup

#### Option 1: Per resource (recommended for mixed apps)

```php
// routes/api.php
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

Without the middleware, a POST with a missing required field returns HTML (if `Accept` header is absent):

```html
<!DOCTYPE html>
<html>...Laravel error page...</html>
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

---

**Next:** [Error Handling](12-error-handling.md)
