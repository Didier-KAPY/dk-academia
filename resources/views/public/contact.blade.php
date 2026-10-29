@extends('layouts.app')
@section('title', 'Contact')
@section('content')
    <section class="form-page"><div class="page-heading"><span class="eyebrow">ÉCHANGEONS</span><h1>Contactez DK ACADEMIA</h1><p>Une question sur une formation ou un projet numérique ? Écrivez-nous.</p></div>
        <form class="form-card" method="POST" action="{{ route('contact.store') }}">
            @csrf
            <div class="field-grid"><label>Nom complet<input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Adresse e-mail<input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Téléphone (facultatif)<input name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel">@error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Sujet<input name="subject" value="{{ old('subject', request('subject')) }}" required maxlength="160">@error('subject')<span class="field-error">{{ $message }}</span>@enderror</label>
            </div>
            <label>Votre message<textarea name="message" rows="7" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea>@error('message')<span class="field-error">{{ $message }}</span>@enderror</label>
            <div class="trap-field" aria-hidden="true"><label>Ne pas remplir<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <button class="button button-primary" type="submit">Envoyer le message</button>
        </form>
    </section>
@endsection
