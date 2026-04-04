# Field Visibility

Control which fields appear in API responses using a whitelist (`visibleFields`) and blacklist (`hiddenFields`). Both work on `ModelCrudController` and `ServiceCrudController`.

> **Note:** Field visibility is ignored when `$resourceClass` is set. The resource class always has full control over the response shape.

---

## Concept

The field filtering pipeline:

```
All model fields (toArray())
  → visibleFields set? → Keep only these fields (whitelist).
  → hiddenFields set?  → Remove these fields (blacklist).
  → Return result.
```

**Blacklist always wins.** If a field appears in both `$visibleFields` and `$hiddenFields`, it is removed.

---

## Static Properties

```php
use Didasto\Apilot\Controllers\ModelCrudController;

class PostController extends ModelCrudController
{
    protected string $model = Post::class;

    // Whitelist: only show these fields
    protected array $visibleFields = ['id', 'title', 'status'];

    // Blacklist: always hide these fields
    protected array $hiddenFields = ['password', 'remember_token'];
}
```

Both properties default to `[]` (no filtering). Setting only one activates only that filter.

---

## Dynamic Methods

Override `visibleFields()` or `hiddenFields()` for request-based logic (e.g. role-based field access):

```php
use Illuminate\Http\Request;
use Didasto\Apilot\Controllers\ModelCrudController;

class UserController extends ModelCrudController
{
    protected string $model = User::class;

    protected function visibleFields(Request $request): array
    {
        if ($request->user()?->isAdmin()) {
            return ['id', 'name', 'email', 'role', 'created_at'];
        }
        return ['id', 'name'];
    }

    protected function hiddenFields(Request $request): array
    {
        return ['password', 'remember_token'];
    }
}
```

The method takes precedence over the property when overridden in a subclass.

---

## Priority Order

1. `$resourceClass` set → **Resource handles everything; visibility is ignored.**
2. `visibleFields()` overridden → Call method, use result as whitelist.
3. `$visibleFields` not empty → Use property as whitelist.
4. `hiddenFields()` overridden → Call method, use result as blacklist.
5. `$hiddenFields` not empty → Use property as blacklist.
6. Nothing set → All fields returned (default behavior).

Steps 2–3 (whitelist) and 4–5 (blacklist) run **sequentially**, not as alternatives.

---

## Combined Example

```php
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    // Whitelist includes 'body', but blacklist removes it → 'body' never appears
    protected array $visibleFields = ['id', 'title', 'body', 'status'];
    protected array $hiddenFields = ['body'];
}
// Response fields: id, title, status
```

---

## ServiceCrudController

Works identically. The controller converts service items to arrays via `itemToArray()` before applying visibility:

```php
class TagController extends ServiceCrudController
{
    protected string $serviceClass = TagService::class;
    protected array $hiddenFields = ['internal_notes', 'cost_price'];
}
```

`itemToArray()` handles `array`, objects with `toArray()`, and `stdClass` objects.

---

## When to Use a Resource Instead

Use `$resourceClass` when you need:
- Type coercion or computed fields
- Nested resource relationships
- Consistent response shape regardless of runtime conditions

Use `visibleFields`/`hiddenFields` for simple field filtering without the overhead of a resource class.

---

## OpenAPI Impact

When no `$resourceClass` is set and visibility is configured, the OpenAPI generator builds the response schema from the visible fields:

- `$visibleFields` set → Schema contains only those properties.
- `$hiddenFields` set → Schema contains all model fields except blacklisted ones.
- Field types are inferred from the model's `$casts` and `$fillable`.
