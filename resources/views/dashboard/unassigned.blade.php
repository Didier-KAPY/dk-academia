@extends('layouts.app')

@section('title', 'Accès en cours de configuration')
@section('body-class', 'dashboard-page')

@section('content')
    <section class="empty-state">
        <span class="empty-icon">✦</span>
        <span class="eyebrow">ESPACE DK ACADEMIA</span>
        <h1>Votre espace est en préparation.</h1>
        <p>Votre compte est bien connecté, mais aucun rôle ne lui a encore été attribué. Contactez l’administration pour obtenir l’accès adapté à vos fonctions.</p>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-outline" type="submit">Se déconnecter</button></form>
    </section>
@endsection
