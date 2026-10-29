@extends('layouts.app')
@section('title', 'Services informatiques')
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">SOLUTIONS NUMÉRIQUES</span><h1>Nos services</h1><p>Des prestations adaptées aux besoins des professionnels et des organisations.</p></div>
        <div class="catalog-grid">@forelse ($services as $service)<article class="catalog-card">@if ($service->image_path)<img src="{{ asset('storage/'.$service->image_path) }}" alt="{{ $service->name }}">@else<span class="catalog-placeholder">DK</span>@endif<div class="catalog-card-body"><h2>{{ $service->name }}</h2>@if ($service->summary)<p>{{ $service->summary }}</p>@endif
                @if ($service->description)<p>{{ $service->description }}</p>@endif
                @if ($service->price_from !== null)<p class="price">À partir de {{ number_format((float) $service->price_from, 2, ',', ' ') }} {{ $service->currency }}</p>@endif<a class="text-link" href="{{ route('contact', ['subject' => $service->name]) }}">Demander un devis →</a></div></article>@empty<div class="empty-state"><h2>Nos offres sont en préparation.</h2><p>Contactez notre équipe pour parler de vos besoins numériques.</p><a href="{{ route('contact') }}">Nous contacter</a></div>@endforelse</div>
        <div class="home-cta"><div><h2>Un besoin particulier ?</h2><p>Décrivons ensemble une solution adaptée à votre activité.</p></div><a class="button button-gold" href="{{ route('contact') }}">Demander un devis</a></div>
    </section>
@endsection
