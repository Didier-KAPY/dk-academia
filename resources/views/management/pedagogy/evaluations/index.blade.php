@extends('layouts.app')
@section('title', 'Évaluations pédagogiques')
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">RÉSULTATS PÉDAGOGIQUES</span><h1>Évaluations</h1><p>Préparez des questionnaires et suivez leur remise par les apprenants.</p><a class="button button-primary" href="{{ route('management.pedagogy.evaluations.create') }}">Créer une évaluation</a></div>
        <div class="dashboard-list">@forelse ($evaluations as $evaluation)<article class="dashboard-list-row"><div><strong>{{ $evaluation->title }}</strong><span>{{ $evaluation->training?->title ?? 'Formation' }}@if ($evaluation->session) · {{ $evaluation->session->name }}@endif · {{ $evaluation->attempts_count }} copie(s)</span></div><div class="row-actions"><span class="status-pill">{{ $evaluation->is_published ? 'Publiée' : 'Brouillon' }}</span><span class="muted">{{ $evaluation->total_points }} points</span><a class="text-link" href="{{ route('management.pedagogy.evaluations.show', $evaluation) }}">Voir les copies →</a></div></article>@empty<div class="empty-state"><h2>Aucune évaluation créée.</h2><p>Créez un questionnaire pour mesurer les acquis des apprenants.</p></div>@endforelse</div>{{ $evaluations->links() }}
    </section>
@endsection
