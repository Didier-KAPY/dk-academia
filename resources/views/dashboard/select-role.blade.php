@extends('layouts.app')

@section('title', 'Choisir un espace')
@section('body-class', 'dashboard-page')

@section('content')
    <section class="dashboard-wrap">
        <div class="welcome-block">
            <span class="eyebrow">VOTRE ESPACE DE TRAVAIL</span>
            <h1>Bonjour, {{ auth()->user()->name }}.</h1>
            <p>Plusieurs espaces vous sont attribués. Choisissez celui que vous souhaitez ouvrir.</p>
        </div>
        <div class="role-grid">
            @foreach ($roles as $role)
                <a class="role-card" href="{{ route('dashboard.roles.show', $role->slug) }}">
                    <span class="role-card-icon">✦</span>
                    <span class="role-card-content"><strong>{{ $role->name }}</strong><small>{{ $role->description }}</small></span>
                    <span class="role-card-arrow" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
