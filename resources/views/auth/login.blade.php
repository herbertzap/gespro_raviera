@extends('layouts.app', ['class' => 'login-page', 'page' => 'Página de Inicio de Sesión', 'contentClass' => 'login-page'])

@section('content')
<div class="login-container">
    <!-- Carrusel de Imágenes de Fondo -->
    <div class="background-carousel">
        <div class="carousel-slide active" style="background-image: url('https://images.unsplash.com/photo-1553413077-190dd305871c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')">
            <div class="overlay"></div>
        </div>
        <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')">
            <div class="overlay"></div>
        </div>
        <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')">
            <div class="overlay"></div>
        </div>
        <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1586528116493-6b8b5c3b1b1a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')">
            <div class="overlay"></div>
        </div>
    </div>

    <!-- Contenido del Login -->
    <div class="login-content">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-section">
                    <i class="material-icons logo-icon">local_shipping</i>
                    <h1 class="company-name">Comercial Higuera</h1>
                    <p class="company-tagline">Sistema de Gestión Logística</p>
                </div>
            </div>

            <!-- Mensajes de Auto-Logout -->
            @if(session('auto_logout'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="material-icons">warning</i>
                    <strong>{{ session('error') }}</strong>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <form class="login-form" method="post" action="{{ route('login') }}">
                @csrf
                
                <div class="form-group">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text">
                                <i class="material-icons">person</i>
                            </div>
                        </div>
                        <input type="text" name="login" class="form-control{{ $errors->has('login') ? ' is-invalid' : '' }}" 
                               placeholder="Email o RUT" value="{{ old('login') }}" required>
                    </div>
                    @include('alerts.feedback', ['field' => 'login'])
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text">
                                <i class="material-icons">lock</i>
                            </div>
                        </div>
                        <input type="password" name="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" 
                               placeholder="Contraseña" required>
                    </div>
                    @include('alerts.feedback', ['field' => 'password'])
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block btn-login">
                        <i class="material-icons">login</i>
                        Iniciar Sesión
                    </button>
                </div>

                <div class="form-links">
                    <div class="row">
                        <div class="col-6">
                            <a href="{{ route('register') }}" class="link-left">
                                <i class="material-icons">person_add</i>
                                Crear Cuenta
                            </a>
                        </div>
                        <div class="col-6 text-right">
                            <a href="{{ route('password.request') }}" class="link-right">
                                <i class="material-icons">help</i>
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="login-footer">
                <p class="demo-info">
                    <i class="material-icons">info</i>
                    Demo: admin@black.com / secret
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.login-container {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.background-carousel {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.carousel-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0;
    transition: opacity 2s ease-in-out;
}

.carousel-slide.active {
    opacity: 1;
}

.overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.4) 100%);
}

.login-content {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 400px;
    padding: 20px;
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.2);
}

.login-header {
    text-align: center;
    margin-bottom: 30px;
}

.logo-section {
    margin-bottom: 20px;
}

.logo-icon {
    font-size: 48px;
    color: #2196F3;
    margin-bottom: 10px;
}

.company-name {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin: 0;
    margin-bottom: 5px;
}

.company-tagline {
    font-size: 14px;
    color: #666;
    margin: 0;
    font-weight: 300;
}

.login-form {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.input-group {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.input-group-text {
    background: #f8f9fa;
    border: none;
    color: #666;
    padding: 12px 15px;
}

.form-control {
    border: none;
    padding: 15px;
    font-size: 16px;
    background: #fff;
    color: #333;
}

.form-control:focus {
    box-shadow: none;
    background: #fff;
    color: #333;
}

.form-control::placeholder {
    color: #999;
    opacity: 1;
}

.btn-login {
    background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
    border: none;
    border-radius: 10px;
    padding: 15px;
    font-size: 16px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
    transition: all 0.3s ease;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
}

.form-links {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.link-left, .link-right {
    color: #2196F3;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.3s ease;
}

.link-left:hover, .link-right:hover {
    color: #1976D2;
    text-decoration: none;
}

.link-left i, .link-right i {
    font-size: 16px;
    vertical-align: middle;
    margin-right: 5px;
}

.login-footer {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.demo-info {
    font-size: 12px;
    color: #666;
    margin: 0;
}

.demo-info i {
    font-size: 14px;
    vertical-align: middle;
    margin-right: 5px;
}

@media (max-width: 768px) {
    .login-content {
        padding: 10px;
    }
    
    .login-card {
        padding: 30px 20px;
    }
    
    .company-name {
        font-size: 24px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.carousel-slide');
    let currentSlide = 0;
    
    function nextSlide() {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }
    
    // Cambiar imagen cada 5 segundos
    setInterval(nextSlide, 5000);
});
</script>
@endsection
