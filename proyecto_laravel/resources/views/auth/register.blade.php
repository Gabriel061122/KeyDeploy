@extends('layouts.app')

@section('title', 'Registro — KeyDeploy')

@section('content')
<div class="card" style="max-width: 28rem;">
    <h1>Registrarse</h1>
    <p class="muted">Al registrarte obtienes una cuenta de <strong>Cliente</strong> para describir tus proyectos (RF14: el registro público solo asigna el rol CLIENTE).</p>
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <label for="name">Nombre</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror

        <label for="email">Correo electrónico</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
        @error('email') <p class="field-error">{{ $message }}</p> @enderror

        <label for="password">Contraseña (8+ caracteres, letras y números)</label>
        <input id="password" type="password" name="password" required>
        @error('password') <p class="field-error">{{ $message }}</p> @enderror

        <label for="password_confirmation">Repite la contraseña</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <div class="actions">
            <button type="submit" class="btn">Crear cuenta</button>
            <a href="{{ route('login') }}" class="btn btn-secondary">Ya tengo cuenta</a>
        </div>
    </form>
</div>
@endsection
