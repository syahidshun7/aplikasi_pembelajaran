<?php

namespace App\Http\Requests\Rubrics;

use App\Models\Rubric;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRubricLevelRequest extends FormRequest
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
            'level' => ['required', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:255'],
            'score_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}

