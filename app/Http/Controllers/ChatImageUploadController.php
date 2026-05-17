<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatImageUploadController extends Controller
{
    private const MAX_DIMENSION = 1600;
    private const QUALITY = 72;
    private const MAX_FILE_SIZE_KB = 5120;

    public function store(Request $request): JsonResponse
    {
        if (! extension_loaded('gd')) {
            return response()->json(['message' => 'Ekstensi GD belum aktif di server.'], 500);
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:' . self::MAX_FILE_SIZE_KB],
        ]);

        $file = $request->file('image');
        $extension = 'webp';
        $filename = 'chat/' . now()->format('Y/m') . '/' . Str::ulid() . '.' . $extension;

        $image = $this->loadImage($file->getRealPath(), $file->getMimeType());
        if (! $image) {
            return response()->json(['message' => 'Gagal memproses gambar.'], 422);
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth > self::MAX_DIMENSION || $origHeight > self::MAX_DIMENSION) {
            $ratio = min(self::MAX_DIMENSION / $origWidth, self::MAX_DIMENSION / $origHeight);
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = (int) round($origHeight * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($image);
            $image = $resized;
        } else {
            $newWidth = $origWidth;
            $newHeight = $origHeight;
        }

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($image, null, self::QUALITY);
        } else {
            imagejpeg($image, null, self::QUALITY);
            $extension = 'jpg';
            $filename = Str::replaceLast('.webp', '.jpg', $filename);
        }
        $binary = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($filename, $binary);

        $url = Storage::disk('public')->url($filename);
        $size = strlen($binary);

        return response()->json([
            'image_url' => $url,
            'image_width' => $newWidth,
            'image_height' => $newHeight,
            'image_size' => $size,
        ]);
    }

    private function loadImage(string $path, string $mime)
    {
        return match (true) {
            str_contains($mime, 'png') => @imagecreatefrompng($path) ?: null,
            str_contains($mime, 'gif') => @imagecreatefromgif($path) ?: null,
            str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($path) ?: null,
            default => @imagecreatefromjpeg($path) ?: null,
        };
    }
}
