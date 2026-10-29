@extends('layouts.app')
@section('title', $lesson->title)
@section('content')
    <article class="detail-page prose"><a class="text-link" href="{{ route('learning.index') }}">← Retour à mes cours</a><span class="tag">{{ $lesson->module->training->title }}</span><h1>{{ $lesson->title }}</h1>@if ($lesson->duration_minutes)<p class="muted">Durée indicative : {{ $lesson->duration_minutes }} minutes</p>@endif
        <div class="lesson-content">{{ $lesson->content ?: 'Le contenu de cette leçon sera bientôt disponible.' }}</div>
        @if ($progression?->completed_at)<p class="progress-complete">Leçon terminée le {{ $progression->completed_at->format('d/m/Y à H:i') }}.</p>@elseif (in_array($enrollment->status, ['confirmed', 'in_progress'], true))<form method="POST" action="{{ route('learning.lessons.complete', $lesson) }}" class="lesson-complete-form">@csrf<label>Temps consacré (secondes, facultatif)<input type="number" name="seconds_watched" min="0" max="86400"></label><button class="button button-primary" type="submit">Marquer comme terminée</button></form>@endif
    </article>
@endsection
