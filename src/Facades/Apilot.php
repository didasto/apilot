<?php

declare(strict_types=1);

namespace Didasto\Apilot\Facades;

use Illuminate\Support\Facades\Facade;
use Didasto\Apilot\Routing\CrudRouteRegistrar;

class RestApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CrudRouteRegistrar::class;
    }
}
