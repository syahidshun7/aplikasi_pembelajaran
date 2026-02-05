@extends('layouts.main')

@section('content')
<div class="container">

    <h1>Dashboard Portofolio</h1>
    <p>Kelola konten portofolio Anda</p>

    {{-- Alert --}}
    <x-alert type="success" :message="session('success')" />
    <x-alert type="error" :message="session('error')" />

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-top:30px;">

        <!-- Card Profil -->
        <div style="border:1px solid #ddd; padding:20px; border-radius:8px;">
            <h3>Profil Saya</h3>
            <p>Nama, profesi, dan deskripsi diri</p>
            <a href="/dashboard/profile" class="btn">Edit Profil</a>
        </div>

        <!-- Card Proyek -->
        <div style="border:1px solid #ddd; padding:20px; border-radius:8px;">
            <h3>Proyek</h3>
            <p>Tambah dan kelola proyek</p>
            <a href="/dashboard/projects" class="btn">Kelola Proyek</a>
        </div>

        <!-- Card Logout -->
        <div style="border:1px solid #ddd; padding:20px; border-radius:8px;">
            <h3>Logout</h3>
            <p>Keluar dari dashboard</p>
            <a href="/logout" class="btn-danger">Logout</a>
        </div>

    </div>

</div>
@endsection
