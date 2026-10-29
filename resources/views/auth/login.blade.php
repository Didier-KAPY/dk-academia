@extends('layouts.app')

@section('title', 'Connexion')
@section('body-class', 'auth-page')

@section('content')
    <section class="auth-shell">
        <div class="auth-aside">
            <span class="eyebrow eyebrow-light">VOTRE ESPACE, VOTRE RYTHME</span>
            <h1>Ravi de vous<br>retrouver.</h1>
            <p>Reprenez votre parcours et retrouvez vos formations, vos séances et vos ressources au même endroit.</p>
            <div class="auth-aside-note"><span class="note-mark">✦</span><span>Apprendre,<br><strong>maîtriser, réussir.</strong></span></div>
        </div>
        <div class="auth-panel">
            <span class="eyebrow">ESPACE PERSONNEL</span>
            <h2>Connexion</h2>
            <p class="form-intro">Entrez vos identifiants pour accéder à votre espace.</p>

            @if ($errors->any())
                <div class="form-alert" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="form-stack">
                @csrf
                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror

                <div class="label-row"><label for="password">Mot de passe</label></div>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')<span class="field-error">{{ $message }}</span>@enderror

                <label class="check-row"><input name="remember" type="checkbox" value="1"><span>Rester connecté sur cet appareil</span></label>
                <button class="button button-primary button-full" type="submit">Se connecter <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Vous n’avez pas encore de compte ? <a href="{{ route('register') }}">Créer un compte</a></p>
        </div>
    </section>
@endsection
