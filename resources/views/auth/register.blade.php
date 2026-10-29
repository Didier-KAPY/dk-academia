@extends('layouts.app')

@section('title', 'Créer un compte')
@section('body-class', 'auth-page')

@section('content')
    <section class="auth-shell">
        <div class="auth-aside">
            <span class="eyebrow eyebrow-light">UN NOUVEAU DÉPART</span>
            <h1>Votre avenir<br>se construit ici.</h1>
            <p>Créez votre compte pour suivre votre parcours de formation et accéder à vos prochains cours.</p>
            <div class="auth-aside-note"><span class="note-mark">✦</span><span>Une étape à la fois.<br><strong>Un objectif à la fois.</strong></span></div>
        </div>
        <div class="auth-panel">
            <span class="eyebrow">REJOINDRE DK ACADEMIA</span>
            <h2>Créer un compte</h2>
            <p class="form-intro">Quelques informations suffisent pour commencer.</p>

            @if ($errors->any())
                <div class="form-alert" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="form-stack">
                @csrf
                <label for="name">Nom complet</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="120" required autofocus>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror

                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror

                <div class="field-grid">
                    <div class="field-group"><label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
                    <div class="field-group"><label for="password_confirmation">Confirmer</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
                </div>
                @error('password')<span class="field-error">{{ $message }}</span>@enderror

                <label class="check-row"><input name="terms" type="checkbox" value="1" required><span>J’accepte que mes informations soient utilisées pour gérer mon compte et mes formations.</span></label>
                @error('terms')<span class="field-error">{{ $message }}</span>@enderror
                <button class="button button-primary button-full" type="submit">Créer mon compte <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Vous avez déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
        </div>
    </section>
@endsection
