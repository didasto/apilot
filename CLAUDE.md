# Prompt: Apilot — Usability Verbesserungen

## Entwicklungsumgebung

Auf dem Host-System ist **kein PHP und kein Composer installiert**. Alle PHP- und Composer-Befehle müssen über Docker ausgeführt werden:

```bash
docker run --rm -v $(pwd):/app -w /app composer <befehl>
docker run --rm -v $(pwd):/app -w /app composer php ./vendor/bin/phpunit
```

---

## Voraussetzung

Das Package `didasto/apilot` ist vollständig implementiert mit Namespace `Didasto\Apilot`. Alle Tests sind grün. Die Dokumentation liegt im `docs/`-Verzeichnis.

**Lies den gesamten bestehenden Code im Package, bevor du Änderungen vornimmst.** Alle bestehenden Tests müssen weiterhin grün bleiben.

---

## Übersicht der Änderungen

Es gibt 4 Verbesserungen:

1. **Route-Attribute** — Routen direkt am Controller per PHP-Attribut definieren statt in einer separaten `routes/api.php`.
2. **Service-Interface mit Defaults** — `CrudServiceInterface` soll Default-Implementierungen mit "Not Implemented"-Exceptions bekommen, damit nur die tatsächlich genutzten Methoden überschrieben werden müssen.
3. **Getrennte FormRequests für Store und Update** — Statt einer einzigen `$formRequestClass` soll es separate `$storeRequestClass` und `$updateRequestClass` geben.
4. **OpenAPI Required-Fields Fix** — `required`-Felder aus FormRequests müssen korrekt in der OpenAPI-Spec erscheinen.

---

## 1. Route-Attribute (`#[ApiResource]`)

### Konzept

Statt Routen in einer separaten `routes/api.php` über den `CrudRouteRegistrar` zu definieren, soll der Nutzer die Routen direkt am Controller per PHP-Attribut deklarieren können. **Beide Wege sollen parallel funktionieren** — der `CrudRouteRegistrar` bleibt bestehen, das Attribut ist eine zusätzliche Option.

### Neue Datei: `src/Attributes/ApiResource.php`

```php
namespace Didasto\Apilot\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ApiResource
{
    /**
     * @param string $path                    — URI-Pfad der Resource (z.B. '/posts', '/api/v1/comments')
     * @param array<int, string>|null $only   — Nur diese Actions registrieren. null = alle.
     * @param array<int, string>|null $except — Diese Actions ausschließen. null = keine.
     * @param string|null $name               — Route-Name-Prefix (z.B. 'api.v1.posts'). null = auto-generiert.
     * @param array<int, string> $middleware   — Zusätzliche Middleware für diese Resource.
     */
    public function __construct(
        public readonly string $path,
        public readonly ?array $only = null,
        public readonly ?array $except = null,
        public readonly ?string $name = null,
        public readonly array $middleware = [],
    ) {}
}
```

### Verwendung durch den Nutzer

```php
namespace App\Http\Controllers\Api;

use Didasto\Apilot\Attributes\ApiResource;
use Didasto\Apilot\Controllers\ModelCrudController;
use App\Models\Post;

#[ApiResource(
    path: '/posts',
    only: ['index', 'show', 'store'],
    name: 'api.v1.posts',
    middleware: ['auth:sanctum'],
)]
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $storeRequestClass = \App\Http\Requests\StorePostRequest::class;
    protected ?string $resourceClass = \App\Http\Resources\PostResource::class;
}
```

```php
// Minimale Variante — alle CRUD-Routen, keine Extra-Middleware, Auto-Name
#[ApiResource(path: '/tags')]
class TagController extends ModelCrudController
{
    protected string $model = Tag::class;
}
```

```php
// ServiceCrudController mit Attribut
#[ApiResource(path: '/external-products', only: ['index', 'show'])]
class ExternalProductController extends ServiceCrudController
{
    protected string $serviceClass = ExternalProductService::class;
}
```

### Neue Datei: `src/Routing/AttributeRouteRegistrar.php`

Diese Klasse scannt Controller-Klassen nach dem `#[ApiResource]`-Attribut und registriert die Routen.

```php
namespace Didasto\Apilot\Routing;

class AttributeRouteRegistrar
{
    public function __construct(
        private readonly RouteRegistry $registry,
    ) {}

    /**
     * Scannt die angegebenen Controller-Klassen nach #[ApiResource] Attributen
     * und registriert die Routen.
     *
     * @param array<int, string> $controllerClasses — Vollqualifizierte Klassennamen
     */
    public function register(array $controllerClasses): void
    {
        foreach ($controllerClasses as $controllerClass) {
            $this->registerController($controllerClass);
        }
    }

    /**
     * Scannt ein Verzeichnis nach Controller-Klassen mit #[ApiResource] Attribut.
     *
     * @param string $directory — Absoluter Pfad zum Verzeichnis (z.B. app_path('Http/Controllers/Api'))
     * @param string $namespace — Basis-Namespace (z.B. 'App\\Http\\Controllers\\Api')
     */
    public function registerDirectory(string $directory, string $namespace): void
    {
        // 1. Alle .php-Dateien im Verzeichnis finden (rekursiv)
        // 2. Klassenname aus Dateiname + Namespace ableiten
        // 3. Prüfen ob die Klasse das #[ApiResource] Attribut hat
        // 4. Wenn ja: registerController() aufrufen
    }

    private function registerController(string $controllerClass): void
    {
        $reflection = new \ReflectionClass($controllerClass);
        $attributes = $reflection->getAttributes(ApiResource::class);

        if (empty($attributes)) {
            return;
        }

        $apiResource = $attributes[0]->newInstance();

        // Aktive Actions ermitteln (analog zum CrudRouteRegistrar)
        $allActions = ['index', 'show', 'store', 'update', 'destroy'];

        if ($apiResource->only !== null) {
            $actions = array_intersect($allActions, $apiResource->only);
        } elseif ($apiResource->except !== null) {
            $actions = array_diff($allActions, $apiResource->except);
        } else {
            $actions = $allActions;
        }

        // Resource-Name aus dem Pfad ableiten (z.B. '/posts' → 'posts', '/api/v1/comments' → 'comments')
        $resourceName = trim(basename($apiResource->path), '/');

        // Route-Name generieren wenn nicht angegeben
        $routeName = $apiResource->name ?? $resourceName;

        // Prefix aus dem Pfad ableiten: Alles vor dem letzten Segment
        // '/posts' → prefix = config('apilot.prefix')
        // '/api/v1/posts' → prefix = 'api/v1'
        $pathSegments = explode('/', trim($apiResource->path, '/'));
        if (count($pathSegments) > 1) {
            $prefix = implode('/', array_slice($pathSegments, 0, -1));
        } else {
            $prefix = config('apilot.prefix', 'api');
        }

        // Middleware: Globale Config-Middleware + per-Resource-Middleware
        $middleware = array_merge(
            config('apilot.middleware', ['api']),
            $apiResource->middleware,
        );

        // Routen registrieren (analog zum CrudRouteRegistrar)
        // Nutze Laravel's Route-Facade
        \Illuminate\Support\Facades\Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () use ($controllerClass, $resourceName, $actions, $routeName) {
                foreach ($actions as $action) {
                    // Registriere die Route je nach Action
                    // z.B. Route::get($resourceName, [$controllerClass, 'index'])->name("{$routeName}.index")
                    // Gleiche Logik wie im bestehenden CrudRouteRegistrar
                }
            });

        // In der RouteRegistry registrieren (für OpenAPI-Generierung)
        $this->registry->register(new RouteEntry(
            resourceName: $resourceName,
            controllerClass: $controllerClass,
            actions: array_values($actions),
            middleware: $middleware,
            prefix: $prefix,
        ));
    }
}
```

### Anpassung des ServiceProviders

Der `ApilotServiceProvider` soll den `AttributeRouteRegistrar` als Singleton registrieren und optional automatisches Scanning ermöglichen.

Füge eine neue Config-Option hinzu:

```php
// In config/apilot.php
'auto_discover' => [
    /*
    | Aktiviert das automatische Scannen von Controller-Verzeichnissen
    | nach #[ApiResource] Attributen.
    */
    'enabled' => false,

    /*
    | Verzeichnisse die gescannt werden sollen.
    | Jeder Eintrag ist ein Array mit 'directory' und 'namespace'.
    */
    'directories' => [
        [
            'directory' => app_path('Http/Controllers/Api'),
            'namespace' => 'App\\Http\\Controllers\\Api',
        ],
    ],
],
```

Im ServiceProvider (boot-Methode):

```php
if (config('apilot.auto_discover.enabled', false)) {
    $registrar = app(AttributeRouteRegistrar::class);
    foreach (config('apilot.auto_discover.directories', []) as $entry) {
        $registrar->registerDirectory($entry['directory'], $entry['namespace']);
    }
}
```

### Nutzung ohne Auto-Discovery

Der Nutzer kann den `AttributeRouteRegistrar` auch manuell nutzen, z.B. in einem ServiceProvider oder in `routes/api.php`:

```php
// In AppServiceProvider::boot() oder routes/api.php
use Didasto\Apilot\Routing\AttributeRouteRegistrar;

app(AttributeRouteRegistrar::class)->register([
    \App\Http\Controllers\Api\PostController::class,
    \App\Http\Controllers\Api\TagController::class,
]);
```

Oder mit Directory-Scan:

```php
app(AttributeRouteRegistrar::class)->registerDirectory(
    app_path('Http/Controllers/Api'),
    'App\\Http\\Controllers\\Api',
);
```

### Wichtig

- **Beide Wege (Attribut + CrudRouteRegistrar) müssen parallel funktionieren.** Der CrudRouteRegistrar bleibt unverändert.
- **OpenAPI-Generierung:** Der `AttributeRouteRegistrar` nutzt dieselbe `RouteRegistry` wie der `CrudRouteRegistrar`. Die OpenAPI-Generierung funktioniert daher automatisch für beide Wege.
- **Priorität:** Wenn dieselbe Resource sowohl über Attribut als auch über CrudRouteRegistrar registriert wird, werden die Routen doppelt registriert (kein Schutz dagegen nötig, aber kein Crash).

---

## 2. Service-Interface mit Defaults

### Problem

Aktuell muss ein Service, der `CrudServiceInterface` implementiert, **alle 5 Methoden** implementieren — auch wenn der Controller nur `index` und `show` nutzt. Das erzeugt unnötigen Boilerplate.

### Lösung

Ersetze das `CrudServiceInterface` durch eine Kombination aus Interface + abstrakte Basisklasse. Das Interface bleibt für Type-Hinting bestehen, die abstrakte Klasse liefert Default-Implementierungen die eine `NotImplementedException` werfen.

### Neue Datei: `src/Exceptions/NotImplementedException.php`

```php
namespace Didasto\Apilot\Exceptions;

use Illuminate\Http\JsonResponse;

class NotImplementedException extends \RuntimeException
{
    public function __construct(string $method)
    {
        parent::__construct(
            sprintf('Method %s is not implemented.', $method)
        );
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            data: [
                'error' => [
                    'message' => $this->getMessage(),
                    'status'  => 501,
                ],
            ],
            status: 501,
        );
    }
}
```

### Neue Datei: `src/Services/AbstractCrudService.php`

```php
namespace Didasto\Apilot\Services;

use Didasto\Apilot\Contracts\CrudServiceInterface;
use Didasto\Apilot\Dto\PaginatedResult;
use Didasto\Apilot\Dto\PaginationParams;
use Didasto\Apilot\Exceptions\NotImplementedException;

abstract class AbstractCrudService implements CrudServiceInterface
{
    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        throw new NotImplementedException(static::class . '::list');
    }

    public function find(int|string $id): mixed
    {
        throw new NotImplementedException(static::class . '::find');
    }

    public function create(array $data): mixed
    {
        throw new NotImplementedException(static::class . '::create');
    }

    public function update(int|string $id, array $data): mixed
    {
        throw new NotImplementedException(static::class . '::update');
    }

    public function delete(int|string $id): bool
    {
        throw new NotImplementedException(static::class . '::delete');
    }
}
```

### Anpassung des `CrudServiceInterface`

Das Interface bleibt **unverändert**. Es dient weiterhin als Vertrag. Die `AbstractCrudService` implementiert es mit Defaults.

### Auswirkung auf den Nutzer

**Vorher (muss alle implementieren):**

```php
class ExternalProductService implements CrudServiceInterface
{
    public function list(...): PaginatedResult { /* Logik */ }
    public function find(...): mixed { /* Logik */ }
    public function create(...): mixed { throw new \Exception('Not supported'); }
    public function update(...): mixed { throw new \Exception('Not supported'); }
    public function delete(...): bool { throw new \Exception('Not supported'); }
}
```

**Nachher (nur das implementieren was gebraucht wird):**

```php
use Didasto\Apilot\Services\AbstractCrudService;

class ExternalProductService extends AbstractCrudService
{
    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        // Nur diese Methode wird gebraucht
    }

    public function find(int|string $id): mixed
    {
        // Und diese
    }

    // create, update, delete müssen NICHT überschrieben werden.
    // Wenn sie aufgerufen werden, kommt automatisch ein 501 Not Implemented.
}
```

### Wichtig

- **Das bestehende `CrudServiceInterface` bleibt unverändert.** Bestehende Services die das Interface direkt implementieren funktionieren weiterhin.
- **`AbstractCrudService` ist optional.** Nutzer können weiterhin das Interface direkt implementieren.
- **Der `ServiceCrudController` muss keine Änderung erfahren** — er prüft weiterhin gegen `CrudServiceInterface`. Die `NotImplementedException` wird vom Exception-Handler als 501-Response gerendert.
- **Bestehende Tests dürfen nicht brechen.**

---

## 3. Getrennte FormRequests für Store und Update

### Problem

Aktuell gibt es nur eine einzige `$formRequestClass` für beide Operationen (store und update). In der Praxis haben Store und Update oft unterschiedliche Regeln:

- **Store:** `title` ist required, `published_at` ist required
- **Update:** Nur `image` und `published_at` dürfen geändert werden, `title` ist gar nicht erlaubt

### Lösung

Führe zwei neue Properties ein: `$storeRequestClass` und `$updateRequestClass`. Die bestehende `$formRequestClass` bleibt als Fallback.

### Änderungen am `ModelCrudController` und `ServiceCrudController`

Füge folgende Properties hinzu:

```php
/**
 * FormRequest-Klasse speziell für die store-Action.
 * Hat Vorrang vor $formRequestClass für store.
 */
protected ?string $storeRequestClass = null;

/**
 * FormRequest-Klasse speziell für die update-Action.
 * Hat Vorrang vor $formRequestClass für update.
 */
protected ?string $updateRequestClass = null;
```

### Auflösungslogik (Priorität)

Passe die `resolveFormRequest()`-Methode an (oder erstelle eine neue die den Action-Kontext kennt):

```php
protected function resolveFormRequest(string $action): array
{
    $requestClass = match ($action) {
        'store'  => $this->storeRequestClass ?? $this->formRequestClass,
        'update' => $this->updateRequestClass ?? $this->formRequestClass,
        default  => $this->formRequestClass,
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

Ändere die Aufrufe in `store()` und `update()`:

```php
// In store():
$validated = $this->resolveFormRequest('store');

// In update():
$validated = $this->resolveFormRequest('update');
```

### Verwendung durch den Nutzer

```php
#[ApiResource(path: '/posts')]
class PostController extends ModelCrudController
{
    protected string $model = Post::class;

    // Store braucht alle Felder
    protected ?string $storeRequestClass = StorePostRequest::class;

    // Update erlaubt nur image und published_at
    protected ?string $updateRequestClass = UpdatePostRequest::class;

    // resourceClass für Responses
    protected ?string $resourceClass = PostResource::class;
}
```

```php
// app/Http/Requests/StorePostRequest.php
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'description'      => 'required|string',
            'short_description'=> 'required|string|max:500',
            'image'            => 'required|url',
            'published_at'     => 'required|date',
        ];
    }
}
```

```php
// app/Http/Requests/UpdatePostRequest.php
class UpdatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image'        => 'sometimes|url',
            'published_at' => 'sometimes|date',
        ];
    }
}
```

### Rückwärtskompatibilität

- **`$formRequestClass` bleibt bestehen** und funktioniert weiterhin als einzige Property wenn `$storeRequestClass` und `$updateRequestClass` nicht gesetzt sind.
- **Priorität:** Spezifisch (`$storeRequestClass` / `$updateRequestClass`) überschreibt Allgemein (`$formRequestClass`).
- **Alle drei können gleichzeitig gesetzt sein:** `$formRequestClass` als Fallback, `$storeRequestClass` für store-spezifische Regeln, `$updateRequestClass` für update-spezifische Regeln.
- **Bestehende Tests dürfen nicht brechen.**

### Auswirkung auf OpenAPI-Generierung

Die OpenAPI-Generierung muss angepasst werden:

- Wenn `$storeRequestClass` gesetzt ist:
    - POST (store) nutzt das Schema von `$storeRequestClass` → Schema-Name: `{Resource}StoreRequest` (z.B. `PostStoreRequest`)
    - PUT (update) nutzt das Schema von `$updateRequestClass` (wenn gesetzt) oder `$formRequestClass` → Schema-Name: `{Resource}UpdateRequest` (z.B. `PostUpdateRequest`)
- Wenn nur `$formRequestClass` gesetzt ist (bisheriges Verhalten):
    - Beide nutzen dasselbe Schema → Schema-Name: `{Resource}Request` (wie bisher)
- Wenn `$storeRequestClass` UND `$updateRequestClass` identisch sind:
    - Nur ein Schema erstellen, nicht duplizieren.

#### Anpassungen im `SchemaBuilder`:

Der `SchemaBuilder` muss fähig sein, mehrere Request-Schemas pro Resource zu erzeugen. Füge eine Methode hinzu oder erweitere die bestehende:

```php
/**
 * Ermittelt die Schema-Namen und -Klassen für eine Resource.
 *
 * @return array<string, string> — Key = Schema-Name, Value = FormRequest-Klassenname
 */
public function resolveRequestSchemas(
    string $resourceName,
    ?string $formRequestClass,
    ?string $storeRequestClass,
    ?string $updateRequestClass,
): array
{
    $schemas = [];

    $storeClass = $storeRequestClass ?? $formRequestClass;
    $updateClass = $updateRequestClass ?? $formRequestClass;

    if ($storeClass !== null && $updateClass !== null && $storeClass === $updateClass) {
        // Beide gleich → ein Schema
        $schemas["{$resourceName}Request"] = $storeClass;
    } else {
        if ($storeClass !== null) {
            $schemas["{$resourceName}StoreRequest"] = $storeClass;
        }
        if ($updateClass !== null) {
            $schemas["{$resourceName}UpdateRequest"] = $updateClass;
        }
    }

    return $schemas;
}
```

#### Anpassungen im `PathBuilder`:

- POST (store): `$ref` zeigt auf `{Resource}StoreRequest` (wenn vorhanden) oder `{Resource}Request`
- PUT (update): `$ref` zeigt auf `{Resource}UpdateRequest` (wenn vorhanden) oder `{Resource}Request`

#### Zugriff auf die neuen Properties via Reflection:

Der `PathBuilder` / `OpenApiGenerator` muss die neuen Properties `$storeRequestClass` und `$updateRequestClass` per Reflection auslesen (analog zu den bestehenden Properties).

---

## 4. OpenAPI Required-Fields Fix

### Problem

Die `required`-Felder aus FormRequests werden nicht korrekt in der OpenAPI-Spec abgebildet. Felder mit der `required`-Rule müssen im `required`-Array des Schemas erscheinen.

### Diagnose

Lies den aktuellen `SchemaBuilder`-Code und prüfe folgende Stellen:

1. **Rule-Parsing:** Wird die `required`-Rule korrekt erkannt?
    - Bei Pipe-Syntax: `'required|string|max:255'`
    - Bei Array-Syntax: `['required', 'string', 'max:255']`
    - Bei komplexen Rules: `['required', Rule::in([...])]`

2. **Required-Array-Aufbau:** Wird das Feld korrekt zum `required`-Array im Schema hinzugefügt?

3. **`sometimes`-Rule:** Felder mit `sometimes` dürfen NICHT in `required` stehen.

4. **`required_if`, `required_with`, `required_unless` etc.:** Diese Felder dürfen NICHT in `required` stehen (conditional required).

5. **`nullable`-Felder die gleichzeitig `required` sind:** `'field' => 'required|nullable|string'` → Feld ist in `required`, hat aber `nullable: true` im Schema.

### Erwartetes Ergebnis

Für diese FormRequest:

```php
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'description'      => 'required|string',
            'short_description'=> 'required|string|max:500',
            'image'            => 'required|url',
            'published_at'     => 'required|date',
        ];
    }
}
```

Muss das generierte OpenAPI-Schema so aussehen:

```json
{
    "PostStoreRequest": {
        "type": "object",
        "required": ["title", "description", "short_description", "image", "published_at"],
        "properties": {
            "title": {
                "type": "string",
                "maxLength": 255
            },
            "description": {
                "type": "string"
            },
            "short_description": {
                "type": "string",
                "maxLength": 500
            },
            "image": {
                "type": "string",
                "format": "uri"
            },
            "published_at": {
                "type": "string",
                "format": "date"
            }
        }
    }
}
```

Für die Update-Request:

```php
class UpdatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image'        => 'sometimes|url',
            'published_at' => 'sometimes|date',
        ];
    }
}
```

Muss das Schema so aussehen:

```json
{
    "PostUpdateRequest": {
        "type": "object",
        "properties": {
            "image": {
                "type": "string",
                "format": "uri"
            },
            "published_at": {
                "type": "string",
                "format": "date"
            }
        }
    }
}
```

**Beachte:** Kein `required`-Array, weil beide Felder `sometimes` haben.

### Rules die als "required" zählen

Nur die folgenden Rules sollen ein Feld ins `required`-Array bringen:
- `required`

### Rules die NICHT als "required" zählen (auch wenn sie "required" im Namen haben)

- `required_if`
- `required_unless`
- `required_with`
- `required_with_all`
- `required_without`
- `required_without_all`
- `sometimes`
- `nullable` (macht ein Feld NICHT nicht-required, aber setzt `nullable: true`)

---

## 5. Tests

### Neue Test-Dateien:

```
tests/
├── Feature/
│   ├── AttributeRouteRegistrarTest.php        (NEU)
│   ├── SeparateFormRequestsTest.php           (NEU)
│   └── AbstractCrudServiceTest.php            (NEU)
├── Unit/
│   └── SchemaBuilderRequiredFieldsTest.php    (NEU)
└── Fixtures/
    ├── Controllers/
    │   ├── AttributePostController.php        (NEU — Controller mit #[ApiResource])
    │   ├── AttributeTagController.php         (NEU — ServiceCrudController mit #[ApiResource])
    │   ├── SeparateRequestPostController.php  (NEU — Controller mit $storeRequestClass + $updateRequestClass)
    │   └── AttributeOnlyShowController.php    (NEU — only: ['show'] via Attribut)
    ├── Requests/
    │   ├── StorePostRequest.php               (NEU)
    │   └── UpdatePostRequest.php              (NEU)
    └── Services/
        └── MinimalTagService.php              (NEU — erbt von AbstractCrudService, implementiert nur list + find)
```

### `Feature/AttributeRouteRegistrarTest.php`

```
1. testAttributeRegistersAllCrudRoutes
   — Controller mit #[ApiResource(path: '/posts')] → alle 5 Routen vorhanden.

2. testAttributeRespectsOnlyParameter
   — #[ApiResource(path: '/posts', only: ['index', 'show'])] → nur GET-Routen.

3. testAttributeRespectsExceptParameter
   — #[ApiResource(path: '/posts', except: ['destroy'])] → kein DELETE.

4. testAttributeAppliesMiddleware
   — #[ApiResource(path: '/posts', middleware: ['auth:sanctum'])] → Middleware auf Routen angewendet.

5. testAttributeSetsRouteName
   — #[ApiResource(path: '/posts', name: 'api.v1.posts')] → Route-Namen sind api.v1.posts.index etc.

6. testAttributeAutoGeneratesRouteName
   — #[ApiResource(path: '/posts')] ohne name → Route-Namen sind posts.index etc.

7. testAttributeWithCustomPrefix
   — #[ApiResource(path: '/api/v2/posts')] → Prefix ist 'api/v2', Resource-Name ist 'posts'.

8. testAttributeWithSimplePath
   — #[ApiResource(path: '/tags')] → nutzt Config-Prefix + '/tags'.

9. testAttributeRoutesAppearInRouteRegistry
   — Registriere via Attribut → RouteRegistry enthält den Eintrag.

10. testAttributeRoutesAppearInOpenApiSpec
    — Registriere via Attribut → Generierte Spec enthält die Pfade.

11. testManualAndAttributeRegistrationWorkTogether
    — PostController via Attribut, TagController via CrudRouteRegistrar → beide funktionieren.

12. testRegisterDirectoryFindsAnnotatedControllers
    — Lege Controller in ein Temp-Verzeichnis, scanne → Routen registriert.

13. testRegisterDirectoryIgnoresNonAnnotatedControllers
    — Controller ohne Attribut im selben Verzeichnis → wird ignoriert.

14. testControllerWithOnlyShowAttributeRegistersOnlyShowRoute
    — #[ApiResource(path: '/items', only: ['show'])] → nur GET /items/{id}.
```

### `Feature/SeparateFormRequestsTest.php`

```
1. testStoreUsesStoreRequestClass
   — Controller mit $storeRequestClass → Store validiert mit StorePostRequest-Rules.

2. testUpdateUsesUpdateRequestClass
   — Controller mit $updateRequestClass → Update validiert mit UpdatePostRequest-Rules.

3. testStoreRejectsFieldsNotInStoreRequest
   — StorePostRequest hat title, description, image, published_at. POST mit nur { "image": "..." } → 422 (title und description required).

4. testUpdateAcceptsPartialData
   — UpdatePostRequest hat 'sometimes' Rules. PUT mit nur { "image": "new.jpg" } → 200, nur image geändert.

5. testUpdateRejectsFieldsNotInUpdateRequest
   — UpdatePostRequest erlaubt nur image und published_at. PUT mit { "title": "Hacked" } → title wird ignoriert (nicht in validated() enthalten), oder 422 wenn title nicht in den Rules steht.

6. testFallbackToFormRequestClassWhenSpecificNotSet
   — Controller mit nur $formRequestClass (keine $storeRequestClass/$updateRequestClass) → Store und Update nutzen dieselbe Klasse.

7. testStoreRequestFallsBackToFormRequestClass
   — Controller mit $formRequestClass und $updateRequestClass, aber ohne $storeRequestClass → Store nutzt $formRequestClass.

8. testUpdateRequestFallsBackToFormRequestClass
   — Controller mit $formRequestClass und $storeRequestClass, aber ohne $updateRequestClass → Update nutzt $formRequestClass.

9. testAllThreeRequestClassesCanBeSetSimultaneously
   — $formRequestClass, $storeRequestClass, $updateRequestClass alle gesetzt → Spezifische haben Vorrang.

10. testOpenApiSpecHasSeparateStoreAndUpdateSchemas
    — Controller mit $storeRequestClass + $updateRequestClass → Spec hat PostStoreRequest und PostUpdateRequest Schemas.

11. testOpenApiSpecHasSingleSchemaWhenBothAreSame
    — Controller mit $storeRequestClass = $updateRequestClass (gleiche Klasse) → Spec hat nur PostRequest Schema.

12. testOpenApiSpecStoreEndpointReferencesStoreSchema
    — POST /posts → requestBody.$ref = PostStoreRequest.

13. testOpenApiSpecUpdateEndpointReferencesUpdateSchema
    — PUT /posts/{id} → requestBody.$ref = PostUpdateRequest.
```

### `Feature/AbstractCrudServiceTest.php`

```
1. testListThrowsNotImplementedByDefault
   — MinimalTagService → rufe index auf einen Controller der list nicht implementiert hat → 501 JSON-Response.

2. testCreateThrowsNotImplementedByDefault
   — POST → 501 JSON-Response mit Message "Method ...::create is not implemented."

3. testUpdateThrowsNotImplementedByDefault
   — PUT → 501.

4. testDeleteThrowsNotImplementedByDefault
   — DELETE → 501.

5. testOverriddenMethodWorks
   — MinimalTagService implementiert list() und find() → index und show funktionieren (200).

6. testNotImplementedResponseFormat
   — 501-Response hat exaktes Format: { error: { message: "...", status: 501 } }.

7. testServiceCanExtendAbstractCrudService
   — MinimalTagService ist instanceof CrudServiceInterface.

8. testServiceCanStillImplementInterfaceDirectly
   — Ein Service der CrudServiceInterface direkt implementiert funktioniert weiterhin.
```

### `Unit/SchemaBuilderRequiredFieldsTest.php`

```
1. testRequiredFieldAppearsInRequiredArray
   — 'title' => 'required|string' → 'title' in required.

2. testMultipleRequiredFieldsAppearInRequiredArray
   — 'title' => 'required|string', 'body' => 'required|string' → beide in required.

3. testOptionalFieldDoesNotAppearInRequiredArray
   — 'body' => 'nullable|string' → 'body' NICHT in required.

4. testSometimesFieldDoesNotAppearInRequiredArray
   — 'image' => 'sometimes|url' → 'image' NICHT in required.

5. testRequiredIfFieldDoesNotAppearInRequiredArray
   — 'subtitle' => 'required_if:type,article' → 'subtitle' NICHT in required.

6. testRequiredWithFieldDoesNotAppearInRequiredArray
   — 'confirm' => 'required_with:password' → 'confirm' NICHT in required.

7. testRequiredWithoutFieldDoesNotAppearInRequiredArray
   — 'email' => 'required_without:phone' → 'email' NICHT in required.

8. testRequiredUnlessFieldDoesNotAppearInRequiredArray
   — 'name' => 'required_unless:role,admin' → 'name' NICHT in required.

9. testRequiredAndNullableFieldIsRequiredButNullable
   — 'bio' => 'required|nullable|string' → 'bio' in required UND nullable: true.

10. testFieldWithNoRequiredRuleIsNotRequired
    — 'tags' => 'array' → 'tags' NICHT in required.

11. testEmptyRulesProduceNoRequiredArray
    — Keine Rules → kein 'required' Key im Schema (oder leeres Array).

12. testStoreRequestSchemaHasCorrectRequiredFields
    — StorePostRequest (alle required) → alle Felder in required.

13. testUpdateRequestSchemaHasNoRequiredFields
    — UpdatePostRequest (alle sometimes) → kein required-Array.
```

---

## 6. Dokumentation aktualisieren

Aktualisiere folgende Doku-Dateien im `docs/`-Verzeichnis:

### `docs/03-model-crud-controller.md`

- Ergänze die neuen Properties `$storeRequestClass` und `$updateRequestClass` mit Erklärung und Beispiel.
- Erkläre die Prioritäts-/Fallback-Logik.

### `docs/04-service-crud-controller.md`

- Ergänze die `AbstractCrudService`-Klasse als empfohlene Basisklasse.
- Zeige ein Beispiel eines Services der nur `list()` und `find()` implementiert.
- Erkläre die 501-Response bei nicht-implementierten Methoden.

### `docs/05-route-registration.md`

- Füge einen neuen Abschnitt "Route Attributes" hinzu.
- Erkläre das `#[ApiResource]`-Attribut mit allen Parametern.
- Zeige Beispiele für: minimale Nutzung, only/except, Middleware, Custom Name.
- Erkläre Auto-Discovery (Config-Option) vs. manuelle Registrierung.
- Vergleich: "Wann nutze ich Attribute vs. CrudRouteRegistrar?"

### `docs/10-openapi-generation.md`

- Ergänze, dass getrennte Store/Update-Schemas generiert werden.
- Zeige ein Beispiel wo PostStoreRequest und PostUpdateRequest unterschiedliche Schemas erzeugen.

### `docs/12-error-handling.md`

- Ergänze den 501-Statuscode für `NotImplementedException`.

### `docs/13-configuration.md`

- Ergänze die neuen Config-Optionen für Auto-Discovery.

### `docs/README.md`

- Inhaltsverzeichnis aktualisieren falls nötig.

---

## 7. Regeln

- **Rückwärtskompatibilität:** Alle bestehenden Tests müssen weiterhin grün sein.
- **`$formRequestClass` bleibt bestehen.** Es ist der Fallback wenn die spezifischen Properties nicht gesetzt sind.
- **`CrudServiceInterface` bleibt unverändert.** Die `AbstractCrudService` ist eine neue, optionale Klasse.
- **`CrudRouteRegistrar` bleibt unverändert.** Das `#[ApiResource]`-Attribut ist eine zusätzliche Option.
- **Alle bestehenden Coding-Regeln gelten weiterhin:** `declare(strict_types=1)`, vollständige Type-Hints, PHP 8.2+, single quotes, keine externen Dependencies.
- **Führe am Ende alle Tests aus** (bestehende + neue) und stelle sicher, dass alles grün ist.

---

## 8. Zusammenfassung: Erwartetes Ergebnis

Nach Abschluss soll folgendes funktionieren:

### Route-Attribute:

```php
#[ApiResource(path: '/posts', only: ['index', 'show', 'store'], middleware: ['auth:sanctum'])]
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
}
```

### Minimaler Service (nur read-only):

```php
class ProductService extends AbstractCrudService
{
    public function list(array $filters, array $sorting, PaginationParams $pagination): PaginatedResult
    {
        // Nur diese Methode implementieren
    }

    public function find(int|string $id): mixed
    {
        // Und diese
    }

    // Alles andere: automatisch 501 Not Implemented
}
```

### Getrennte Requests:

```php
class PostController extends ModelCrudController
{
    protected string $model = Post::class;
    protected ?string $storeRequestClass = StorePostRequest::class;
    protected ?string $updateRequestClass = UpdatePostRequest::class;
}
```

### OpenAPI-Spec mit korrekten Required-Fields und getrennten Schemas:

```json
{
    "components": {
        "schemas": {
            "PostStoreRequest": {
                "type": "object",
                "required": ["title", "description", "short_description", "image", "published_at"],
                "properties": { ... }
            },
            "PostUpdateRequest": {
                "type": "object",
                "properties": { ... }
            }
        }
    }
}
```