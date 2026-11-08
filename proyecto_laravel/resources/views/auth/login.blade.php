@extends('layouts.app')

@section('title', 'Acceso — KeyDeploy')

@section('content')
<div class="card" style="max-width: 28rem;">
    <h1>Iniciar sesión</h1>
    <p class="muted">Accede con tu cuenta de KeyDeploy.</p>
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Correo electrónico</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <p class="field-error">{{ $message }}</p> @enderror

        <label for="password">Contraseña</label>
        <input id="password" type="password" name="password" required>
        @error('password') <p class="field-error">{{ $message }}</p> @enderror

        <div class="actions">
            <button type="submit" class="btn">Iniciar sesión</button>
            <a href="{{ route('register') }}" class="btn btn-secondary">Crear una cuenta</a>
        </div>
    </form>
</div>
@endsection
