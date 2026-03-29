<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Requests;

use Didasto\Apilot\Http\Requests\CrudFormRequest;

class TagRequest extends CrudFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash'],
        ];
    }
}
