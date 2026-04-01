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

Die Config-Option `response_wrapper` in `config/apilot.php` wird aktuell nicht korrekt ausgewertet. Der Wrapper wird immer als `"data"` gesetzt, unabhängig von der Config.

---

## Neues Verhalten: 3 Modi

Die Config-Option `response_wrapper` unterstützt drei Werte mit klar getrenntem Verhalten:

### Modus 1: `null` — Laravel Default

```php
'response_wrapper' => null,
```

Apilot greift **nicht** in die Response-Formatierung ein. Laravel's `JsonResource` bestimmt das Format. Das bedeutet: Laravel's Default-Verhalten (`"data"`-Wrapper via `JsonResource`) bleibt aktiv, es sei denn, der Nutzer hat selbst `JsonResource::withoutWrapping()` in seiner App aufgerufen.

**Apilot macht hier gar nichts** — keine `JsonResource::wrap()`-Aufrufe, keine manuelle Response-Erstellung. Die Standard-Laravel-Resource-Methoden (`toResponse()`, `::collection()`) werden unverändert genutzt.

**Single-Item (show, store, update):**
```json
{
    "data": {
        "id": 1,
        "title": "Post 1"
    }
}
```

**Collection (index):**
```json
{
    "data": [
        { "id": 1, "title": "Post 1" },
        { "id": 2, "title": "Post 2" }
    ],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 7
    }
}
```

*(Das ist exakt Laravels Standard-Output für paginierte Resources.)*

---

### Modus 2: `[]` — Kein Wrapper

```php
'response_wrapper' => [],
```

Apilot entfernt den Wrapper komplett. Single-Item-Responses geben das Objekt direkt zurück. Paginierte Responses nutzen `"items"` als Key für die Collection, weil `meta` und `links` sonst nicht transportiert werden können.

**Single-Item (show, store, update):**
```json
{
    "id": 1,
    "title": "Post 1",
    "content": "...",
    "created_at": "2026-03-31T22:18:02.000000Z"
}
```

**Collection (index):**
```json
{
    "items": [
        { "id": 1, "title": "Post 1" },
        { "id": 2, "title": "Post 2" }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 7
    },
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    }
}
```

---

### Modus 3: `'string'` — Named Wrapper

```php
'response_wrapper' => 'data',     // oder 'result', 'payload', etc.
```

Apilot wrapped alle Responses unter dem angegebenen Key. Der Nutzer kontrolliert den Key-Namen.

**Single-Item mit `'data'` (show, store, update):**
```json
{
    "data": {
        "id": 1,
        "title": "Post 1"
    }
}
```

**Collection mit `'data'` (index):**
```json
{
    "data": [
        { "id": 1, "title": "Post 1" },
        { "id": 2, "title": "Post 2" }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 7
    },
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    }
}
```

**Single-Item mit `'result'`:**
```json
{
    "result": {
        "id": 1,
        "title": "Post 1"
    }
}
```

**Collection mit `'result'`:**
```json
{
    "result": [
        { "id": 1, "title": "Post 1" },
        { "id": 2, "title": "Post 2" }
    ],
    "meta": { ... },
    "links": { ... }
}
```

---

### Destroy-Response (alle Modi)

Die `destroy`-Response ist bei **allen drei Modi** identisch: `204 No Content` mit leerem Body. Der Wrapper hat keine Auswirkung auf destroy.

### Error-Responses (alle Modi)

Fehler-Responses (404, 403, 422, 501) haben bei **allen drei Modi** ihr eigenes festes Format und werden **nicht** vom Wrapper beeinflusst:

```json
// 404
{ "error": { "message": "Resource not found.", "status": 404 } }

// 422
{ "message": "The title field is required.", "errors": { "title": ["The title field is required."] } }
```

---

## Umsetzung

### 1. Erkennung des Modus

Erstelle eine Hilfsmethode (in einem Trait oder einer Hilfsklasse), die den Wrapper-Modus ermittelt:

```php
protected function resolveWrapperMode(): string
{
    $wrapper = config('apilot.response_wrapper');

    if ($wrapper === null) {
        return 'laravel';   // Modus 1: Laravel Default
    }

    if (is_array($wrapper) && empty($wrapper)) {
        return 'none';      // Modus 2: Kein Wrapper
    }

    if (is_string($wrapper) && $wrapper !== '') {
        return 'named';     // Modus 3: Named Wrapper
    }

    // Fallback für ungültige Werte
    return 'laravel';
}

protected function resolveWrapperKey(): ?string
{
    $wrapper = config('apilot.response_wrapper');

    if (is_string($wrapper) && $wrapper !== '') {
        return $wrapper;
    }

    return null;
}
```

### 2. Response-Builder-Methoden

Erstelle zwei zentrale Methoden, die von **beiden** Controllern (`ModelCrudController` und `ServiceCrudController`) genutzt werden. Extrahiere sie in einen gemeinsamen Trait oder eine Basisklasse.

**Wichtig:** Diese Methoden dürfen **kein** `JsonResource::withoutWrapping()` oder `JsonResource::wrap()` aufrufen — das würde global die gesamte App beeinflussen. Die Response wird stattdessen **manuell** zusammengebaut.

```php
/**
 * Baut die Response für ein einzelnes Item (show, store, update).
 */
protected function buildItemResponse(mixed $item, string $resourceClass, string $action, Request $request): JsonResponse
{
    $mode = $this->resolveWrapperMode();
    $resolved = (new $resourceClass($item))->resolve($request);

    $responseData = match ($mode) {
        'none'    => $resolved,                                     // Direkt das Objekt
        'named'   => [$this->resolveWrapperKey() => $resolved],     // Gewrapped
        'laravel' => null,                                          // Marker: Laravel Default nutzen
        default   => null,
    };

    // Bei 'laravel'-Modus: Lass Laravel die Response bauen
    if ($responseData === null) {
        return (new $resourceClass($item))
            ->response()
            ->setStatusCode($this->getStatusCode($action));
    }

    return new JsonResponse($responseData, $this->getStatusCode($action));
}

/**
 * Baut die paginierte Response für index.
 */
protected function buildPaginatedResponse(mixed $paginator, string $resourceClass, string $action, Request $request): JsonResponse
{
    $mode = $this->resolveWrapperMode();

    // Bei 'laravel'-Modus: Lass Laravel die Response bauen (Standard-Pagination-Format)
    if ($mode === 'laravel') {
        return $resourceClass::collection($paginator)
            ->response()
            ->setStatusCode($this->getStatusCode($action));
    }

    // Für 'none' und 'named': Manuell zusammenbauen
    $items = $resourceClass::collection($paginator)->resolve($request);

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

    $itemsKey = match ($mode) {
        'none'  => 'items',
        'named' => $this->resolveWrapperKey(),
        default => 'data',
    };

    $responseData = [
        $itemsKey => $items,
        'meta'    => $meta,
        'links'   => $links,
    ];

    return new JsonResponse($responseData, $this->getStatusCode($action));
}
```

### 3. Controller-Methoden anpassen

Ersetze in **beiden Controllern** (`ModelCrudController` und `ServiceCrudController`) die bisherige Response-Erstellung in den Methoden `index`, `show`, `store` und `update` durch Aufrufe der neuen Builder-Methoden.

**Achte darauf, dass die Hooks weiterhin an den richtigen Stellen aufgerufen werden.** Die Builder-Methoden sollen NACH den Hooks (`afterIndex`, `afterStore`, `afterUpdate`, `transformResponse`) aufgerufen werden.

### 4. ServiceCrudController — Index-Response

Der `ServiceCrudController` baut die Index-Response aus `PaginatedResult`. Passe die Logik analog an. Im `laravel`-Modus nutze `'data'` als Key (Laravels Standard simulieren), da der ServiceCrudController keinen Eloquent-Paginator hat und die Response immer manuell baut.

### 5. Config-Kommentar aktualisieren

In `config/apilot.php`:

```php
/*
|--------------------------------------------------------------------------
| Response Wrapper
|--------------------------------------------------------------------------
|
| Steuert, wie Apilot die JSON-Responses formatiert.
| Drei Modi sind verfügbar:
|
| null     → Laravel Default. Apilot greift nicht ein.
|            Laravel's JsonResource bestimmt das Format (Standard: "data"-Wrapper).
|            Nutze diesen Modus wenn du Laravels Standard-Verhalten behalten
|            oder selbst JsonResource::wrap()/withoutWrapping() steuern willst.
|
| []       → Kein Wrapper. Single-Items werden direkt als JSON-Objekt zurückgegeben.
|            Paginierte Responses nutzen "items" als Key für die Collection.
|            Beispiel show:  { "id": 1, "title": "..." }
|            Beispiel index: { "items": [...], "meta": {...}, "links": {...} }
|
| 'string' → Named Wrapper. Der angegebene String wird als Wrapper-Key genutzt.
|            Beispiel 'data':   { "data": { "id": 1, ... } }
|            Beispiel 'result': { "result": { "id": 1, ... } }
|
| Error-Responses (404, 403, 422) werden nicht vom Wrapper beeinflusst.
|
*/
'response_wrapper' => null,
```

---

## Tests

### Neue Datei: `tests/Feature/ResponseWrapperTest.php`

Nutze den bestehenden Post-Fixture-Controller und die bestehende Post-Migration. Registriere Routen im `setUp()`. Ändere die Config per `config()->set('apilot.response_wrapper', ...)` in jedem Test.

#### Modus 1: `null` — Laravel Default

```
1. testNullWrapperShowReturnsLaravelDefault
   — config: null
   — GET show → Response hat Key "data" mit dem Post-Objekt.

2. testNullWrapperIndexReturnsLaravelDefault
   — config: null
   — Erstelle 5 Posts, GET index
   — Response hat Keys "data" (Array), "links", "meta".

3. testNullWrapperStoreReturnsLaravelDefault
   — config: null
   — POST store → Response hat Key "data".

4. testNullWrapperUpdateReturnsLaravelDefault
   — config: null
   — PUT update → Response hat Key "data".
```

#### Modus 2: `[]` — Kein Wrapper

```
5. testEmptyArrayWrapperShowReturnsUnwrapped
   — config: []
   — GET show → Top-Level-Keys sind "id", "title", etc. Kein "data"-Key.

6. testEmptyArrayWrapperStoreReturnsUnwrapped
   — config: []
   — POST store → Response ist direkt das erstellte Post-Objekt.

7. testEmptyArrayWrapperUpdateReturnsUnwrapped
   — config: []
   — PUT update → Response ist direkt das aktualisierte Post-Objekt.

8. testEmptyArrayWrapperIndexUsesItemsKey
   — config: []
   — Erstelle 5 Posts, GET index.
   — Response hat Keys "items", "meta", "links". Kein "data"-Key.

9. testEmptyArrayWrapperIndexItemsContainsPosts
   — config: []
   — Erstelle 3 Posts, GET index.
   — "items" enthält 3 Objekte mit "id", "title", etc.

10. testEmptyArrayWrapperIndexMetaIsCorrect
    — config: []
    — Erstelle 20 Posts, GET index mit per_page=5.
    — meta.total = 20, meta.last_page = 4, meta.per_page = 5, meta.current_page = 1.

11. testEmptyArrayWrapperIndexLinksAreCorrect
    — config: []
    — Erstelle 20 Posts, GET index mit per_page=5, page=2.
    — links.prev nicht null, links.next nicht null.

12. testEmptyArrayWrapperIndexPage1HasNoPrev
    — config: []
    — GET index page=1 → links.prev ist null.

13. testEmptyArrayWrapperIndexLastPageHasNoNext
    — config: []
    — Erstelle 10 Posts, per_page=5, page=2 → links.next ist null.
```

#### Modus 3: `'string'` — Named Wrapper

```
14. testStringWrapperDataShowWrapsInData
    — config: 'data'
    — GET show → Response hat Key "data".

15. testStringWrapperDataIndexWrapsInData
    — config: 'data'
    — GET index → Response hat Keys "data", "meta", "links".

16. testStringWrapperResultShowWrapsInResult
    — config: 'result'
    — GET show → Response hat Key "result". Kein "data"-Key.

17. testStringWrapperResultIndexWrapsInResult
    — config: 'result'
    — GET index → Response hat Keys "result", "meta", "links". Kein "data"-Key.

18. testStringWrapperResultStoreWrapsInResult
    — config: 'result'
    — POST store → Response hat Key "result".

19. testStringWrapperResultUpdateWrapsInResult
    — config: 'result'
    — PUT update → Response hat Key "result".

20. testStringWrapperPayloadShowWrapsInPayload
    — config: 'payload'
    — GET show → Response hat Key "payload".
```

#### Destroy (alle Modi)

```
21. testDestroyUnaffectedByNullWrapper
    — config: null → DELETE → 204, leerer Body.

22. testDestroyUnaffectedByEmptyArrayWrapper
    — config: [] → DELETE → 204, leerer Body.

23. testDestroyUnaffectedByStringWrapper
    — config: 'result' → DELETE → 204, leerer Body.
```

#### Error-Responses (alle Modi)

```
24. test404UnaffectedByEmptyArrayWrapper
    — config: [] → GET show/999 → { "error": { "message": ..., "status": 404 } }.

25. test404UnaffectedByStringWrapper
    — config: 'result' → GET show/999 → { "error": { ... } }, NICHT { "result": { "error": ... } }.

26. test422UnaffectedByEmptyArrayWrapper
    — config: [] → POST store ungültig → { "message": ..., "errors": { ... } }.

27. test422UnaffectedByStringWrapper
    — config: 'result' → POST store ungültig → { "message": ..., "errors": { ... } }.
```

#### Hooks

```
28. testHooksWorkWithEmptyArrayWrapper
    — config: [] → HookedPostController, POST store → Hooks aufgerufen UND Response unwrapped.

29. testHooksWorkWithNamedWrapper
    — config: 'result' → HookedPostController, POST store → Hooks aufgerufen UND Response in "result".

30. testTransformResponseHookCalledForAllModes
    — Für jeden Modus: transformResponse-Hook wird aufgerufen.
```

#### ServiceCrudController

```
31. testServiceControllerEmptyArrayWrapperShow
    — config: [] → GET show → direkt das Item-Objekt.

32. testServiceControllerEmptyArrayWrapperIndex
    — config: [] → GET index → "items", "meta", "links".

33. testServiceControllerNamedWrapperShow
    — config: 'result' → GET show → { "result": { ... } }.

34. testServiceControllerNamedWrapperIndex
    — config: 'result' → GET index → { "result": [...], "meta": {...}, "links": {...} }.

35. testServiceControllerNullWrapperIndex
    — config: null → GET index → "data", "meta", "links".
```

#### OpenAPI Spec

```
36. testOpenApiSpecReflectsNamedWrapper
    — config: 'result' → Spec: show-Schema hat "result", index hat "result".

37. testOpenApiSpecReflectsEmptyArrayWrapper
    — config: [] → Spec: show-Schema hat Properties direkt, index hat "items".

38. testOpenApiSpecReflectsNullWrapper
    — config: null → Spec: show-Schema hat "data", index hat "data".
```

#### Edge Cases

```
39. testEmptyStringFallsBackToLaravelDefault
    — config: '' → verhält sich wie null.

40. testNumericValueFallsBackToLaravelDefault
    — config: 123 → verhält sich wie null.

41. testNonEmptyArrayFallsBackToLaravelDefault
    — config: ['data'] → verhält sich wie null. Nur exakt [] aktiviert den No-Wrapper-Modus.
```

---

## Dokumentation aktualisieren

Aktualisiere `docs/13-configuration.md` mit der vollständigen Beschreibung aller drei Modi, Beispiel-Responses und Hinweisen.

---

## Regeln

- **Rückwärtskompatibilität:** Default ist `null` (Laravel Default). Bestehende Tests müssen grün bleiben.
- **Kein globaler Seiteneffekt:** Kein `JsonResource::withoutWrapping()` oder `JsonResource::wrap()` aufrufen.
- **Error-Responses immun.** Destroy immun.
- **Beide Controller-Typen** müssen alle drei Modi unterstützen.
- **OpenAPI-Spec** muss den Modus reflektieren.
- **Alle Coding-Regeln gelten.** Alle Tests am Ende ausführen.