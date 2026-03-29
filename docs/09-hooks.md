# Hooks

Hooks let you inject custom logic at specific points in the CRUD lifecycle without subclassing or overriding entire actions. Override any hook in your controller — no registration needed.

## Hook Reference

| Hook | Actions | Signature | Return | Description |
|------|---------|-----------|--------|-------------|
| `modifyIndexQuery` | index | `(mixed $query, Request $request)` | `mixed` | Modify the Eloquent query before filtering and sorting are applied |
| `afterIndex` | index | `(mixed $result, Request $request)` | `mixed` | Transform the paginator after loading |
| `afterShow` | show | `(mixed $item, Request $request)` | `mixed` | Transform the model after loading |
| `beforeStore` | store | `(array $data, Request $request)` | `array` | Modify validated data before creating the model |
| `afterStore` | store | `(mixed $item, Request $request)` | `mixed` | Act on the created model before it is returned |
| `beforeUpdate` | update | `(mixed $item, array $data, Request $request)` | `array` | Modify validated data before updating the model |
| `afterUpdate` | update | `(mixed $item, Request $request)` | `mixed` | Act on the updated model before it is returned |
| `beforeDestroy` | destroy | `(mixed $item, Request $request)` | `bool` | Return `false` to abort deletion with 403 |
| `afterDestroy` | destroy | `(mixed $item, Request $request)` | `void` | Act after the model is deleted |
| `transformResponse` | all | `(mixed $data, string $action, Request $request)` | `mixed` | Final transformation of the response data for any action |
| `getStatusCode` | all | `(string $action)` | `int` | Override the HTTP status code for any action |

## Execution Order Per Action

### index

1. `modifyIndexQuery($query, $request)` → modify query before filters/sorting
2. Apilot applies filtering, sorting, pagination
3. `afterIndex($paginator, $request)` → transform the paginator
4. Resource class wraps each item
5. `transformResponse($data, 'index', $request)` → final response transformation
6. Response sent with `getStatusCode('index')` (default: 200)

### show

1. Model loaded by ID (404 if not found)
2. `afterShow($item, $request)` → transform loaded model
3. Resource class wraps the item
4. `transformResponse($data, 'show', $request)` → final response transformation
5. Response sent with `getStatusCode('show')` (default: 200)

### store

1. FormRequest validation (422 on failure if `$formRequestClass` is set)
2. `beforeStore($data, $request)` → modify validated data
3. `Model::create($data)` called
4. `afterStore($item, $request)` → act on created model
5. Resource class wraps the item
6. `transformResponse($data, 'store', $request)` → final response transformation
7. Response sent with `getStatusCode('store')` (default: 201)

### update

1. Model loaded by ID (404 if not found)
2. FormRequest validation (422 on failure if `$formRequestClass` is set)
3. `beforeUpdate($item, $data, $request)` → modify validated data
4. `$item->update($data)` called
5. `afterUpdate($item, $request)` → act on updated model
6. Resource class wraps the item
7. `transformResponse($data, 'update', $request)` → final response transformation
8. Response sent with `getStatusCode('update')` (default: 200)

### destroy

1. Model loaded by ID (404 if not found)
2. `beforeDestroy($item, $request)` → return `false` to abort (403)
3. `$item->delete()` called
4. `afterDestroy($item, $request)` → act after deletion
5. Response sent with `getStatusCode('destroy')` (default: 204)

## Hook Examples

### Scope index to the authenticated user

```php
protected function modifyIndexQuery(mixed $query, Request $request): mixed
{
    return $query->where('user_id', $request->user()->id);
}
```

### Inject the authenticated user on store

```php
protected function beforeStore(array $data, Request $request): array
{
    $data['user_id'] = $request->user()->id;
    return $data;
}
```

### Authorize deletion

```php
protected function beforeDestroy(mixed $item, Request $request): bool
{
    return $item->user_id === $request->user()->id;
}
```

Returning `false` aborts the deletion and returns:

```json
{
    "error": {
        "message": "Action not allowed.",
        "status": 403
    }
}
```

### Fire an event after creation

```php
protected function afterStore(mixed $item, Request $request): mixed
{
    PostCreated::dispatch($item);
    return $item;
}
```

### Add computed fields to every response

```php
protected function transformResponse(mixed $data, string $action, Request $request): mixed
{
    if ($action === 'index') {
        $data['meta']['generated_at'] = now()->toIso8601String();
    }
    return $data;
}
```

### Return 202 Accepted for async store

```php
protected function getStatusCode(string $action): int
{
    if ($action === 'store') {
        return 202;
    }
    return parent::getStatusCode($action);
}
```

## ServiceCrudController Hooks

`ServiceCrudController` supports the same hooks except `modifyIndexQuery`. Instead of an Eloquent query, the `index` pipeline passes the raw `$filters` array through `afterIndex`:

```php
protected function afterIndex(mixed $result, Request $request): mixed
{
    // $result is the PaginatedResult from your service
    return $result;
}
```

The `beforeStore`, `afterStore`, `beforeUpdate`, `afterUpdate`, `beforeDestroy`, `afterDestroy`, and `transformResponse` hooks work identically.

---

**Next:** [OpenAPI Generation](10-openapi-generation.md)
