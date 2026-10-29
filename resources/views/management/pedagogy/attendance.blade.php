@extends('layouts.app')
@section('title', 'Présences — '.$session->name)
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">SUIVI DES PRÉSENCES</span><h1>{{ $session->training->title }}</h1><p>{{ $session->name }} · {{ $session->starts_at?->format('d/m/Y à H:i') ?? 'Date à confirmer' }}</p><a class="text-link" href="{{ route('management.pedagogy.sessions.index') }}">← Retour aux sessions</a></div>
        <form class="form-card" method="POST" action="{{ route('management.pedagogy.attendance.store', $session) }}">@csrf
            <label>Date du cours<input type="date" name="attendance_date" value="{{ old('attendance_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>@error('attendance_date')<span class="field-error">{{ $message }}</span>@enderror</label>
            @forelse ($enrollments as $enrollment)<div class="attendance-row"><div><strong>{{ $enrollment->user->name }}</strong><span>{{ $enrollment->user->email }}</span></div><label>Présence<select name="attendees[{{ $enrollment->user_id }}][status]">@foreach (['present' => 'Présent', 'absent' => 'Absent', 'late' => 'En retard', 'excused' => 'Justifié'] as $value => $label)<option value="{{ $value }}" @selected(old('attendees.'.$enrollment->user_id.'.status', $attendance->get($enrollment->user_id)?->status ?? 'present') === $value)>{{ $label }}</option>@endforeach</select></label><label>Note<input name="attendees[{{ $enrollment->user_id }}][notes]" value="{{ old('attendees.'.$enrollment->user_id.'.notes', $attendance->get($enrollment->user_id)?->notes) }}" maxlength="2000"></label></div>@empty<div class="empty-state"><h2>Aucun apprenant confirmé pour cette session.</h2><p>Les demandes doivent être confirmées avant la saisie des présences.</p></div>@endforelse
            @error('attendees')<span class="field-error">{{ $message }}</span>@enderror
            @if ($enrollments->isNotEmpty())<button class="button button-primary" type="submit">Enregistrer les présences</button>@endif
        </form>
    </section>
@endsection
