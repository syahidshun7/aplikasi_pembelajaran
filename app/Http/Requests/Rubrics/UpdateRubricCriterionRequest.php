<?php

namespace App\Http\Requests\Rubrics;

use App\Models\Rubric;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRubricCriterionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:0'],
            'order' => ['required', 'integer', 'min:0'],
        ];
    }
}

