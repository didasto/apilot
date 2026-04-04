# Per-Action Authorization

Assign a dedicated `FormRequest` class to each CRUD action for fine-grained authorization and validation control.

---

## Properties

```php
use Didasto\Apilot\Controllers\ModelCrudController;

class UserController extends ModelCrudController
{
    protected string $model = User::class;

    // Global fallback (existing behavior)
    protected ?string $formRequestClass = null;

    // Store / Update — for validation + authorization
    protected ?string $storeRequestClass = UserStoreRequest::class;
    protected ?string $updateRequestClass = UserUpdateRequest::class;

    // Index / Show / Destroy — primarily for authorization
    protected ?string $indexRequestClass = UserIndexRequest::class;
    protected ?string $showRequestClass = UserShowRequest::class;
    protected ?string $destroyRequestClass = UserDestroyRequest::class;
}
```

---

## Fallback Chain

| Action  | 1st Priority           | Fallback to `$formRequestClass`? |
|---------|------------------------|----------------------------------|
| index   | `$indexRequestClass`   | **NO**                           |
| show    | `$showRequestClass`    | **NO**                           |
| store   | `$storeRequestClass`   | YES                              |
| update  | `$updateRequestClass`  | YES                              |
| destroy | `$destroyRequestClass` | **NO**                           |

`$formRequestClass` is **not** used as a fallback for `index`, `show`, and `destroy`. This prevents validation rules meant for write operations from being applied to read/delete requests.

---

## Authorization Only (index, show, destroy)

For read and delete actions, you typically only need `authorize()`. Keep `rules()` empty:

```php
class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return []; // No input validation for listing
    }
}
```

```php
class UserShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = User::find($this->route('id'));
        return $this->user()?->isAdmin()
            || $this->user()?->id === $user?->id;
    }

    public function rules(): array
    {
        return [];
    }
}
```

```php
class UserDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
```

---

## Validation + Authorization (store, update)

For write actions, use `rules()` as usual:

```php
class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ];
    }
}
```

---

## Interaction with Hooks

Authorization via `FormRequest` runs **before** lifecycle hooks. For `destroy`, the order is:

1. `resolveAuthorization('destroy')` — `destroyRequestClass::authorize()` checked first.
2. `findOrFail($id)` — fetch the model.
3. `beforeDestroy($item, $request)` — hook runs next.
4. If `beforeDestroy()` returns `false` → 403 (ActionNotAllowedException).
5. `$item->delete()` — actual deletion.

Both the FormRequest authorization and the `beforeDestroy` hook can independently block a delete operation.

---

## OpenAPI Impact

`indexRequestClass`, `showRequestClass`, and `destroyRequestClass` do **not** generate `requestBody` schemas in the OpenAPI spec (GET and DELETE operations have no request body). They only affect runtime authorization.

`storeRequestClass` and `updateRequestClass` generate separate request body schemas as before.
