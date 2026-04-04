<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
