# Changelog

## [1.0.1] - 2026-03-29

### Added
- `ModelCrudController` for Eloquent-based CRUD APIs
- `ServiceCrudController` for non-Eloquent CRUD APIs via `CrudServiceInterface`
- `CrudRouteRegistrar` for easy route registration with `only()`, `except()`, `middleware()` support
- `HasCrudHooks` trait with lifecycle hooks: `modifyIndexQuery`, `beforeStore`, `afterStore`, `beforeUpdate`, `afterUpdate`, `beforeDestroy`, `afterDestroy`, `afterIndex`, `afterShow`, `transformResponse`, `getStatusCode`
- Automatic filtering with `EXACT`, `PARTIAL`, and `SCOPE` filter types
- Automatic sorting with multi-field support and directional prefix (`-`)
- Configurable pagination with `per_page` limits
- OpenAPI 3.0.3 specification auto-generation from registered routes
- `/api/doc` route for live spec serving
- `rest-api:generate-spec` Artisan command with `--validate`, `--stdout`, and `--path` options
- `#[OpenApiMeta]` attribute for controller-level documentation overrides
- `#[OpenApiProperty]` attribute for schema property overrides
- `ForceJsonResponse` middleware (alias: `rest-api.json`)
- `SpecValidator` for basic OpenAPI spec validation
- `DefaultResource` fallback for controllers without custom resources
- `ResourceNotFoundException` (renders as 404 JSON)
- `ActionNotAllowedException` (renders as 403 JSON)
- Edge-case hardening: empty filter values ignored, array sort injection ignored, non-numeric pagination parameters fall back to configured defaults
- Validation of `$model`, `$serviceClass`, `$resourceClass`, and `$formRequestClass` properties with descriptive `LogicException` messages
