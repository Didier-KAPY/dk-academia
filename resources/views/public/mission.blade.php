@extends('layouts.app')
@section('title', 'Mission et vision')
@section('content')
    <section class="content-page">
        <span class="eyebrow">NOTRE MISSION</span>
        <h1>Rendre les compétences utiles accessibles.</h1>
        <p>Nous transmettons des savoir-faire professionnels directement mobilisables et accompagnons les organisations dans leur évolution numérique.</p>
        <h2>Notre vision</h2>
        <p>Contribuer à un avenir où chacun peut apprendre, maîtriser de nouveaux outils et réussir durablement dans son activité.</p>
        <a class="button button-primary" href="{{ route('contact') }}">Parlons de votre projet</a>
    </section>
@endsection
