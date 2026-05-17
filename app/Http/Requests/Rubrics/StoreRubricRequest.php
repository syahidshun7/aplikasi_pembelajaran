<?php

namespace App\Http\Requests\Rubrics;

use App\Models\Rubric;
use Illuminate\Foundation\Http\FormRequest;

class StoreRubricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create', Rubric::class));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}

