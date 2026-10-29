@extends('layouts.app')
@section('title', 'Créer une évaluation')
@section('content')
    <section class="form-page"><div class="page-heading"><span class="eyebrow">RÉSULTATS PÉDAGOGIQUES</span><h1>Créer une évaluation</h1><p>Définissez une question, son barème et la bonne réponse. La réponse correcte reste privée.</p></div>
        <form class="form-card" method="POST" action="{{ route('management.pedagogy.evaluations.store') }}">@csrf
            <div class="field-grid"><label>Formation<select name="training_id" required><option value="">Choisir</option>@foreach ($trainings as $training)<option value="{{ $training->id }}" @selected(old('training_id') == $training->id)>{{ $training->title }}</option>@endforeach</select>@error('training_id')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Session (facultatif)<select name="training_session_id"><option value="">Toute la formation</option>@foreach ($sessions as $session)<option value="{{ $session->id }}" @selected(old('training_session_id') == $session->id)>{{ $session->training->title }} — {{ $session->name }}</option>@endforeach</select>@error('training_session_id')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Titre<input name="title" value="{{ old('title') }}" required maxlength="255">@error('title')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Type<select name="type"><option value="quiz">Quiz</option><option value="exam">Examen</option><option value="exercise">Exercice</option></select></label>
                <label>Points requis pour réussir<input type="number" name="passing_points" min="0" step="0.01" value="{{ old('passing_points') }}"></label>
                <label>Ouverture<input type="datetime-local" name="opens_at" value="{{ old('opens_at') }}"></label>
                <label>Clôture<input type="datetime-local" name="closes_at" value="{{ old('closes_at') }}"></label>
            </div>
            <label>Description<textarea name="description" rows="3">{{ old('description') }}</textarea></label>
            @for ($questionIndex = 0; $questionIndex < 3; $questionIndex++)
                <fieldset class="question-card"><legend>Question {{ $questionIndex + 1 }}</legend><label>Énoncé<textarea name="questions[{{ $questionIndex }}][prompt]" rows="3">{{ old('questions.'.$questionIndex.'.prompt') }}</textarea></label>
                    <div class="field-grid"><label>Type de question<select name="questions[{{ $questionIndex }}][question_type]"><option value="multiple_choice" @selected(old('questions.'.$questionIndex.'.question_type', 'multiple_choice') === 'multiple_choice')>Choix multiples</option><option value="short_answer" @selected(old('questions.'.$questionIndex.'.question_type') === 'short_answer')>Réponse courte (correction manuelle)</option></select></label><label>Points<input type="number" name="questions[{{ $questionIndex }}][points]" min="0.01" step="0.01" value="{{ old('questions.'.$questionIndex.'.points', 1) }}"></label></div>
                    <label>Choix de réponse (pour une question à choix multiples)<div class="field-grid">@for ($optionIndex = 0; $optionIndex < 4; $optionIndex++)<input name="questions[{{ $questionIndex }}][options][]" value="{{ old('questions.'.$questionIndex.'.options.'.$optionIndex) }}" placeholder="Choix {{ $optionIndex + 1 }}">@endfor</div></label>
                    <label>Bonne réponse (privée)<input name="questions[{{ $questionIndex }}][answer]" value="{{ old('questions.'.$questionIndex.'.answer') }}" maxlength="5000"></label>
                    @error('questions.'.$questionIndex.'.prompt')<span class="field-error">{{ $message }}</span>@enderror
                    @error('questions.'.$questionIndex.'.answer')<span class="field-error">{{ $message }}</span>@enderror
                    @error('questions.'.$questionIndex.'.options')<span class="field-error">{{ $message }}</span>@enderror
                </fieldset>
            @endfor
            <label class="checkbox-field"><input type="checkbox" name="is_published" value="1" @checked(old('is_published'))><span>Publier dès maintenant</span></label>
            <button class="button button-primary" type="submit">Créer l’évaluation</button>
        </form>
    </section>
@endsection
