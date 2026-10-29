@extends('layouts.app')

@section('title', 'Gérer les actualités')

@section('content')
    <section class="dashboard-wrap">
        <div class="dashboard-heading">
            <div class="welcome-block">
                <span class="eyebrow">CONTENUS DU SITE</span>
                <h1>Actualités</h1>
                <p>Créez les articles, préparez les brouillons et publiez-les sur le site.</p>
            </div>
            <a class="button button-primary" href="{{ route('management.content.news.create') }}">Nouvelle actualité</a>
        </div>

        <form class="dashboard-panel content-filter" method="GET" action="{{ route('management.content.news.index') }}">
            <label>Rechercher<input type="search" name="q" value="{{ $search }}" placeholder="Titre ou catégorie"></label>
            <label>Statut
                <select name="status">
                    <option value="">Tous</option>
                    <option value="published" @selected($status === 'published')>Publiées</option>
                    <option value="draft" @selected($status === 'draft')>Brouillons</option>
                </select>
            </label>
            <button class="button button-outline button-small" type="submit">Filtrer</button>
        </form>

        <div class="content-post-list">
            @forelse ($posts as $post)
                <article class="content-post-row">
                    <div class="content-post-main">
                        <strong>{{ $post->title }}</strong>
                        <span>{{ $post->category ?: 'Sans catégorie' }} · {{ $post->updated_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <span class="status-pill {{ $post->is_published ? 'content-status-published' : 'content-status-draft' }}">
                        {{ $post->is_published ? 'Publiée' : 'Brouillon' }}
                    </span>
                    <div class="content-post-actions">
                        <a class="text-link" href="{{ route('management.content.news.edit', $post) }}">Modifier</a>
                        @if ($post->is_published)<a class="text-link" href="{{ route('news.show', $post) }}" target="_blank" rel="noopener">Voir</a>@endif
                        <form method="POST" action="{{ route('management.content.news.publication', $post) }}">
                            @csrf
                            @method('PATCH')
                            <button class="text-button" type="submit">{{ $post->is_published ? 'Dépublier' : 'Publier' }}</button>
                        </form>
                        <form method="POST" action="{{ route('management.content.news.destroy', $post) }}" onsubmit="return confirm('Supprimer définitivement cette actualité ?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-button text-danger" type="submit">Supprimer</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty-state"><h2>Aucune actualité trouvée</h2><p>Créez un premier article pour alimenter le site.</p></div>
            @endforelse
        </div>
        {{ $posts->links() }}
        <a class="text-link content-back-link" href="{{ route('management.content.pages.index') }}">Gérer les pages À propos et Mission et vision</a>
    </section>
@endsection
