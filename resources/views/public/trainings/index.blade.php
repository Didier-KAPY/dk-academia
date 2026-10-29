@extends('layouts.app')
@section('title', 'Nos formations')
@section('content')
    <section class="listing-page">
        <div class="page-heading"><span class="eyebrow">APPRENDRE ET PROGRESSER</span><h1>Nos formations</h1><p>Choisissez un parcours adapté à vos objectifs professionnels.</p></div>
        <form class="filter-panel" method="GET" action="{{ route('trainings.index') }}">
            <label>Catégorie<select name="category"><option value="">Toutes</option>@foreach ($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></label>
            <label>Niveau<select name="level"><option value="">Tous</option>@foreach ($levels as $level)<option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>{{ $level }}</option>@endforeach</select></label>
            <label>Modalité<select name="mode"><option value="">Toutes</option>@foreach ($deliveryModes as $mode)<option value="{{ $mode->slug }}" @selected(($filters['mode'] ?? '') === $mode->slug)>{{ $mode->name }}</option>@endforeach</select></label>
            <label>Prix maximum<input type="number" min="0" step="0.01" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Sans limite"></label>
            <button class="button button-primary" type="submit">Filtrer</button>
        </form>
        <div class="catalog-grid">
            @forelse ($trainings as $training)
                <article class="catalog-card">
                    @if ($training->image_path)<img src="{{ asset('storage/'.$training->image_path) }}" alt="{{ $training->title }}">@else<span class="catalog-placeholder">DK</span>@endif
                    <div class="catalog-card-body">
                        @if ($training->category)<span class="tag">{{ $training->category->name }}</span>@endif
                        <h2><a href="{{ route('trainings.show', $training) }}">{{ $training->title }}</a></h2>
                        <p>{{ $training->summary ?: $training->description }}</p>
                        <div class="card-meta">@if ($training->level)<span>{{ $training->level }}</span>@endif @if ($training->duration_hours)<span>{{ $training->duration_hours }} h</span>@endif</div>
                        <p class="price">{{ $training->price !== null ? number_format((float) $training->price, 2, ',', ' ').' '.$training->currency : 'Tarif sur demande' }}</p>
                        <a class="text-link" href="{{ route('trainings.show', $training) }}">Voir le programme →</a>
                    </div>
                </article>
            @empty
                <div class="empty-state"><h2>Aucune formation ne correspond à ces critères.</h2><p>Essayez d’ajuster les filtres ou contactez-nous pour être conseillé.</p><a href="{{ route('contact') }}">Nous contacter</a></div>
            @endforelse
        </div>
        {{ $trainings->links() }}
    </section>
@endsection
