@extends('layouts.app')
@section('title', 'Tarifs')
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">TARIFS</span><h1>Des offres transparentes</h1><p>Les tarifs publiés sont indiqués ci-dessous. Contactez-nous pour les formules sur mesure.</p></div>
        <h2>Formations</h2><div class="catalog-grid">@forelse ($trainings as $training)<article class="catalog-card compact-card"><div class="catalog-card-body"><span class="tag">{{ $training->category?->name ?? 'Formation' }}</span><h3><a href="{{ route('trainings.show', $training) }}">{{ $training->title }}</a></h3><p class="price">{{ $training->price !== null ? number_format((float) $training->price, 2, ',', ' ').' '.$training->currency : 'Tarif sur demande' }}</p></div></article>@empty<p>Les tarifs des formations seront affichés ici dès leur publication.</p>@endforelse</div>
        <h2>Services</h2><div class="catalog-grid">@forelse ($services as $service)<article class="catalog-card compact-card"><div class="catalog-card-body"><h3>{{ $service->name }}</h3><p>{{ $service->summary }}</p><p class="price">{{ $service->price_from !== null ? 'À partir de '.number_format((float) $service->price_from, 2, ',', ' ').' '.$service->currency : 'Sur devis' }}</p></div></article>@empty<p>Nos prestations sont proposées sur devis.</p>@endforelse</div><p><a class="button button-primary" href="{{ route('contact') }}">Demander un tarif personnalisé</a></p>
    </section>
@endsection
