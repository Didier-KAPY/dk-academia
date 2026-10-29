@extends('layouts.app')
@section('title', 'Actualités')
@section('content')
    <section class="listing-page"><div class="page-heading"><span class="eyebrow">LA VIE DE DK ACADEMIA</span><h1>Actualités</h1><p>Nos annonces, événements et conseils de formation.</p></div>
        <div class="catalog-grid">@forelse ($posts as $post)<article class="catalog-card">@if ($post->image_path)<img src="{{ asset('storage/'.$post->image_path) }}" alt="{{ $post->title }}">@else<span class="catalog-placeholder">DK</span>@endif<div class="catalog-card-body">@if ($post->category)<span class="tag">{{ $post->category }}</span>@endif<h2><a href="{{ route('news.show', $post) }}">{{ $post->title }}</a></h2><p>{{ $post->excerpt }}</p><span class="muted">{{ $post->published_at->format('d/m/Y') }}</span><p><a class="text-link" href="{{ route('news.show', $post) }}">Lire l’article →</a></p></div></article>@empty<div class="empty-state"><h2>Pas encore d’actualité publiée.</h2><p>Les nouvelles de DK ACADEMIA apparaîtront ici.</p></div>@endforelse</div>{{ $posts->links() }}
    </section>
@endsection
