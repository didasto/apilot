# Installation

Apilot requires PHP 8.2+ and Laravel 11.x or 12.x. There are no additional external dependencies.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2+ |
| Laravel | 11.x or 12.x |

## Composer

```bash
composer require didasto/apilot
```

## Service Provider

Laravel's package auto-discovery registers `ApilotServiceProvider` automatically. If your application has auto-discovery disabled, add the provider manually in `config/app.php`:

```php
'providers' => [
    Didasto\Apilot\ApilotServiceProvider::class,
],
```

## Publish Configuration

Publish the configuration file to `config/apilot.php`:

```bash
php artisan vendor:publish --tag=apilot
```

See [Configuration](13-configuration.md) for a full reference of all available options.

## Optional: Middleware

Apilot ships with a `ForceJsonResponse` middleware that ensures all API requests receive JSON error responses (instead of HTML). It is registered as `apilot.json` but is **not applied automatically**. See [Middleware](11-middleware.md) for setup instructions.

---

**Next:** [Quick Start](02-quick-start.md)
