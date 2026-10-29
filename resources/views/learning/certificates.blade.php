@extends('layouts.app')
@section('title', 'Mes certificats')
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">PARCOURS ACCOMPLI</span><h1>Mes certificats</h1><p>Les attestations délivrées par DK ACADEMIA apparaissent ici.</p></div>
        <div class="certificate-grid">@forelse ($certificates as $certificate)<article class="certificate-card"><span class="certificate-mark">DK</span><span class="eyebrow">CERTIFICAT DE FORMATION</span><h2>{{ $certificate->enrollment->training->title }}</h2><p>Décerné à <strong>{{ auth()->user()->name }}</strong></p><p>Numéro : <strong>{{ $certificate->certificate_number }}</strong></p><p class="muted">Délivré le {{ $certificate->issued_at->format('d/m/Y') }}</p></article>@empty<div class="empty-state"><h2>Aucun certificat n’a encore été délivré.</h2><p>Une attestation apparaîtra ici lorsqu’une formation sera terminée et validée.</p></div>@endforelse</div>{{ $certificates->links() }}
    </section>
@endsection
