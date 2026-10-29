@extends('layouts.app')
@section('title', 'Gestion pédagogique')
@section('body-class', 'dashboard-page')
@section('content')
    <section class="listing-page">
        <div class="page-heading"><span class="eyebrow">ESPACE DE TRAVAIL</span><h1>Gestion pédagogique</h1><p>Suivez les formations, les sessions, les inscriptions et les présences.</p></div>
        <div class="dashboard-metrics">
            <article class="metric-card"><span>Formations actives</span><strong>{{ $activeTrainings }}</strong><small>Publiées au catalogue</small></article>
            @if ($canReviewEnrollments)<article class="metric-card"><span>Inscriptions à traiter</span><strong>{{ $pendingEnrollments }}</strong><small>Demandes en attente</small></article>@endif
            <article class="metric-card"><span>Sessions affichées</span><strong>{{ $sessions->total() }}</strong><small>Programmées</small></article>
        </div>
        <div class="dashboard-shortcuts">
            @if ($canManageTrainings)<a class="button button-primary" href="{{ route('management.trainings.index') }}">Gérer les formations</a>@endif
            <a class="button button-outline" href="{{ route('management.pedagogy.sessions.index') }}">Gérer les sessions</a>
            @if ($canReviewEnrollments)<a class="button button-outline" href="{{ route('management.pedagogy.enrollments.index') }}">Traiter les inscriptions</a>@endif
            <a class="button button-outline" href="{{ route('management.pedagogy.evaluations.index') }}">Gérer les évaluations</a>
        </div>
        <section class="dashboard-table-section"><div class="dashboard-section-heading"><div><span class="eyebrow">PLANNING</span><h2>Sessions programmées</h2></div></div>
            <div class="dashboard-list">@forelse ($sessions as $session)<article class="dashboard-list-row"><div><strong>{{ $session->training->title }} — {{ $session->name }}</strong><span>{{ $session->starts_at?->format('d/m/Y à H:i') ?? 'Date à confirmer' }} · {{ $session->instructor?->name ?? 'Formateur à attribuer' }} · {{ $session->deliveryMode?->name ?? 'Modalité à confirmer' }}</span></div><a class="text-link" href="{{ route('management.pedagogy.attendance.show', $session) }}">Présences →</a></article>@empty<div class="empty-state"><h2>Aucune session programmée.</h2><p>Les prochaines sessions s’afficheront ici.</p></div>@endforelse</div>{{ $sessions->links() }}
        </section>
    </section>
@endsection
