# Prompt: Apilot — Response Wrapper Config Fix

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

## Problem

Die Config-Option `response_wrapper` in `config/apilot.php` wird aktuell **nicht ausgewertet**. Egal ob `null` oder ein String gesetzt ist — Laravel `JsonResource` wrapped die Response immer in `"data"`, weil `JsonResource::wrap()` / `JsonResource::withoutWrapping()` nie aufgerufen wird.

---

## Erwartetes Verhalten

| Config-Wert | Verhalten |
|---|---|
| `'response_wrapper' => 'data'` | Response wrapped in `"data"` (Laravel-Default) |
| `'response_wrapper' => 'result'` | Response wrapped in `"result"` |
| `'response_wrapper' => null` | Kein Wrapping — Items direkt auf Top-Level |

### Beispiel: `response_wrapper => null`, Index-Response

```json
[
    { "id": 1, "title": "Post 1" },
    { "id": 2, "title": "Post 2" }
]
```

Aber: **Pagination-Meta und Links müssen weiterhin verfügbar sein.** Bei `null` sollen `meta` und `links` als Response-Headers oder als Top-Level-Keys neben dem Array übertragen werden. Da ein JSON-Array keine zusätzlichen Keys haben kann, gibt es zwei sinnvolle Optionen:

**Option A (empfohlen): Envelope-Stil auch ohne Wrapper**

Bei paginierten Responses (`index`) wird trotzdem ein Objekt zurückgegeben, aber die Items liegen direkt unter einem konfigurierbaren Key oder sind die einzigen Daten:

```json
{
    "items": [
        { "id": 1, "title": "Post 1" },
        { "id": 2, "title": "Post 2" }
    ],
    "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 7 },
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

Nein — das wäre wieder ein Wrapper unter anderem Namen. Stattdessen:

**Option B (umsetzen): Controller baut die Response manuell**

Für `response_wrapper => null`:

- **Index (paginiert):** Die Response ist ein Objekt, Items liegen **ohne Key-Wrapper** neben `meta` und `links`:

```json
{
    "items": [...],
    "meta": {...},
    "links": {...}
}
```

Nein — das ist genau das gleiche Problem. Der sauberste Ansatz:

**Tatsächliche Umsetzung:**

Die Controller (`ModelCrudController` und `ServiceCrudController`) sollen die Response **nicht** über `JsonResource::collection()` und `JsonResource::make()` bauen, wenn sie den Wrapper kontrollieren müssen. Stattdessen soll der Controller die Response manuell zusammenbauen.

### Konkretes Verhalten:

#### Single-Resource Responses (show, store, update):

| Config | Response |
|---|---|
| `'response_wrapper' => 'data'` | `{ "data": { "id": 1, ... } }` |
| `'response_wrapper' => 'result'` | `{ "result": { "id": 1, ... } }` |
| `'response_wrapper' => null` | `{ "id": 1, ... }` |

#### Collection Responses (index):

| Config | Response |
|---|---|
| `'response_wrapper' => 'data'` | `{ "data": [...], "meta": {...}, "links": {...} }` |
| `'response_wrapper' => 'result'` | `{ "result": [...], "meta": {...}, "links": {...} }` |
| `'response_wrapper' => null` | `{ "items": [...], "meta": {...}, "links": {...} }` |

**Bei `null` wird für paginierte Responses der Key `items` als Fallback genutzt**, weil `meta` und `links` sonst nicht transportiert werden können. Dieses Verhalten soll in der Config dokumentiert sein.

---

## Änderungen

### 1. `ApilotServiceProvider` — Wrapper-Konfiguration anwenden

In der `boot()`-Methode des ServiceProviders:

```php
$wrapper = config('apilot.response_wrapper');

if ($wrapper === null) {
    \Illuminate\Http\Resources\Json\JsonResource::withoutWrapping();
} else {
    \Illuminate\Http\Resources\Json\JsonResource::wrap($wrapper);
}
```

**Achtung:** `JsonResource::withoutWrapping()` und `::wrap()` sind **globale** Einstellungen — sie betreffen ALLE JsonResources in der gesamten Laravel-App. Das muss in der Doku klar kommuniziert werden.

### 2. Controller-Methoden — Manuelle Response-Erstellung bei `null`-Wrapper

Da `JsonResource::withoutWrapping()` bei Collections die `meta` und `links` Keys entfernt (Laravel gibt dann nur das nackte Array zurück), müssen die Controller bei paginierten Responses manuell eingreifen.

Erstelle eine gemeinsame Methode in einem Trait oder einer Basisklasse, die von beiden Controllern genutzt wird:

```php
/**
 * Baut die paginierte Index-Response manuell auf.
 */
protected function buildPaginatedResponse(mixed $paginator, string $resourceClass, Request $request): JsonResponse
{
    $wrapper = config('apilot.response_wrapper');

    // Items durch die Resource transformieren
    $items = $resourceClass::collection($paginator)->resolve($request);

    // Meta und Links aufbauen
    $meta = [
        'current_page' => $paginator->currentPage(),
        'last_page'    => $paginator->lastPage(),
        'per_page'     => $paginator->perPage(),
        'total'        => $paginator->total(),
    ];

    $links = [
        'first' => $paginator->url(1),
        'last'  => $paginator->url($paginator->lastPage()),
        'prev'  => $paginator->previousPageUrl(),
        'next'  => $paginator->nextPageUrl(),
    ];

    // Response zusammenbauen
    if ($wrapper === null) {
        $responseData = [
            'items' => $items,
            'meta'  => $meta,
            'links' => $links,
        ];
    } else {
        $responseData = [
            $wrapper => $items,
            'meta'   => $meta,
            'links'  => $links,
        ];
    }

    return new JsonResponse($responseData, $this->getStatusCode('index'));
}

/**
 * Baut die Single-Item-Response auf.
 */
protected function buildItemResponse(mixed $item, string $resourceClass, string $action, Request $request): JsonResponse
{
    $wrapper = config('apilot.response_wrapper');
    $resolved = (new $resourceClass($item))->resolve($request);

    if ($wrapper === null) {
        $responseData = $resolved;
    } else {
        $responseData = [$wrapper => $resolved];
    }

    return new JsonResponse($responseData, $this->getStatusCode($action));
}
```

### 3. Beide Controller anpassen

Ersetze in `ModelCrudController` und `ServiceCrudController` die bisherige Response-Erstellung durch Aufrufe von `buildPaginatedResponse()` und `buildItemResponse()`.

**Achte darauf, dass die Hooks (`afterIndex`, `transformResponse` etc.) weiterhin an den richtigen Stellen aufgerufen werden.** Die Response-Builder-Methoden sollen NACH den Hooks aufgerufen werden.

### 4. Config-Kommentar aktualisieren

In `config/apilot.php` den Kommentar für `response_wrapper` erweitern:

```php
/*
|--------------------------------------------------------------------------
| Response Wrapper
|--------------------------------------------------------------------------
| Der Key, unter dem Daten im JSON-Response gewrapped werden.
|
| 'data'   → { "data": { ... } }          (Default, Laravel-Standard)
| 'result' → { "result": { ... } }        (Custom Wrapper-Key)
| null     → { "id": 1, ... }             (Kein Wrapping für Single-Items)
|             { "items": [...], "meta": {...}, "links": {...} }
|             (Paginierte Responses nutzen "items" als Key)
|
| HINWEIS: Diese Einstellung betrifft ALLE JsonResources in der App,
| nicht nur die von Apilot generierten.
*/
'response_wrapper' => 'data',
```

---

## Tests

### Neue Datei: `tests/Feature/ResponseWrapperTest.php`

Nutze den bestehenden Post-Fixture-Controller. Registriere Routen im `setUp()`. Ändere die Config per `config()->set()` in jedem Test.

```
1. testDefaultWrapperIsData
   — Config: 'data' (Default).
   — POST store → Response hat Key "data" mit dem Post-Objekt.
   — Prüfe: json_decode hat exakt die Keys ["data"].

2. testDefaultWrapperOnIndex
   — Config: 'data'.
   — GET index → Response hat Keys "data", "meta", "links".
   — "data" ist ein Array von Posts.

3. testCustomWrapperKey
   — Config: 'result'.
   — POST store → Response hat Key "result" mit dem Post-Objekt.
   — Prüfe: json_decode hat exakt die Keys ["result"].

4. testCustomWrapperKeyOnIndex
   — Config: 'result'.
   — GET index → Response hat Keys "result", "meta", "links".
   — "result" ist ein Array von Posts.

5. testNullWrapperRemovesWrappingOnShow
   — Config: null.
   — GET show → Response ist direkt das Post-Objekt (kein Wrapper-Key).
   — Prüfe: Response enthält "id", "title" etc. direkt auf Top-Level.

6. testNullWrapperRemovesWrappingOnStore
   — Config: null.
   — POST store → Response ist direkt das erstellte Post-Objekt.

7. testNullWrapperRemovesWrappingOnUpdate
   — Config: null.
   — PUT update → Response ist direkt das aktualisierte Post-Objekt.

8. testNullWrapperUsesItemsKeyOnIndex
   — Config: null.
   — GET index → Response hat Keys "items", "meta", "links".
   — "items" ist ein Array von Posts.

9. testNullWrapperIndexMetaIsCorrect
   — Config: null.
   — Erstelle 20 Posts, GET index mit per_page=5.
   — meta.total = 20, meta.last_page = 4, meta.per_page = 5, meta.current_page = 1.

10. testNullWrapperIndexLinksAreCorrect
    — Config: null.
    — Erstelle 20 Posts, GET index mit per_page=5, page=2.
    — links.prev ist nicht null, links.next ist nicht null.

11. testDestroyResponseUnaffectedByWrapper
    — Config: null, 'data', 'result' → DELETE destroy ist immer 204 mit leerem Body.

12. testWrapperWorksWithServiceController
    — Config: null.
    — GET index auf ServiceCrudController → Response hat "items", "meta", "links".

13. testWrapperWorksWithServiceControllerShow
    — Config: null.
    — GET show auf ServiceCrudController → Response ist direkt das Item-Objekt.

14. testCustomWrapperWorksWithServiceController
    — Config: 'result'.
    — POST store auf ServiceCrudController → Response hat Key "result".

15. testWrapperDoesNotAffectErrorResponses
    — Config: null.
    — GET show mit ungültiger ID → 404-Response hat weiterhin { "error": { "message": ..., "status": 404 } }.

16. testWrapperDoesNotAffectValidationErrors
    — Config: null.
    — POST store mit ungültigen Daten → 422-Response hat weiterhin { "message": ..., "errors": {...} }.

17. testHooksStillWorkWithNullWrapper
    — Config: null.
    — Nutze HookedPostController, POST store → Hooks werden aufgerufen UND Response hat kein Wrapper-Key.

18. testHooksStillWorkWithCustomWrapper
    — Config: 'result'.
    — Nutze HookedPostController, POST store → Hooks werden aufgerufen UND Response hat Key "result".

19. testTransformResponseHookReceivesUnwrappedData
    — Config: null.
    — transformResponse-Hook erhält die Daten im selben Format unabhängig vom Wrapper.

20. testOpenApiSpecReflectsWrapperConfig
    — Config: 'result'.
    — Generiere Spec → show-Response-Schema hat Property "result" statt "data".

21. testOpenApiSpecReflectsNullWrapperOnShow
    — Config: null.
    — Generiere Spec → show-Response-Schema hat die Properties direkt (kein Wrapper-Key).

22. testOpenApiSpecReflectsNullWrapperOnIndex
    — Config: null.
    — Generiere Spec → index-Response-Schema hat "items", "meta", "links".
```

---

## Wichtig

- **Rückwärtskompatibilität:** Der Default-Wert ist `'data'` — bestehende Tests erwarten dieses Format und müssen weiterhin grün sein.
- **Globaler Effekt:** `JsonResource::withoutWrapping()` betrifft die gesamte App. Das muss in der Doku und Config klar kommuniziert werden. Alternativ: Die Controller bauen die Responses komplett manuell (ohne sich auf Laravels Resource-Wrapping zu verlassen), dann ist der Effekt lokal. **Bevorzuge die manuelle Response-Erstellung**, um keine Seiteneffekte auf andere Teile der App zu haben.
- **Error-Responses:** Fehler-Responses (404, 403, 422, 500) dürfen NICHT vom Wrapper beeinflusst werden. Sie haben ihr eigenes Format.
- **OpenAPI-Spec:** Die Spec muss den konfigurierten Wrapper-Key reflektieren.
- **Alle bestehenden Tests müssen grün bleiben.**
- **Führe am Ende alle Tests aus** (bestehende + neue).