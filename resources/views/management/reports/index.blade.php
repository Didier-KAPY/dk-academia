@extends('layouts.app')

@section('title', 'Rapports')

@section('content')
    <section class="dashboard-wrap">
        <div class="dashboard-heading">
            <div class="welcome-block">
                <span class="eyebrow">ADMINISTRATION</span>
                <h1>Rapports</h1>
                <p>Consultez les opérations validées et les inscriptions sur une période donnée.</p>
            </div>
        </div>

        <form class="dashboard-panel" method="GET" action="{{ route('management.reports.index') }}">
            <div class="form-grid">
                <label>Date de début<input type="date" name="from" value="{{ $from }}" required></label>
                <label>Date de fin<input type="date" name="to" value="{{ $to }}" required></label>
            </div>
            @error('to')<p class="form-error">{{ $message }}</p>@enderror
            <div class="form-actions">
                <button class="button button-primary" type="submit">Afficher</button>
                <a class="button button-outline" href="{{ route('management.reports.export', ['from' => $from, 'to' => $to]) }}">Exporter en CSV</a>
            </div>
        </form>

        <section class="dashboard-table-section">
            <div class="dashboard-section-heading"><div><span class="eyebrow">FINANCES</span><h2>Recettes validées</h2></div></div>
            <div class="dashboard-list">
                @forelse ($receipts as $row)
                    <article class="dashboard-list-row"><strong>{{ number_format((float) $row->total, 2, ',', ' ') }} {{ $row->currency }}</strong><span>{{ $from }} → {{ $to }}</span></article>
                @empty
                    <p>Aucune recette validée sur cette période.</p>
                @endforelse
            </div>
        </section>

        <section class="dashboard-table-section">
            <div class="dashboard-section-heading"><div><span class="eyebrow">FINANCES</span><h2>Dépenses validées</h2></div></div>
            <div class="dashboard-list">
                @forelse ($expenses as $row)
                    <article class="dashboard-list-row"><strong>{{ number_format((float) $row->total, 2, ',', ' ') }} {{ $row->currency }}</strong><span>{{ $from }} → {{ $to }}</span></article>
                @empty
                    <p>Aucune dépense validée sur cette période.</p>
                @endforelse
            </div>
        </section>

        <section class="dashboard-table-section">
            <div class="dashboard-section-heading"><div><span class="eyebrow">PÉDAGOGIE</span><h2>Inscriptions par statut</h2></div></div>
            <div class="dashboard-list">
                @forelse ($enrollments as $row)
                    <article class="dashboard-list-row"><strong>{{ $row->total }}</strong><span>{{ $row->status }}</span></article>
                @empty
                    <p>Aucune inscription sur cette période.</p>
                @endforelse
            </div>
        </section>

        <section class="dashboard-table-section">
            <div class="dashboard-section-heading"><div><span class="eyebrow">PÉDAGOGIE</span><h2>Inscriptions par formation</h2></div></div>
            <div class="dashboard-list">
                @forelse ($trainingEnrollments as $row)
                    <article class="dashboard-list-row"><div><strong>{{ $row->training_title }}</strong><span>{{ $row->status }}</span></div><strong>{{ $row->total }}</strong></article>
                @empty
                    <p>Aucune inscription sur cette période.</p>
                @endforelse
            </div>
        </section>
    </section>
@endsection
