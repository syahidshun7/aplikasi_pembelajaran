<?php

namespace App\Http\Requests\Creations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'link' => ['nullable', 'url', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['crafting', 'refining', 'finished'])],
            'progress' => ['required', 'integer', 'between:0,100'],
            'is_public' => ['sometimes', 'boolean'],
            'photos' => ['sometimes', 'array', 'max:8'],
            'photos.*' => ['bail', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/x-png,image/webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul creation wajib diisi.',
            'title.string' => 'Judul creation harus berupa teks.',
            'title.max' => 'Judul creation maksimal 255 karakter.',
            'description.required' => 'Deskripsi creation wajib diisi.',
            'description.string' => 'Deskripsi creation harus berupa teks.',
            'link.url' => 'Link project harus berupa URL yang valid, misalnya https://contoh.com.',
            'link.max' => 'Link project terlalu panjang.',
            'category.string' => 'Kategori harus berupa teks.',
            'category.max' => 'Kategori maksimal 120 karakter.',
            'status.required' => 'Status creation wajib dipilih.',
            'status.in' => 'Status creation hanya boleh crafting, refining, atau finished.',
            'progress.required' => 'Progress wajib diisi.',
            'progress.integer' => 'Progress harus berupa angka bulat.',
            'progress.between' => 'Progress harus berada di antara 0 sampai 100.',
            'photos.array' => 'Format upload foto tidak valid.',
            'photos.max' => 'Maksimal 8 foto untuk satu creation.',
            'photos.*.file' => 'File foto tidak valid.',
            'photos.*.mimes' => 'Foto harus berformat JPG, JPEG, PNG, atau WEBP.',
            'photos.*.mimetypes' => 'Foto harus berformat JPG, JPEG, PNG, atau WEBP, termasuk file screenshot PNG.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 4MB.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Coba pilih ulang file yang ingin diupload.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul creation',
            'description' => 'deskripsi creation',
            'link' => 'link project',
            'category' => 'kategori',
            'status' => 'status creation',
            'progress' => 'progress',
            'photos' => 'foto creation',
            'photos.*' => 'foto creation',
        ];
    }
}
