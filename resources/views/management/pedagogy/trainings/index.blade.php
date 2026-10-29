@extends('layouts.app')
@section('title', 'Gestion des formations')
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">CATALOGUE INTERNE</span><h1>Formations</h1><p>Créez les parcours qui seront proposés sur le site public.</p><a class="button button-primary" href="{{ route('management.trainings.create') }}">Créer une formation</a></div>
        <div class="dashboard-list">@forelse ($trainings as $training)<article class="dashboard-list-row"><div><strong>{{ $training->title }}</strong><span>{{ $training->category?->name ?? 'Sans catégorie' }} · {{ $training->modules_count }} modules · {{ $training->sessions_count }} sessions · {{ $training->enrollments_count }} inscriptions</span></div><div class="row-actions"><span class="status-pill">{{ $training->is_published ? 'Publiée' : 'Brouillon' }}</span><a class="text-link" href="{{ route('management.trainings.show', $training) }}">Gérer →</a><a class="text-link" href="{{ route('management.trainings.edit', $training) }}">Modifier</a></div></article>@empty<div class="empty-state"><h2>Aucune formation enregistrée.</h2><p>Créez un parcours pour alimenter le catalogue public.</p></div>@endforelse</div>{{ $trainings->links() }}
    </section>
@endsection
