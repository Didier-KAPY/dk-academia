@extends('layouts.app')
@section('title', $training->exists ? 'Modifier une formation' : 'Créer une formation')
@section('content')
    <section class="form-page"><div class="page-heading"><span class="eyebrow">CATALOGUE INTERNE</span><h1>{{ $training->exists ? 'Modifier la formation' : 'Créer une formation' }}</h1><p>Les formations publiées apparaissent dans le catalogue public.</p></div>
        <form class="form-card" method="POST" action="{{ $training->exists ? route('management.trainings.update', $training) : route('management.trainings.store') }}">
            @csrf @if ($training->exists)@method('PUT')@endif
            <div class="field-grid">
                <label>Titre<input name="title" value="{{ old('title', $training->title) }}" required maxlength="255">@error('title')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Slug public (facultatif)<input name="slug" value="{{ old('slug', $training->slug) }}" maxlength="255" placeholder="généré depuis le titre"></label>
                <label>Catégorie<select name="category_id"><option value="">Sans catégorie</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $training->category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <label>Niveau<select name="level" required>@foreach (['debutant' => 'Débutant', 'intermediaire' => 'Intermédiaire', 'avance' => 'Avancé', 'expert' => 'Expert'] as $value => $label)<option value="{{ $value }}" @selected(old('level', $training->level) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Durée en heures<input type="number" name="duration_hours" min="1" value="{{ old('duration_hours', $training->duration_hours) }}"></label>
                <label>Tarif<input type="number" name="price" min="0" step="0.01" value="{{ old('price', $training->price) }}"></label>
                <label>Devise<input name="currency" value="{{ old('currency', $training->currency ?: 'USD') }}" required maxlength="3"></label>
                <label>Chemin de l’image (facultatif)<input name="image_path" value="{{ old('image_path', $training->image_path) }}" maxlength="255" placeholder="images/formations/nom.jpg"></label>
            </div>
            <label>Résumé<textarea name="summary" rows="2" maxlength="500">{{ old('summary', $training->summary) }}</textarea></label>
            <label>Description<textarea name="description" rows="5">{{ old('description', $training->description) }}</textarea></label>
            <label>Public cible<textarea name="target_audience" rows="2">{{ old('target_audience', $training->target_audience) }}</textarea></label>
            <fieldset class="checklist-field"><legend>Modalités proposées</legend>
                @foreach ($deliveryModes as $mode)
                    <label class="checkbox-field"><input type="checkbox" name="delivery_modes[]" value="{{ $mode->id }}" @checked(collect(old('delivery_modes', $training->deliveryModes->pluck('id')->all()))->contains($mode->id))><span>{{ $mode->name }}</span></label>
                @endforeach
                @error('delivery_modes')<span class="field-error">{{ $message }}</span>@enderror
            </fieldset>
            <fieldset class="checklist-field"><legend>Objectifs pédagogiques (jusqu’à 3)</legend>@for ($i = 0; $i < 3; $i++)<input name="objectives[]" value="{{ old('objectives.'.$i, $training->objectives[$i] ?? '') }}" placeholder="Objectif {{ $i + 1 }}">@endfor</fieldset>
            <fieldset class="checklist-field"><legend>Programme indicatif (jusqu’à 3 éléments)</legend>@for ($i = 0; $i < 3; $i++)<input name="syllabus[]" value="{{ old('syllabus.'.$i, $training->syllabus[$i] ?? '') }}" placeholder="Module {{ $i + 1 }}">@endfor</fieldset>
            <label>Prérequis<textarea name="prerequisites" rows="2">{{ old('prerequisites', $training->prerequisites) }}</textarea></label>
            <label class="checkbox-field"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $training->is_published))><span>Publier cette formation sur le catalogue</span></label>
            <div class="form-actions"><button class="button button-primary" type="submit">{{ $training->exists ? 'Enregistrer les changements' : 'Créer la formation' }}</button><a class="button button-outline" href="{{ route('management.trainings.index') }}">Annuler</a></div>
        </form>
    </section>
@endsection
