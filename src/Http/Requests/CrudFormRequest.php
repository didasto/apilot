<?php

declare(strict_types=1);

namespace Didasto\Apilot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class CrudFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    abstract public function rules(): array;
}
