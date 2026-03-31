# Error Handling

Apilot returns consistent JSON error responses for all failure scenarios. All error bodies follow the same envelope structure.

## Error Envelope

```json
{
    "error": {
        "message": "Human-readable description.",
        "status": 404
    }
}
```

## 404 — Resource Not Found

Returned by `show`, `update`, and `destroy` when the requested ID does not exist.

**Trigger:** `Model::find($id)` returns `null`.

**Response:**

```json
HTTP/1.1 404 Not Found
Content-Type: application/json

{
    "error": {
        "message": "Resource not found.",
        "status": 404
    }
}
```

**Implementation:** `ResourceNotFoundException` is thrown and renders itself.

## 403 — Action Not Allowed

Returned by `destroy` when the `beforeDestroy()` hook returns `false`.

**Trigger:** Your authorization logic in `beforeDestroy()` rejects the operation.

**Response:**

```json
HTTP/1.1 403 Forbidden
Content-Type: application/json

{
    "error": {
        "message": "Action not allowed.",
        "status": 403
    }
}
```

**Implementation:** `ActionNotAllowedException` is thrown and renders itself.

You can customize the message:

```php
protected function beforeDestroy(mixed $item, Request $request): bool
{
    if ($item->user_id !== $request->user()->id) {
        throw new \Didasto\Apilot\Exceptions\ActionNotAllowedException(
            'You do not own this post.'
        );
    }
    return true;
}
```

## 422 — Validation Error

Returned by `store` and `update` when FormRequest validation fails.

**Trigger:** `$formRequestClass` is set and the request data fails the defined `rules()`.

**Response:**

```json
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json

{
    "message": "The title field is required.",
    "errors": {
        "title": ["The title field is required."],
        "status": ["The selected status is invalid."]
    }
}
```

This is Laravel's standard validation response format. Apilot does not modify it.

## 501 — Not Implemented

Returned when a service method has not been implemented in an `AbstractCrudService` subclass and that endpoint is called.

**Trigger:** A controller action calls a service method that throws `NotImplementedException` (the default in `AbstractCrudService`).

**Response:**

```json
HTTP/1.1 501 Not Implemented
Content-Type: application/json

{
    "error": {
        "message": "Method App\\Services\\ProductService::create is not implemented.",
        "status": 501
    }
}
```

**Implementation:** `NotImplementedException` is thrown by `AbstractCrudService` for any method that is not overridden, and renders itself via its `render()` method.

## 500 — Misconfiguration Exceptions

These are thrown during development when the controller is misconfigured. They should never reach production.

| Scenario | Exception | Message |
|----------|-----------|---------|
| `$model` not set | `LogicException` | `Property $model must be set in PostController.` |
| `$model` class does not exist | `LogicException` | `Model class App\Models\Foo does not exist.` |
| `$serviceClass` not set | `LogicException` | `Property $serviceClass must be set in ProductController.` |
| `$formRequestClass` class does not exist | `LogicException` | `FormRequest class App\Http\Requests\Foo does not exist.` |
| `$resourceClass` class does not exist | `LogicException` | `Resource class App\Http\Resources\Foo does not exist.` |

## HTML vs JSON Error Responses

Without the `ForceJsonResponse` middleware, Laravel returns HTML for some errors (e.g., validation) when the `Accept: application/json` header is missing. See [Middleware](11-middleware.md) for how to prevent this.

---

**Next:** [Configuration](13-configuration.md)
