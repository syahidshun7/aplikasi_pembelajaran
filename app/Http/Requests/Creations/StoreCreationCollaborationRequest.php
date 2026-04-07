<?php

namespace App\Http\Requests\Creations;

use App\Models\CreationCollaborator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreationCollaborationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'requested_role' => ['nullable', 'string', Rule::in(CreationCollaborator::assignableRoles())],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
