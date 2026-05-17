<?php

namespace App\Http\Requests\Rubrics;

use App\Models\Rubric;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRubricRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Rubric|null $rubric */
        $rubric = $this->route('rubric');
        return $rubric ? (bool) $this->user()?->can('update', $rubric) : false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            // Matrix updates (optional)
            'descriptions' => ['sometimes', 'array'],
            'descriptions.*.criteria_id' => ['required', 'integer', 'exists:rubric_criteria,id'],
            'descriptions.*.level_id' => ['required', 'integer', 'exists:rubric_levels,id'],
            'descriptions.*.description' => ['nullable', 'string'],
        ];
    }
}

