<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
            'status' => 'required|string|in:draft,published,archived',
        ];
    }
}
