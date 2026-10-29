@extends('layouts.app')

@section('title', $page->slug === 'about' ? 'À propos' : 'Mission et vision')

@section('content')
    <section class="content-page">
        <span class="eyebrow">{{ $page->eyebrow }}</span>
        <h1>{{ $page->title }}</h1>
        <p class="lead">{{ $page->introduction }}</p>

        @foreach ($paragraphs as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach

        @if ($page->secondary_title)
            <h2>{{ $page->secondary_title }}</h2>
            @if ($page->secondary_body)<p>{{ $page->secondary_body }}</p>@endif
        @endif

        @if ($page->slug === 'about')
            <div class="hero-actions">
                <a class="button button-primary" href="{{ route('trainings.index') }}">Découvrir les formations</a>
                <a class="button button-outline" href="{{ route('contact') }}">Nous contacter</a>
            </div>
        @else
            <a class="button button-primary" href="{{ route('contact') }}">Parlons de votre projet</a>
        @endif
    </section>
@endsection
