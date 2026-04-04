<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminOnlyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [];
    }
}
