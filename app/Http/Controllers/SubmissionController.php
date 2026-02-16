<?php

namespace App\Http\Controllers;
use App\Models\Submission;
use App\Models\Quest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    public function store(Request $request, Quest $quest)
    {
        $request->validate([
            'content' => 'required|string',
            'file' => 'nullable', // Max 2MB
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            // Simpan file ke folder 'submissions' di dalam disk 'public'
            $filePath = $request->file('file')->store('submissions', 'public');
        }

        Submission::create([
            'quest_id' => $quest->id,
            'user_id' => auth()->id(),
            'content' => $request->content,
            'file_path' => $filePath,
            'status' => 'Pending',
        ]);

        // Opsional: Ubah status quest menjadi Ongoing saat ada submission masuk
        // $quest->update(['status' => 'Ongoing']);

        return back()->with('message', 'MISSION_REPORT_SENT_WAITING_FOR_REVIEW');
    }
}