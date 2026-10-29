@extends('layouts.app')
@section('title', $evaluation->title)
@section('content')
    <section class="form-page"><a class="text-link" href="{{ route('learning.index') }}">← Retour à mes cours</a><div class="page-heading"><span class="eyebrow">ÉVALUATION</span><h1>{{ $evaluation->title }}</h1><p>{{ $evaluation->description }}</p></div>
        @if ($attempt->status === 'submitted')<div class="dashboard-panel"><div class="panel-symbol">✓</div><div><h2>Votre évaluation a été remise.</h2>@if ($attempt->score !== null)<p>Votre résultat : {{ $attempt->score }} / {{ $evaluation->total_points }} points.</p>@else<p>Vos réponses nécessitent une correction manuelle. Le résultat sera affiché après révision.</p>@endif</div></div>
        @else<form class="form-card" method="POST" action="{{ route('learning.evaluations.submit', $evaluation) }}">@csrf
            @foreach ($questions as $question)<fieldset class="question-card"><legend>{{ $loop->iteration }}. {{ $question->prompt }} <span class="muted">({{ $question->points }} pt)</span></legend>
                @if ($question->question_type === 'multiple_choice')@foreach ($question->options ?? [] as $option)<label class="checkbox-field"><input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}" @checked(old('answers.'.$question->id) === $option) required><span>{{ $option }}</span></label>@endforeach
                @else<label>Votre réponse<textarea name="answers[{{ $question->id }}]" rows="3" maxlength="5000" required>{{ old('answers.'.$question->id) }}</textarea></label>@endif
                @error('answers.'.$question->id)<span class="field-error">{{ $message }}</span>@enderror
            </fieldset>@endforeach
            @error('answers')<span class="field-error">{{ $message }}</span>@enderror
            <p class="muted">Après remise, vos réponses ne peuvent plus être modifiées.</p><button class="button button-primary" type="submit">Remettre l’évaluation</button>
        </form>@endif
    </section>
@endsection
