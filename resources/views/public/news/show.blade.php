@extends('layouts.app')
@section('title', $post->title)
@section('content')
    <article class="detail-page prose"><a class="text-link" href="{{ route('news.index') }}">← Toutes les actualités</a>@if ($post->category)<span class="tag">{{ $post->category }}</span>@endif<h1>{{ $post->title }}</h1><p class="muted">{{ $post->published_at->format('d/m/Y') }}</p>@if ($post->image_path)<img class="detail-image" src="{{ asset('storage/'.$post->image_path) }}" alt="{{ $post->title }}">@endif
        @if ($post->excerpt)<p class="lead">{{ $post->excerpt }}</p>@endif<div class="article-body">{{ $post->body }}</div></article>
@endsection
