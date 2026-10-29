@extends('layouts.app')

@section('title', 'Modifier une page publique')

@section('content')
    <section class="form-page">
        <div class="page-heading">
            <span class="eyebrow">CONTENUS DU SITE</span>
            <h1>Modifier : {{ $page->slug === 'about' ? 'À propos' : 'Mission et vision' }}</h1>
            <p>Le texte est affiché tel qu’il est saisi ; séparez les paragraphes par une ligne vide.</p>
        </div>

        <form class="form-card" method="POST" action="{{ route('management.content.pages.update', $page->slug) }}">
            @csrf
            @method('PUT')

            <div class="field-grid">
                <label>Petit titre de section
                    <input name="eyebrow" value="{{ old('eyebrow', $page->eyebrow) }}" maxlength="120" required>
                    @error('eyebrow')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Titre principal
                    <input name="title" value="{{ old('title', $page->title) }}" maxlength="200" required>
                    @error('title')<span class="field-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <label>Introduction
                <textarea name="introduction" rows="4" maxlength="2000" required>{{ old('introduction', $page->introduction) }}</textarea>
                @error('introduction')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <label>Texte principal
                <textarea name="body" rows="8" maxlength="20000">{{ old('body', $page->body) }}</textarea>
                @error('body')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <div class="field-grid">
                <label>Titre de la deuxième section (facultatif)
                    <input name="secondary_title" value="{{ old('secondary_title', $page->secondary_title) }}" maxlength="200">
                    @error('secondary_title')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Texte de la deuxième section (facultatif)
                    <textarea name="secondary_body" rows="4" maxlength="20000">{{ old('secondary_body', $page->secondary_body) }}</textarea>
                    @error('secondary_body')<span class="field-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <div class="form-actions">
                <button class="button button-primary" type="submit">Enregistrer la page</button>
                <a class="button button-outline" href="{{ route('management.content.pages.index') }}">Retour</a>
            </div>
        </form>
    </section>
@endsection
