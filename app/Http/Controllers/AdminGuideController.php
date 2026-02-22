<?php

namespace App\Http\Controllers;// Sesuaikan dengan folder Admin kamu

use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminGuideController extends Controller
{
    /**
     * Menampilkan daftar materi (guides)
     */
    public function index()
    {
        return Inertia::render('Guide/Index', [
            'materi' => Guide::orderBy('created_at', 'desc')->get()
        ]);
    }

    /**
     * Menyimpan materi baru ke database
     */
    public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'file' => 'nullable|file|mimes:pdf,jpg,png,zip|max:10240',
    ]);

    $filePath = null;
    if ($request->hasFile('file')) {
        $filePath = $request->file('file')->store('guides', 'public');
    }

    // UUID akan terisi otomatis ke kolom 'uuid' berkat fungsi uniqueIds() di Model
    Guide::create([
        'title'       => $request->title,
        'description' => $request->description,
        'file_path'   => $filePath, 
    ]);

    return back()->with('message', 'New Scroll has been inscribed in the library!');
}

    /**
     * Memperbarui materi yang sudah ada
     */
    public function update(Request $request, $uuid)
    {
        // Cari guide berdasarkan UUID karena rute manual mengirimkan UUID
        $guide = Guide::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
        ]);

        $data = [
            'title'       => $request->title,
            'description' => $request->description,
        ];

        if ($request->hasFile('file')) {
            // Hapus file lama dari storage jika user mengunggah file baru
            if ($guide->file_path) {
                Storage::disk('public')->delete($guide->file_path);
            }
            $data['file_path'] = $request->file('file')->store('guides', 'public');
        }

        $guide->update($data);

        return back()->with('message', 'The knowledge scroll has been updated.');
    }

    /**
     * Menghapus materi dari library
     */
    public function destroy($uuid)
    {
        $guide = Guide::where('uuid', $uuid)->firstOrFail();

        // Hapus file dari storage fisik sebelum menghapus record di database
        if ($guide->file_path) {
            Storage::disk('public')->delete($guide->file_path);
        }

        $guide->delete();

        return back()->with('message', 'The scroll has been purged from the archive.');
    }
}