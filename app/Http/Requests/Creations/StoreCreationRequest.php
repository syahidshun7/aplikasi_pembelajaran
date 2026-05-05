<?php

namespace App\Http\Requests\Creations;

use App\Models\CreationCategory;
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
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'link' => ['nullable', 'url', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', Rule::exists(CreationCategory::class, 'id')],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'publication_status' => ['nullable', Rule::in(['draft', 'publish'])],
            'status' => ['nullable', Rule::in(['crafting', 'refining', 'finished'])],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'is_public' => ['sometimes', 'boolean'],
            'is_open_for_collaboration' => ['sometimes', 'boolean'],
            'is_open_for_review' => ['sometimes', 'boolean'],
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
            'description.string' => 'Deskripsi creation harus berupa teks.',
            'content.string' => 'Konten creation harus berupa teks.',
            'link.url' => 'Link project harus berupa URL yang valid, misalnya https://contoh.com.',
            'link.max' => 'Link project terlalu panjang.',
            'category.string' => 'Kategori harus berupa teks.',
            'category.max' => 'Kategori maksimal 120 karakter.',
            'category_id.integer' => 'Kategori creation tidak valid.',
            'category_id.exists' => 'Kategori creation tidak ditemukan.',
            'tags.array' => 'Tags harus berupa daftar.',
            'tags.*.string' => 'Tag harus berupa teks.',
            'tags.*.max' => 'Tag maksimal 40 karakter.',
            'featured_image.max' => 'Featured image terlalu panjang.',
            'publication_status.in' => 'Status publikasi hanya boleh draft atau publish.',
            'status.in' => 'Status creation hanya boleh crafting, refining, atau finished.',
            'progress.integer' => 'Progress harus berupa angka bulat.',
            'progress.between' => 'Progress harus berada di antara 0 sampai 100.',
            'is_open_for_collaboration.boolean' => 'Pengaturan kolaborasi tidak valid.',
            'is_open_for_review.boolean' => 'Pengaturan review mentor tidak valid.',
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
            'content' => 'konten creation',
            'link' => 'link project',
            'category' => 'kategori',
            'category_id' => 'kategori',
            'tags' => 'tags',
            'featured_image' => 'featured image',
            'publication_status' => 'status publikasi',
            'status' => 'status creation',
            'progress' => 'progress',
            'is_open_for_collaboration' => 'pengaturan kolaborasi',
            'is_open_for_review' => 'pengaturan review mentor',
            'photos' => 'foto creation',
            'photos.*' => 'foto creation',
        ];
    }
}
