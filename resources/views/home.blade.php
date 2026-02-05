


  @extends('layouts.main')
  @section('content')
 
<div class="container">
    <div class="header">
    <h1>Halo, saya {{ $nama }}</h1>
    <p class="profesi">{{ $profesi }}</p>
    <p class="tentang">{{ $tentang }}</p>
          @if(session('success'))
        <p style="color:rgb(0, 255, 34); text-align:center;">
            {{ session('success') }}
        </p>
    @endif
     <a href="/login" class="btn-login">Login</a>
</div>
        
        <h2 class="section-title">Proyek Saya</h2>
        
        <div class="projects-list">
            @foreach($projects as $project)
                <div class="project-card">
                    <h3 class="project-title">{{ $project['title'] }}</h3>
                    <p class="project-description">{{ $project['description'] }}</p>
                </div>
            @endforeach
        </div>
        
        <div class="contact-section">
            <h2 class="section-title">Kontak Saya</h2>
            <a href="mailto:{{$email}}>" class="contact-email">
                {{ $email }}
            </a>
        </div>
    </div>

    
    @endsection
   
    @section('scripts')
    <script>
        // Efek tambahan untuk interaksi
        document.addEventListener('DOMContentLoaded', function() {
            const projectCards = document.querySelectorAll('.project-card');
            
            projectCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.borderLeftColor = '#e74c3c';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.borderLeftColor = '#3498db';
                });
            });
            
            // Animasi sederhana untuk elemen saat halaman dimuat
            const header = document.querySelector('.header');
            header.style.opacity = '0';
            header.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                header.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                header.style.opacity = '1';
                header.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
    @endsection


