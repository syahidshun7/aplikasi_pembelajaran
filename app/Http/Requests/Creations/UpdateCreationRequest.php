<?php

namespace App\Http\Requests\Creations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rule;

class UpdateCreationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'link' => ['nullable', 'url', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'required', Rule::in(['crafting', 'refining', 'finished'])],
            'progress' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'is_public' => ['sometimes', 'boolean'],
            'photos' => ['sometimes', 'array', 'max:8'],
            'photos.*' => [
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(4096),
            ],
            'remove_photo_ids' => ['sometimes', 'array'],
            'remove_photo_ids.*' => ['integer', 'exists:creation_photos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul creation wajib diisi.',
            'title.max' => 'Judul creation maksimal 255 karakter.',
            'description.required' => 'Deskripsi creation wajib diisi.',
            'link.url' => 'Link project harus berupa URL yang valid, misalnya https://contoh.com.',
            'link.max' => 'Link project terlalu panjang.',
            'category.max' => 'Kategori maksimal 120 karakter.',
            'status.required' => 'Status creation wajib dipilih.',
            'status.in' => 'Status creation hanya boleh crafting, refining, atau finished.',
            'progress.required' => 'Progress wajib diisi.',
            'progress.integer' => 'Progress harus berupa angka bulat.',
            'progress.between' => 'Progress harus berada di antara 0 sampai 100.',
            'photos.array' => 'Format upload foto tidak valid.',
            'photos.max' => 'Maksimal 8 foto untuk satu creation.',
            'photos.*.image' => 'Setiap file foto harus berupa gambar.',
            'photos.*.mimes' => 'Foto harus berformat JPG, JPEG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 4MB.',
            'remove_photo_ids.array' => 'Daftar foto yang akan dihapus tidak valid.',
            'remove_photo_ids.*.integer' => 'Data foto yang akan dihapus tidak valid.',
            'remove_photo_ids.*.exists' => 'Foto yang dipilih untuk dihapus tidak ditemukan.',
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
            'remove_photo_ids' => 'foto yang akan dihapus',
            'remove_photo_ids.*' => 'foto yang akan dihapus',
        ];
    }
}
