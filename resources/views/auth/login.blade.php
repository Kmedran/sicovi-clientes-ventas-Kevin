@extends('layouts.guest')
@section('title', 'Iniciar sesión')
@section('content')
<section class="login-panel" aria-labelledby="login-title">
    <h1 id="login-title" class="login-heading">Bienvenido a SICOVI</h1>
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Revise los datos para continuar.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('login.store') }}" autocomplete="off" data-login-form data-preserve-input="{{ is_string(old('username')) && old('username') !== '' ? 'true' : 'false' }}">
        @csrf
        <div class="mb-3">
            <label for="username" class="visually-hidden">Usuario</label>
            <input id="username" class="form-control login-input @error('username') is-invalid @enderror" type="text" name="username" value="{{ is_string(old('username')) ? old('username') : '' }}" placeholder="Usuario" required minlength="3" maxlength="40" autocomplete="off" autocapitalize="none" spellcheck="false" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
            @error('username')<div id="username-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="visually-hidden">Contraseña</label>
            <input id="password" class="form-control login-input @error('password') is-invalid @enderror" type="password" name="password" placeholder="Contraseña" required autocomplete="off" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password')<div id="password-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary w-100" type="submit">Iniciar sesión</button>
    </form>
</section>
@endsection
