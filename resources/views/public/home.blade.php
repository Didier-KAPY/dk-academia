@extends('layouts.app')

@section('title', 'Formation professionnelle')
@section('body-class', 'home-page')

@section('content')
    <section class="hero">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> LA COMPÉTENCE QUI OUVRE DES PORTES</span>
            <h1>Apprendre aujourd’hui.<br><span>Réussir demain.</span></h1>
            <p class="hero-description">Des formations professionnelles et des solutions numériques pensées pour faire grandir vos projets en République démocratique du Congo.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="{{ route('trainings.index') }}">Consulter les formations <span aria-hidden="true">→</span></a>
                <a class="button button-outline" href="#domaines">Découvrir DK ACADEMIA</a>
            </div>
            <div class="hero-proof"><span class="proof-stars" aria-hidden="true">★★★★★</span><span>Des compétences utiles. Des résultats concrets.</span></div>
        </div>
        <div class="hero-visual" aria-label="Illustration de la réussite professionnelle">
            <div class="visual-orbit orbit-one"></div>
            <div class="visual-orbit orbit-two"></div>
            <div class="visual-card card-top"><span class="visual-icon">✦</span><span>Un savoir-faire<br><strong>qui vous ressemble</strong></span></div>
            <div class="visual-monogram">DK<span>.</span></div>
            <div class="visual-card card-bottom"><span class="mini-bars"><i></i><i></i><i></i><i></i></span><span>Votre prochain<br><strong>objectif commence ici</strong></span></div>
            <span class="visual-spark spark-one">✳</span><span class="visual-spark spark-two">✦</span>
        </div>
    </section>

    <section class="domain-section" id="domaines">
        <div class="section-heading">
            <div><span class="eyebrow">APPRENDRE ET PROGRESSER</span><h2>Des compétences pour aller plus loin.</h2></div>
            <p>Un accompagnement concret pour votre carrière, votre entreprise et votre avenir numérique.</p>
        </div>
        <div class="domain-grid">
            <article class="domain-card"><span class="domain-number">01</span><span class="domain-icon">⌘</span><h3>Informatique &amp; numérique</h3><p>Développement web, bureautique, intelligence artificielle et outils numériques.</p></article>
            <article class="domain-card"><span class="domain-number">02</span><span class="domain-icon">▤</span><h3>Gestion &amp; comptabilité</h3><p>Comptabilité, gestion d’entreprise et pratiques selon le SYSCOHADA.</p></article>
            <article class="domain-card"><span class="domain-number">03</span><span class="domain-icon">↗</span><h3>Entrepreneuriat</h3><p>Les méthodes et les outils pour transformer une idée en activité durable.</p></article>
            <article class="domain-card"><span class="domain-number">04</span><span class="domain-icon">✳</span><h3>Solutions informatiques</h3><p>Des services numériques adaptés aux besoins des professionnels et des organisations.</p></article>
        </div>
    </section>

    <section class="home-cta">
        <div><span class="eyebrow eyebrow-light">VOTRE PROCHAINE ÉTAPE</span><h2>Investissez dans ce que vous pouvez devenir.</h2></div>
        <a class="button button-gold" href="{{ route('contact') }}">Demander des informations <span aria-hidden="true">→</span></a>
    </section>
@endsection
