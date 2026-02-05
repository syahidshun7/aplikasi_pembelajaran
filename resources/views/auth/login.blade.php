@extends('layouts.main')

@section('content')
<div class="container" style="max-width:400px; margin:60px auto;">

    <h2 style="text-align:center;">Login Pemilik Portofolio</h2>
    <p style="text-align:center; color:#777;">
        Masuk untuk mengelola portofolio
    </p>

    {{-- Pesan error --}}
    @if(session('error'))
        <p style="color:red; text-align:center;">
            {{ session('error') }}
        </p>  
    @endif

    <form action="/login" method="POST">
        @csrf

        <div style="margin-bottom:15px;">
            <label>Email</label>
            <input 
                type="" 
                name="email" 
                value="{{ old('email') }}"
                placeholder="admin"
                style="width:100%; padding:8px;"
                required
            >
        </div>

        <div style="margin-bottom:20px;">
            <label>Password</label>
            <input 
                type="password" 
                name="password"
                placeholder="******"
                style="width:100%; padding:8px;"
                required
            >
        </div>

        <button 
            type="submit"
            style="width:100%; padding:10px; background:#3498db; color:white; border:none; border-radius:4px;"
        >
            Login
        </button>
    </form>

    <p style="text-align:center; margin-top:15px;">
        <a href="/">← Kembali ke Portofolio</a>
    </p>

</div>
@endsection

