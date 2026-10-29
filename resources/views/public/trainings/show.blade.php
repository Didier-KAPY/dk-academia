@extends('layouts.app')
@section('title', $training->title)
@section('content')
    <article class="detail-page">
        <a class="text-link" href="{{ route('trainings.index') }}">← Toutes les formations</a>
        <div class="detail-heading"><div>@if ($training->category)<span class="tag">{{ $training->category->name }}</span>@endif<h1>{{ $training->title }}</h1><p>{{ $training->summary }}</p></div><div class="detail-price">{{ $training->price !== null ? number_format((float) $training->price, 2, ',', ' ').' '.$training->currency : 'Tarif sur demande' }}</div></div>
        @if ($training->image_path)<img class="detail-image" src="{{ asset('storage/'.$training->image_path) }}" alt="{{ $training->title }}">@endif
        <div class="detail-columns"><div class="prose">
            <h2>À propos</h2><p>{{ $training->description ?: $training->summary }}</p>
            @if ($training->objectives)<h2>Objectifs pédagogiques</h2><ul>@foreach ($training->objectives as $objective)<li>{{ $objective }}</li>@endforeach</ul>@endif
            @if ($training->target_audience)<h2>Public concerné</h2><p>{{ $training->target_audience }}</p>@endif
            @if ($training->prerequisites)<h2>Prérequis</h2><p>{{ $training->prerequisites }}</p>@endif
            @if ($training->modules->isNotEmpty())<h2>Programme</h2>@foreach ($training->modules as $module)<section class="program-module"><h3>{{ $module->title }}</h3><p>{{ $module->description }}</p><ul>@foreach ($module->lessons as $lesson)<li>{{ $lesson->title }}</li>@endforeach</ul></section>@endforeach
            @elseif ($training->syllabus)<h2>Programme</h2><ul>@foreach ($training->syllabus as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
        </div><aside class="detail-aside"><h2>Modalités</h2><ul>@forelse ($training->deliveryModes as $mode)<li>{{ $mode->name }}</li>@empty<li>Informations à venir</li>@endforelse</ul>@if ($training->duration_hours)<p><strong>Durée :</strong> {{ $training->duration_hours }} heures</p>@endif
            @if ($training->deliveryModes->isNotEmpty())
                <a class="button button-primary button-full" href="{{ route('enrollments.create', $training) }}">S’inscrire en ligne</a>
            @else
                <p>Les inscriptions pour cette formation ne sont pas ouvertes actuellement.</p>
            @endif
            <a class="text-link" href="{{ route('contact') }}">Poser une question</a></aside></div>
        @if ($sessions->isNotEmpty())<section class="session-section"><h2>Sessions programmées</h2><div class="catalog-grid">@foreach ($sessions as $session)<div class="session-card"><h3>{{ $session->name }}</h3><p>{{ $session->starts_at?->format('d/m/Y à H:i') ?? 'Date à confirmer' }}</p><p>{{ $session->deliveryMode?->name ?? 'Modalité à confirmer' }}@if ($session->instructor) · {{ $session->instructor->name }}@endif</p></div>@endforeach</div></section>@endif
    </article>
@endsection
