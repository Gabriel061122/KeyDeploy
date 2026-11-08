@extends('layouts.app')

@section('title', 'KeyDeploy')

@section('content')
<div class="card">
    <h1>KeyDeploy</h1>
    <p>
        Describe tu aplicación, estructura los requerimientos y crea tus arquitecturas manualmente;
        la contratación queda simulada en esta versión.
    </p>
    @auth
        <div class="actions">
            <a class="btn" href="{{ route('projects.index') }}">Ir a mis proyectos</a>
        </div>
    @endauth
    @guest
        <div class="actions">
            <a class="btn" href="{{ route('login') }}">Iniciar sesión</a>
            <a class="btn btn-secondary" href="{{ route('register') }}">Registrarse</a>
        </div>
    @endguest
</div>
@endsection
