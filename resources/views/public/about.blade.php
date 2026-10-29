@extends('layouts.app')
@section('title', 'À propos')
@section('content')
    <section class="content-page">
        <span class="eyebrow">QUI NOUS SOMMES</span>
        <h1>DK ACADEMIA, au service des compétences et des projets.</h1>
        <p>DK ACADEMIA accompagne les particuliers et les organisations en République démocratique du Congo grâce à la formation professionnelle et aux solutions informatiques.</p>
        <p>Notre approche associe apprentissage pratique, accompagnement personnalisé et outils adaptés aux réalités du terrain.</p>
        <div class="hero-actions"><a class="button button-primary" href="{{ route('trainings.index') }}">Découvrir les formations</a><a class="button button-outline" href="{{ route('contact') }}">Nous contacter</a></div>
    </section>
@endsection
