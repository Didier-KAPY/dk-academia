@extends('layouts.app')
@section('title', 'Nos formateurs')
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">TRANSMETTRE LE SAVOIR</span><h1>Nos formateurs</h1><p>Des professionnels qui partagent leur expérience et accompagnent votre progression.</p></div>
        <div class="catalog-grid">@forelse ($trainers as $trainer)<article class="trainer-card">@if ($trainer->photo_path)<img src="{{ asset('storage/'.$trainer->photo_path) }}" alt="Photo de {{ $trainer->display_name ?: $trainer->user->name }}">@else<div class="trainer-avatar" aria-hidden="true">{{ mb_substr($trainer->display_name ?: $trainer->user->name, 0, 1) }}</div>@endif<h2>{{ $trainer->display_name ?: $trainer->user->name }}</h2>@if ($trainer->specialties)<p class="tag-list">@foreach ($trainer->specialties as $specialty)<span class="tag">{{ $specialty }}</span>@endforeach</p>@endif
                @if ($trainer->biography)<p>{{ $trainer->biography }}</p>@endif</article>@empty<div class="empty-state"><h2>Notre équipe pédagogique se présente bientôt.</h2><p>Découvrez nos formations en attendant.</p><a href="{{ route('trainings.index') }}">Voir les formations</a></div>@endforelse</div>{{ $trainers->links() }}
    </section>
@endsection
