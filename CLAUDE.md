# Prompt: Apilot — Field Visibility + Per-Action FormRequests

## Entwicklungsumgebung

Auf dem Host-System ist **kein PHP und kein Composer installiert**. Alle PHP- und Composer-Befehle müssen über Docker ausgeführt werden:

```bash
docker run --rm -v $(pwd):/app -w /app composer <befehl>
docker run --rm -v $(pwd):/app -w /app composer php ./vendor/bin/phpunit
```

---

## Voraussetzung

Das Package `didasto/apilot` ist vollständig implementiert mit Namespace `Didasto\Apilot`. Alle Tests sind grün.

**Lies den gesamten bestehenden Code im Package, bevor du Änderungen vornimmst.** Alle bestehenden Tests müssen weiterhin grün bleiben.

---

## Übersicht

Es gibt 2 Features:

1. **Field Visibility** — `visibleFields` (Whitelist) und `hiddenFields` (Blacklist) als Properties UND überschreibbare Methoden, um die Response-Felder zu steuern, wenn keine eigene Resource-Klasse gesetzt ist.
2. **Per-Action FormRequests** — Für jede der 5 CRUD-Actions eine eigene optionale FormRequest-Klasse, hauptsächlich für Authorization (`authorize()`) bei index, show und destroy.

---

## 1. Field Visibility

### Konzept

Wenn ein Controller **keine** `$resourceClass` definiert hat (nutzt `DefaultResource`), sollen die Felder über Whitelist und Blacklist gesteuert werden können. Whitelist wird zuerst angewendet, dann Blacklist. **Blacklist gewinnt immer.**

Ablauf:

```
Alle Felder des Models ($model->toArray())
  → visibleFields gesetzt? → Nur diese Felder behalten.
  → hiddenFields gesetzt?  → Diese Felder entfernen.
  → Ergebnis zurückgeben.
```

### Prioritätsreihenfolge

```
1. $resourceClass gesetzt?           → Resource nutzen, visibleFields/hiddenFields werden IGNORIERT.
2. visibleFields Methode existiert?  → Methode aufrufen → Ergebnis als Whitelist nutzen.
3. visibleFields Property nicht leer? → Property als Whitelist nutzen.
4. hiddenFields Methode existiert?   → Methode aufrufen → Ergebnis als Blacklist anwenden.
5. hiddenFields Property nicht leer?  → Property als Blacklist anwenden.
6. Nichts gesetzt?                   → Alle Felder (wie bisher, DefaultResource).
```

**Wichtig:** Schritt 2+3 (Whitelist) und Schritt 4+5 (Blacklist) werden **nacheinander** angewendet, nicht alternativ. Die Blacklist wird IMMER angewendet, auch wenn eine Whitelist gesetzt ist.

### Properties im Controller

```php
/**
 * Whitelist: Nur diese Felder in der Response anzeigen.
 * Wird ignoriert wenn $resourceClass gesetzt ist.
 * Kann durch die Methode visibleFields() überschrieben werden.
 *
 * @var array<int, string>
 */
protected array $visibleFields = [];

/**
 * Blacklist: Diese Felder aus der Response entfernen.
 * Wird NACH der Whitelist angewendet — Blacklist gewinnt immer.
 * Wird ignoriert wenn $resourceClass gesetzt ist.
 * Kann durch die Methode hiddenFields() überschrieben werden.
 *
 * @var array<int, string>
 */
protected array $hiddenFields = [];
```

### Methoden-Override (dynamisch)

Der Controller prüft ob eine gleichnamige Methode existiert. Wenn ja, hat die Methode Vorrang vor der Property. Die Methode erhält den aktuellen `Request` als Parameter, um berechtigungsbasierte Entscheidungen treffen zu können.

```php
/**
 * Dynamische Whitelist — überschreibt die Property $visibleFields.
 * Optional: Nur implementieren wenn dynamische Logik gebraucht wird.
 *
 * @return array<int, string>
 */
protected function visibleFields(Request $request): array
{
    // Beispiel: Admin sieht mehr Felder
    if ($request->user()?->isAdmin()) {
        return ['id', 'title', 'status', 'internal_notes', 'revenue'];
    }

    return ['id', 'title', 'status'];
}

/**
 * Dynamische Blacklist — überschreibt die Property $hiddenFields.
 * Optional: Nur implementieren wenn dynamische Logik gebraucht wird.
 *
 * @return array<int, string>
 */
protected function hiddenFields(Request $request): array
{
    if ($request->user()?->isAdmin()) {
        return ['password'];
    }

    return ['password', 'secret_key', 'internal_notes'];
}
```

### Interne Auflösung

Erstelle eine Methode die die Felder auflöst:

```php
/**
 * Löst die sichtbaren Felder auf und wendet Whitelist + Blacklist an.
 * Gibt null zurück wenn eine $resourceClass gesetzt ist (Resource übernimmt).
 *
 * @param array<string, mixed> $data — Die vollen Model-Daten (toArray())
 * @return array<string, mixed>|null — Gefilterte Daten, oder null wenn Resource zuständig ist.
 */
protected function applyFieldVisibility(array $data, Request $request): ?array
{
    // Wenn eine Resource-Klasse gesetzt ist, übernimmt die Resource
    if ($this->resourceClass !== null) {
        return null;
    }

    // Schritt 1: Whitelist auflösen (Methode hat Vorrang vor Property)
    $visible = $this->resolveVisibleFields($request);

    // Schritt 2: Wenn Whitelist vorhanden, nur diese Felder behalten
    if (!empty($visible)) {
        $data = array_intersect_key($data, array_flip($visible));
    }

    // Schritt 3: Blacklist auflösen (Methode hat Vorrang vor Property)
    $hidden = $this->resolveHiddenFields($request);

    // Schritt 4: Blacklist-Felder entfernen (Blacklist gewinnt IMMER)
    if (!empty($hidden)) {
        $data = array_diff_key($data, array_flip($hidden));
    }

    return $data;
}

/**
 * Löst die Whitelist auf: Methode hat Vorrang vor Property.
 */
private function resolveVisibleFields(Request $request): array
{
    // Prüfe ob eine Methode visibleFields() im konkreten Controller existiert
    // und ob sie die Methode aus der Basisklasse überschreibt.
    // Wenn die Methode überschrieben wurde → Methode aufrufen.
    // Sonst → Property nutzen.

    $reflection = new \ReflectionMethod($this, 'visibleFields');
    if ($reflection->getDeclaringClass()->getName() !== self::class) {
        // Methode wurde im Kind-Controller überschrieben
        return $this->visibleFields($request);
    }

    return $this->visibleFields;
}

/**
 * Löst die Blacklist auf: Methode hat Vorrang vor Property.
 */
private function resolveHiddenFields(Request $request): array
{
    $reflection = new \ReflectionMethod($this, 'hiddenFields');
    if ($reflection->getDeclaringClass()->getName() !== self::class) {
        return $this->hiddenFields($request);
    }

    return $this->hiddenFields;
}
```

**Achtung zum Reflection-Ansatz:** Die Basisklasse (`ModelCrudController`) muss die Methoden `visibleFields()` und `hiddenFields()` als überschreibbare Methoden definieren, die per Default die Property zurückgeben:

```php
// In ModelCrudController:
protected function visibleFields(Request $request): array
{
    return $this->visibleFields;
}

protected function hiddenFields(Request $request): array
{
    return $this->hiddenFields;
}
```

Die `resolveVisibleFields`/`resolveHiddenFields`-Methoden prüfen dann per Reflection, ob der konkrete Controller die Methode überschrieben hat. Wenn ja → Methode aufrufen. Wenn nein → Property direkt nutzen (über die Default-Methode). Da die Default-Methode einfach die Property zurückgibt, ist das Ergebnis identisch — aber der Reflection-Check ermöglicht es, die Methode als "aktiv überschrieben" zu erkennen.

**Einfachere Alternative ohne Reflection:** Da die Default-Methode ohnehin die Property zurückgibt, kann man die Methode IMMER aufrufen. Wenn der Nutzer sie überschreibt, bekommt er sein dynamisches Verhalten. Wenn nicht, bekommt er die Property. Kein Reflection nötig:

```php
protected function applyFieldVisibility(array $data, Request $request): ?array
{
    if ($this->resourceClass !== null) {
        return null;
    }

    // Methode aufrufen — gibt entweder die Property oder den dynamischen Wert zurück
    $visible = $this->visibleFields($request);
    if (!empty($visible)) {
        $data = array_intersect_key($data, array_flip($visible));
    }

    $hidden = $this->hiddenFields($request);
    if (!empty($hidden)) {
        $data = array_diff_key($data, array_flip($hidden));
    }

    return $data;
}
```

**Nutze diese einfachere Variante.** Kein Reflection, sauber, funktioniert.

### Anpassung der DefaultResource

Die `DefaultResource` muss die gefilterten Felder nutzen können. Da die Resource keinen Zugriff auf den Controller hat, muss der Controller die Felder-Filterung VOR der Resource-Erstellung anwenden.

**Ansatz:** Der Controller filtert die Daten und übergibt sie an die Response-Builder-Methoden. Wenn `applyFieldVisibility()` nicht `null` zurückgibt (also keine Resource-Klasse gesetzt ist), werden die gefilterten Daten direkt als Array in die Response gepackt — ohne eine Resource-Klasse zu nutzen.

Passe die `buildItemResponse`- und `buildPaginatedResponse`-Methoden an:

```php
protected function buildItemResponse(mixed $item, string $resourceClass, string $action, Request $request): JsonResponse
{
    // Prüfe ob Field Visibility greift
    $filteredData = $this->applyFieldVisibility($item->toArray(), $request);

    if ($filteredData !== null) {
        // Direkt die gefilterten Daten nutzen (keine Resource)
        $resolved = $filteredData;
    } else {
        // Resource nutzen (bisheriges Verhalten)
        $resolved = (new $resourceClass($item))->resolve($request);
    }

    // ... Rest der Response-Erstellung (Wrapper-Logik etc.)
}
```

Für paginierte Responses analog: Jedes Item in der Collection durch `applyFieldVisibility()` laufen lassen.

### Gilt für beide Controller-Typen

`visibleFields` und `hiddenFields` sollen in **beiden** Controllern funktionieren (`ModelCrudController` und `ServiceCrudController`). Beim `ServiceCrudController` gibt `find()` ein `mixed`-Objekt zurück — wenn es ein Array ist, direkt filtern. Wenn es ein Objekt mit `toArray()` ist, zuerst konvertieren. Wenn es keines von beiden ist, Felder-Filterung überspringen.

```php
private function itemToArray(mixed $item): array
{
    if (is_array($item)) {
        return $item;
    }

    if (method_exists($item, 'toArray')) {
        return $item->toArray();
    }

    if ($item instanceof \stdClass) {
        return (array) $item;
    }

    return [];
}
```

### Auswirkung auf OpenAPI-Generierung

Wenn **keine** `$resourceClass` gesetzt ist und `$visibleFields` oder `$hiddenFields` gesetzt sind, soll der `SchemaBuilder` daraus ein Response-Schema generieren:

- `$visibleFields` nicht leer → Schema enthält nur diese Properties.
- `$hiddenFields` nicht leer → Schema enthält alle Model-Felder AUSSER die Blacklist.
- Für die Model-Felder: Nutze Reflection auf das Model (z.B. `$fillable`, `$casts`, Migration-Spalten) um die Felder und Typen zu ermitteln. Fallback: alle Felder als `type: string`.

**Wichtig:** Die Methoden-Variante (`visibleFields(Request)`) kann zur Spec-Generierungszeit nicht ausgewertet werden (kein Request vorhanden). In diesem Fall sollen die Properties als Fallback dienen. Wenn die Properties leer sind und nur die Methode existiert → generisches `additionalProperties: true` Schema (wie bisher).

---

## 2. Per-Action FormRequests

### Konzept

Aktuell gibt es `$formRequestClass`, `$storeRequestClass` und `$updateRequestClass`. Erweitere das um alle 5 CRUD-Actions:

```php
// Bestehend (bleiben)
protected ?string $formRequestClass = null;         // Globaler Fallback
protected ?string $storeRequestClass = null;        // Store-spezifisch
protected ?string $updateRequestClass = null;       // Update-spezifisch

// Neu
protected ?string $indexRequestClass = null;        // Index-spezifisch
protected ?string $showRequestClass = null;         // Show-spezifisch
protected ?string $destroyRequestClass = null;      // Destroy-spezifisch
```

### Fallback-Kette

```
Action-spezifische Klasse → $formRequestClass → keine Validation/Authorization
```

Konkret:

| Action | 1. Priorität | 2. Priorität | 3. Wenn nichts gesetzt |
|--------|-------------|-------------|----------------------|
| index | `$indexRequestClass` | `$formRequestClass` | Keine Validation |
| show | `$showRequestClass` | `$formRequestClass` | Keine Validation |
| store | `$storeRequestClass` | `$formRequestClass` | Keine Validation |
| update | `$updateRequestClass` | `$formRequestClass` | Keine Validation |
| destroy | `$destroyRequestClass` | `$formRequestClass` | Keine Validation |

**Wichtig:** Für index, show und destroy geht es primär um die `authorize()`-Methode der FormRequest. Die `rules()` sind typischerweise leer. Aber das Package soll das nicht erzwingen — wenn der Nutzer Rules definiert, werden sie angewendet.

### Anpassung der `resolveFormRequest`-Methode

Erweitere die bestehende Methode:

```php
protected function resolveFormRequest(string $action): array
{
    $requestClass = match ($action) {
        'index'   => $this->indexRequestClass ?? $this->formRequestClass,
        'show'    => $this->showRequestClass ?? $this->formRequestClass,
        'store'   => $this->storeRequestClass ?? $this->formRequestClass,
        'update'  => $this->updateRequestClass ?? $this->formRequestClass,
        'destroy' => $this->destroyRequestClass ?? $this->formRequestClass,
        default   => $this->formRequestClass,
    };

    if ($requestClass === null) {
        return request()->all();
    }

    if (!class_exists($requestClass)) {
        throw new \LogicException(
            sprintf('FormRequest class %s does not exist.', $requestClass)
        );
    }

    /** @var \Illuminate\Foundation\Http\FormRequest $formRequest */
    $formRequest = app($requestClass);

    return $formRequest->validated();
}
```

### Aufruf in den Controller-Methoden

Für **index** und **show** soll die FormRequest aufgelöst werden, aber die `validated()`-Daten werden nicht für die Query gebraucht — es geht nur um die Authorization. Trotzdem muss `app($requestClass)` aufgerufen werden, damit Laravel die `authorize()`-Methode ausführt.

```php
public function index(Request $request): JsonResponse
{
    // NEU: FormRequest auflösen für Authorization
    $this->resolveAuthorization('index');

    // ... bestehende Logik (Query, Filtering, Sorting, Pagination, Hooks)
}

public function show(Request $request, int|string $id): JsonResponse
{
    // NEU: FormRequest auflösen für Authorization
    $this->resolveAuthorization('show');

    // ... bestehende Logik
}

public function destroy(Request $request, int|string $id): JsonResponse
{
    // NEU: FormRequest auflösen für Authorization
    $this->resolveAuthorization('destroy');

    // ... bestehende Logik (find, beforeDestroy-Hook, delete, afterDestroy-Hook)
}
```

### Separate Methode für Authorization-Only

Da index, show und destroy typischerweise keine Validation-Daten brauchen, erstelle eine separate Methode:

```php
/**
 * Löst die FormRequest-Klasse für eine Action auf, um authorize() auszuführen.
 * Gibt nichts zurück — dient nur der Authorization.
 * Wenn keine FormRequest-Klasse gesetzt ist, passiert nichts.
 * Wenn authorize() false zurückgibt, wirft Laravel automatisch eine 403-Response.
 */
protected function resolveAuthorization(string $action): void
{
    $requestClass = match ($action) {
        'index'   => $this->indexRequestClass,
        'show'    => $this->showRequestClass,
        'destroy' => $this->destroyRequestClass,
        default   => null,
    };

    // Fallback auf $formRequestClass nur für store/update (die resolveFormRequest nutzen)
    // Für index/show/destroy: NUR die spezifische Klasse nutzen, NICHT den Fallback
    // Grund: $formRequestClass hat typischerweise store/update-Rules die bei index/show keinen Sinn machen

    if ($requestClass === null) {
        return;
    }

    if (!class_exists($requestClass)) {
        throw new \LogicException(
            sprintf('FormRequest class %s does not exist.', $requestClass)
        );
    }

    // Instanziierung löst automatisch authorize() aus.
    // Wenn authorize() false zurückgibt → Laravel wirft AuthorizationException → 403
    app($requestClass);
}
```

**Wichtige Design-Entscheidung:** Für `index`, `show` und `destroy` soll der Fallback auf `$formRequestClass` **NICHT** greifen. Grund: `$formRequestClass` enthält typischerweise store/update-Validation-Rules (z.B. `'title' => 'required'`). Wenn diese bei einem GET-Request (index/show) angewendet werden, schlägt die Validation fehl, weil kein Body mitgesendet wird.

Deshalb:

| Action | Klasse | Fallback auf $formRequestClass? |
|--------|--------|-------------------------------|
| index | `$indexRequestClass` | **NEIN** |
| show | `$showRequestClass` | **NEIN** |
| store | `$storeRequestClass` | JA |
| update | `$updateRequestClass` | JA |
| destroy | `$destroyRequestClass` | **NEIN** |

### Gilt für beide Controller-Typen

`ModelCrudController` und `ServiceCrudController` sollen beide alle 5 Per-Action-Properties unterstützen.

### Verwendung durch den Nutzer

```php
use Didasto\Apilot\Attributes\ApiResource;
use Didasto\Apilot\Controllers\ModelCrudController;
use App\Models\User;

#[ApiResource(path: '/users')]
class UserController extends ModelCrudController
{
    protected string $model = User::class;

    // Authorization
    protected ?string $indexRequestClass = UserIndexRequest::class;
    protected ?string $showRequestClass = UserShowRequest::class;
    protected ?string $destroyRequestClass = UserDestroyRequest::class;

    // Validation + Authorization
    protected ?string $storeRequestClass = UserStoreRequest::class;
    protected ?string $updateRequestClass = UserUpdateRequest::class;

    // Fields
    protected array $hiddenFields = ['password', 'remember_token'];
}
```

```php
// Nur Authorization, keine Validation
class UserIndexRequest extends FormRequest
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

```php
class UserShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = User::find($this->route('id'));
        // User darf nur eigene Daten sehen, Admin darf alles
        return $this->user()?->isAdmin() || $this->user()?->id === $user?->id;
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
        // Nur Admins dürfen User löschen
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
```

### Auswirkung auf OpenAPI-Generierung

Die neuen Request-Klassen für index, show und destroy erzeugen **keine** Request-Body-Schemas in der Spec (da GET und DELETE keinen Body haben). ABER:

- Wenn `$indexRequestClass`, `$showRequestClass` oder `$destroyRequestClass` gesetzt ist UND eine Auth-Middleware aktiv ist → die Spec soll einen `security`-Block für diese Operations generieren (falls nicht schon vorhanden).
- Die `authorize()`-Logik kann nicht in die Spec übernommen werden — das ist Runtime-Verhalten.

---

## 3. Tests

### Neue Dateien

```
tests/
├── Feature/
│   ├── FieldVisibility/
│   │   ├── VisibleFieldsTest.php                (NEU)
│   │   ├── HiddenFieldsTest.php                 (NEU)
│   │   ├── CombinedFieldVisibilityTest.php      (NEU)
│   │   ├── DynamicFieldVisibilityTest.php       (NEU)
│   │   └── FieldVisibilityServiceControllerTest.php (NEU)
│   ├── PerActionRequests/
│   │   ├── IndexRequestTest.php                 (NEU)
│   │   ├── ShowRequestTest.php                  (NEU)
│   │   ├── DestroyRequestTest.php               (NEU)
│   │   ├── FallbackChainTest.php                (NEU)
│   │   └── PerActionRequestOpenApiTest.php      (NEU)
└── Fixtures/
    ├── Controllers/
    │   ├── WhitelistPostController.php          (NEU)
    │   ├── BlacklistPostController.php          (NEU)
    │   ├── CombinedVisibilityPostController.php (NEU)
    │   ├── DynamicVisibilityPostController.php  (NEU)
    │   ├── PerActionRequestPostController.php   (NEU)
    │   ├── VisibilityServiceController.php      (NEU)
    │   └── AuthorizedUserController.php         (NEU)
    ├── Requests/
    │   ├── IndexPostRequest.php                 (NEU)
    │   ├── ShowPostRequest.php                  (NEU)
    │   ├── DestroyPostRequest.php               (NEU)
    │   ├── AdminOnlyRequest.php                 (NEU — authorize() prüft isAdmin)
    │   └── OwnerOnlyRequest.php                 (NEU — authorize() prüft user_id)
    └── Models/
        └── UserWithAdmin.php                    (NEU — Einfaches User-Model mit isAdmin()-Methode)
```

### Fixture-Controller

#### `WhitelistPostController`

```php
class WhitelistPostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $visibleFields = ['id', 'title', 'status'];
}
```

#### `BlacklistPostController`

```php
class BlacklistPostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected array $hiddenFields = ['body', 'updated_at'];
}
```

#### `CombinedVisibilityPostController`

```php
class CombinedVisibilityPostController extends ModelCrudController
{
    protected string $model = Post::class;
    // Whitelist enthält "body", aber Blacklist entfernt es → body wird NICHT angezeigt
    protected array $visibleFields = ['id', 'title', 'body', 'status'];
    protected array $hiddenFields = ['body'];
}
```

#### `DynamicVisibilityPostController`

```php
class DynamicVisibilityPostController extends ModelCrudController
{
    protected string $model = Post::class;

    protected function visibleFields(Request $request): array
    {
        if ($request->headers->get('X-Role') === 'admin') {
            return ['id', 'title', 'body', 'status', 'created_at', 'updated_at'];
        }
        return ['id', 'title', 'status'];
    }

    protected function hiddenFields(Request $request): array
    {
        return []; // Keine Blacklist in diesem Test
    }
}
```

*(Nutze einen Custom Header statt echte Auth, um den Test einfach zu halten.)*

#### `PerActionRequestPostController`

```php
class PerActionRequestPostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $indexRequestClass = IndexPostRequest::class;
    protected ?string $showRequestClass = ShowPostRequest::class;
    protected ?string $storeRequestClass = StorePostRequest::class;
    protected ?string $updateRequestClass = UpdatePostRequest::class;
    protected ?string $destroyRequestClass = DestroyPostRequest::class;
}
```

### Tests: Field Visibility

#### `VisibleFieldsTest.php`

```
1. testWhitelistShowsOnlySpecifiedFields
   — WhitelistPostController, GET show → Response hat NUR "id", "title", "status". Kein "body", kein "created_at".

2. testWhitelistWorksOnIndex
   — WhitelistPostController, GET index → Jedes Item hat NUR "id", "title", "status".

3. testWhitelistWorksOnStore
   — WhitelistPostController, POST store → Response hat NUR "id", "title", "status".

4. testWhitelistWorksOnUpdate
   — WhitelistPostController, PUT update → Response hat NUR "id", "title", "status".

5. testEmptyWhitelistShowsAllFields
   — Controller mit visibleFields = [] → alle Felder sichtbar (wie bisher).

6. testWhitelistWithNonExistentFieldIsIgnored
   — visibleFields = ['id', 'title', 'nonexistent'] → Response hat "id", "title". "nonexistent" fehlt einfach.
```

#### `HiddenFieldsTest.php`

```
7. testBlacklistHidesSpecifiedFields
   — BlacklistPostController, GET show → Response hat NICHT "body" und NICHT "updated_at". Alle anderen Felder sind da.

8. testBlacklistWorksOnIndex
   — BlacklistPostController, GET index → Kein Item hat "body" oder "updated_at".

9. testBlacklistWorksOnStore
   — BlacklistPostController, POST store → Response hat NICHT "body" und NICHT "updated_at".

10. testBlacklistWorksOnUpdate
    — BlacklistPostController, PUT update → Response hat NICHT "body" und NICHT "updated_at".

11. testEmptyBlacklistShowsAllFields
    — Controller mit hiddenFields = [] → alle Felder sichtbar (wie bisher).

12. testBlacklistWithNonExistentFieldIsIgnored
    — hiddenFields = ['nonexistent'] → alle Felder sichtbar, kein Crash.
```

#### `CombinedFieldVisibilityTest.php`

```
13. testBlacklistOverridesWhitelist
    — CombinedVisibilityPostController
    — visibleFields: ['id', 'title', 'body', 'status'], hiddenFields: ['body']
    — GET show → Response hat "id", "title", "status". KEIN "body" (Blacklist gewinnt).

14. testBlacklistAppliedAfterWhitelist
    — Erstelle Controller mit visibleFields=['id','title','body'], hiddenFields=['body']
    — GET show → "id", "title". "body" durch Blacklist entfernt.

15. testResourceClassIgnoresVisibilitySettings
    — Controller mit $resourceClass gesetzt UND $visibleFields gesetzt
    — GET show → Resource bestimmt die Felder, visibleFields wird ignoriert.

16. testResourceClassIgnoresHiddenFields
    — Controller mit $resourceClass gesetzt UND $hiddenFields gesetzt
    — GET show → Resource bestimmt die Felder, hiddenFields wird ignoriert.

17. testNoVisibilityNoBlacklistShowsAllFields
    — Controller ohne visibleFields, ohne hiddenFields, ohne resourceClass
    — GET show → alle Model-Felder sichtbar (DefaultResource-Verhalten wie bisher).
```

#### `DynamicFieldVisibilityTest.php`

```
18. testDynamicVisibleFieldsForAdmin
    — DynamicVisibilityPostController, GET show mit Header X-Role: admin
    — Response hat alle 6 Felder.

19. testDynamicVisibleFieldsForNonAdmin
    — DynamicVisibilityPostController, GET show ohne X-Role Header
    — Response hat nur "id", "title", "status".

20. testDynamicVisibleFieldsOnIndex
    — GET index mit X-Role: admin → alle Items haben 6 Felder.
    — GET index ohne X-Role → alle Items haben 3 Felder.

21. testMethodOverridesProperty
    — Controller hat Property visibleFields = ['id'] UND Methode die ['id', 'title'] zurückgibt
    — GET show → Response hat "id", "title" (Methode gewinnt).
```

#### `FieldVisibilityServiceControllerTest.php`

```
22. testVisibleFieldsWorksOnServiceController
    — ServiceCrudController mit visibleFields = ['id', 'name']
    — GET show → nur "id", "name".

23. testHiddenFieldsWorksOnServiceController
    — ServiceCrudController mit hiddenFields = ['secret']
    — GET show → "secret" fehlt.

24. testServiceControllerWithStdClassItems
    — Service gibt stdClass-Objekte zurück → visibleFields filtert korrekt.

25. testServiceControllerWithArrayItems
    — Service gibt Arrays zurück → visibleFields filtert korrekt.
```

### Tests: Per-Action FormRequests

#### `IndexRequestTest.php`

```
26. testIndexWithoutRequestClassAllowsAccess
    — Controller ohne $indexRequestClass → GET index → 200.

27. testIndexWithRequestClassThatAuthorizes
    — IndexPostRequest mit authorize() → true → GET index → 200.

28. testIndexWithRequestClassThatDenies
    — IndexPostRequest mit authorize() → false → GET index → 403.

29. testIndexRequestDoesNotFallBackToFormRequestClass
    — Controller mit $formRequestClass (hat required-Rules), ohne $indexRequestClass
    — GET index → 200 (NICHT 422, weil $formRequestClass NICHT als Fallback für index genutzt wird).
```

#### `ShowRequestTest.php`

```
30. testShowWithoutRequestClassAllowsAccess
    — Controller ohne $showRequestClass → GET show → 200.

31. testShowWithRequestClassThatAuthorizes
    — ShowPostRequest mit authorize() → true → GET show → 200.

32. testShowWithRequestClassThatDenies
    — ShowPostRequest mit authorize() → false → GET show → 403.

33. testShowRequestDoesNotFallBackToFormRequestClass
    — Controller mit $formRequestClass (hat required-Rules), ohne $showRequestClass
    — GET show → 200 (NICHT 422).
```

#### `DestroyRequestTest.php`

```
34. testDestroyWithoutRequestClassAllowsAccess
    — Controller ohne $destroyRequestClass → DELETE → 204.

35. testDestroyWithRequestClassThatAuthorizes
    — DestroyPostRequest mit authorize() → true → DELETE → 204.

36. testDestroyWithRequestClassThatDenies
    — DestroyPostRequest mit authorize() → false → DELETE → 403.

37. testDestroyRequestDoesNotFallBackToFormRequestClass
    — Controller mit $formRequestClass (hat required-Rules), ohne $destroyRequestClass
    — DELETE → 204 (NICHT 422).

38. testDestroyRequestAndBeforeDestroyHookBothActive
    — Controller mit $destroyRequestClass (authorize → true) UND beforeDestroy-Hook (return false)
    — DELETE → 403 (Hook verhindert Löschung, Request hat aber autorisiert). Beide laufen.
```

#### `FallbackChainTest.php`

```
39. testStoreRequestFallsBackToFormRequestClass
    — Controller mit $formRequestClass, ohne $storeRequestClass
    — POST store → nutzt $formRequestClass für Validation.

40. testUpdateRequestFallsBackToFormRequestClass
    — Controller mit $formRequestClass, ohne $updateRequestClass
    — PUT update → nutzt $formRequestClass für Validation.

41. testStoreRequestOverridesFormRequestClass
    — Controller mit $formRequestClass UND $storeRequestClass
    — POST store → nutzt $storeRequestClass (spezifisch gewinnt).

42. testAllFiveRequestClassesCanBeSetSimultaneously
    — Controller mit allen 5 Request-Klassen gesetzt
    — Jede Action nutzt ihre spezifische Klasse.

43. testNoRequestClassesSetAllowsEverything
    — Controller ganz ohne Request-Klassen
    — Alle 5 Actions funktionieren ohne Authorization oder Validation.
```

#### `PerActionRequestOpenApiTest.php`

```
44. testIndexRequestClassDoesNotGenerateRequestBodySchema
    — Controller mit $indexRequestClass → Spec hat KEINEN requestBody für GET index.

45. testShowRequestClassDoesNotGenerateRequestBodySchema
    — Controller mit $showRequestClass → Spec hat KEINEN requestBody für GET show.

46. testDestroyRequestClassDoesNotGenerateRequestBodySchema
    — Controller mit $destroyRequestClass → Spec hat KEINEN requestBody für DELETE.

47. testStoreAndUpdateRequestClassesGenerateSeparateSchemas
    — Controller mit $storeRequestClass + $updateRequestClass → Spec hat beide Schemas.

48. testVisibleFieldsReflectedInOpenApiResponseSchema
    — Controller mit visibleFields = ['id', 'title', 'status'] → Response-Schema hat nur diese 3 Properties.

49. testHiddenFieldsReflectedInOpenApiResponseSchema
    — Controller mit hiddenFields = ['body'] → Response-Schema hat NICHT "body".
```

---

## 4. Dokumentation aktualisieren

### `docs/03-model-crud-controller.md`

- Ergänze Abschnitt "Field Visibility" mit visibleFields/hiddenFields Properties und Methoden.
- Erkläre die Priorität (Resource > Whitelist > Blacklist).
- Zeige Beispiel mit statischen Properties und dynamischen Methoden.

### `docs/04-service-crud-controller.md`

- Ergänze, dass visibleFields/hiddenFields auch beim ServiceCrudController funktionieren.

### `docs/09-hooks.md`

- Ergänze Hinweis, dass per-Action FormRequests VOR den Hooks ausgeführt werden (Authorization passiert vor beforeStore etc.).

### `docs/12-error-handling.md`

- Ergänze 403-Response durch fehlgeschlagene FormRequest-Authorization.

### Neues Doku-File: `docs/16-field-visibility.md`

Erstelle eine neue Doku-Seite:

- Konzept: Whitelist + Blacklist
- Reihenfolge: Whitelist → Blacklist (Blacklist gewinnt)
- Property-basiert vs. Methoden-basiert
- Dynamische Felder basierend auf Berechtigungen
- Wann Resource-Klasse statt Visibility nutzen
- Beispiele: einfach, kombiniert, dynamisch

### Neues Doku-File: `docs/17-authorization.md`

Erstelle eine neue Doku-Seite:

- Per-Action FormRequests
- Alle 5 Properties mit Erklärung
- Fallback-Kette (Tabelle)
- Beispiele: Admin-Only Index, Owner-Only Show, Admin-Only Destroy
- Zusammenspiel mit Hooks (beforeDestroy + destroyRequestClass)
- Hinweis: authorize() für Authorization, rules() für Validation

### `docs/README.md`

- Inhaltsverzeichnis um die neuen Seiten erweitern.

---

## 5. Regeln

- **Rückwärtskompatibilität:** Alle bestehenden Tests müssen grün bleiben. Alle neuen Properties haben leere Defaults und ändern das bisherige Verhalten nicht.
- **visibleFields + hiddenFields werden ignoriert wenn $resourceClass gesetzt ist.** Die Resource hat immer Vorrang.
- **Blacklist gewinnt immer.** Auch wenn ein Feld in der Whitelist steht.
- **Kein Fallback von $formRequestClass auf index/show/destroy.** Nur store und update nutzen den Fallback.
- **Methoden haben Vorrang vor Properties.** Die Default-Methode gibt die Property zurück.
- **Beide Controller-Typen** müssen beide Features unterstützen.
- **OpenAPI-Spec** reflektiert visibleFields/hiddenFields im Response-Schema und erzeugt keine Request-Body-Schemas für index/show/destroy.
- **Alle Coding-Regeln gelten.** Alle Tests am Ende ausführen.