@extends('layouts.app')
@section('title', 'Inscription à '.$training->title)
@section('content')
    <section class="form-page"><div class="page-heading"><span class="eyebrow">INSCRIPTION EN LIGNE</span><h1>{{ $training->title }}</h1><p>Votre demande sera vérifiée par notre équipe. Aucun paiement n’est prélevé par ce formulaire.</p></div>
        @if ($deliveryModes->isNotEmpty())
        <form class="form-card" method="POST" action="{{ route('enrollments.store', $training) }}">
            @csrf
            <label>Modalité de formation<select name="delivery_mode_id" required><option value="">Choisir une modalité</option>@foreach ($deliveryModes as $mode)<option value="{{ $mode->id }}" @selected((string) old('delivery_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>@endforeach</select>@error('delivery_mode_id')<span class="field-error">{{ $message }}</span>@enderror</label>
            @if ($sessions->isNotEmpty())<label>Session (facultatif)<select name="training_session_id"><option value="">Aucune session pour le moment</option>@foreach ($sessions as $session)<option value="{{ $session->id }}" @selected((string) old('training_session_id') === (string) $session->id)>{{ $session->name }} — {{ $session->starts_at?->format('d/m/Y à H:i') ?? 'Date à confirmer' }} @if ($session->deliveryMode)· {{ $session->deliveryMode->name }}@endif</option>@endforeach</select>@error('training_session_id')<span class="field-error">{{ $message }}</span>@enderror</label>@endif
            <label class="checkbox-field"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>Je confirme l’exactitude des informations et accepte que ma demande soit traitée.</span></label>@error('terms')<span class="field-error">{{ $message }}</span>@enderror
            <button class="button button-primary" type="submit">Transmettre ma demande</button>
        </form>
        @else
            <div class="empty-state"><h2>Les inscriptions ne sont pas ouvertes actuellement.</h2><p>Contactez-nous pour connaître les prochaines disponibilités.</p><a class="button button-primary" href="{{ route('contact') }}">Nous contacter</a></div>
        @endif
    </section>
@endsection
