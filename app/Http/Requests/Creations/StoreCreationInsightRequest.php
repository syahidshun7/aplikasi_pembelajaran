<?php

namespace App\Http\Requests\Creations;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreationInsightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:creation_insights,id'],
        ];
    }
}
