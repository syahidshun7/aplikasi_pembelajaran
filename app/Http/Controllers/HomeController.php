<?php

namespace App\Http\Controllers;
use App\Models\Project;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {

        $projects = Project::all();

        // Data yang akan dikirim ke view 
        $data = [
            'nama' => "Syahid Hussein",
            'profesi' => "Web Developer",
            'tentang' => 'Saya seorang developer yang berpengalaman membangun website dan aplikasi web modern.',
            'email' => 'syahid@example.com',
            'projects' => $projects
            
        ];

        // Mengirim data ke Blade view
        return view('home', $data);
    }
}
