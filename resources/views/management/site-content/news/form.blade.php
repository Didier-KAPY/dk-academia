@extends('layouts.app')

@section('title', $post->exists ? 'Modifier une actualité' : 'Nouvelle actualité')

@section('content')
    <section class="form-page">
        <div class="page-heading">
            <span class="eyebrow">CONTENUS DU SITE</span>
            <h1>{{ $post->exists ? 'Modifier l’actualité' : 'Nouvelle actualité' }}</h1>
            <p>Les brouillons restent invisibles au public. Une date future permet de programmer la publication.</p>
        </div>

        <form class="form-card" method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('management.content.news.update', $post) : route('management.content.news.store') }}">
            @csrf
            @if ($post->exists)@method('PUT')@endif

            <div class="field-grid">
                <label>Titre
                    <input name="title" value="{{ old('title', $post->title) }}" maxlength="255" required>
                    @error('title')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Slug (facultatif)
                    <input name="slug" value="{{ old('slug', $post->slug) }}" maxlength="255" placeholder="généré-depuis-le-titre">
                    @error('slug')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Catégorie
                    <input name="category" value="{{ old('category', $post->category) }}" maxlength="255">
                    @error('category')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Image (JPG, PNG ou WebP, 5 Mo maximum)
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    @error('image')<span class="field-error">{{ $message }}</span>@enderror
                </label>
            </div>

            @if ($post->image_path)
                <div class="content-image-preview"><img src="{{ asset('storage/'.$post->image_path) }}" alt="Image actuelle : {{ $post->title }}"><span>Image actuelle</span></div>
            @endif

            <label>Résumé
                <textarea name="excerpt" rows="3" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea>
                @error('excerpt')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <label>Contenu de l’article
                <textarea name="body" rows="14" maxlength="50000" required>{{ old('body', $post->body) }}</textarea>
                @error('body')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <label class="checkbox-field"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))><span>Publier cette actualité</span></label>

            <label>Date et heure de publication
                <input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\\TH:i')) }}">
                @error('published_at')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <div class="form-actions">
                <button class="button button-primary" type="submit">{{ $post->exists ? 'Enregistrer les modifications' : 'Créer l’actualité' }}</button>
                <a class="button button-outline" href="{{ route('management.content.news.index') }}">Annuler</a>
            </div>
        </form>
    </section>
@endsection
