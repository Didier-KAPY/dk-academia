@extends('layouts.app')

@section('title', 'Pages publiques')

@section('content')
    <section class="dashboard-wrap">
        <div class="dashboard-heading">
            <div class="welcome-block">
                <span class="eyebrow">ADMINISTRATION</span>
                <h1>Contenus du site</h1>
                <p>Modifiez les pages de présentation visibles par les visiteurs.</p>
            </div>
            <a class="button button-outline" href="{{ route('management.content.news.index') }}">Gérer les actualités</a>
        </div>

        <div class="page-content-cards">
            @foreach ($pages as $page)
                <article class="content-admin-card">
                    <span class="eyebrow">{{ $page->eyebrow }}</span>
                    <h2>{{ $page->title }}</h2>
                    <p>{{ $page->introduction }}</p>
                    <div class="content-admin-actions">
                        <a class="button button-primary button-small" href="{{ route('management.content.pages.edit', $page->slug) }}">Modifier la page</a>
                        <a class="text-link" href="{{ $page->slug === 'about' ? route('about') : route('mission') }}" target="_blank" rel="noopener">Voir la page publique</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
