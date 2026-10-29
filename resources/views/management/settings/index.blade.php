@extends('layouts.app')

@section('title', 'Paramètres généraux')

@section('content')
    <section class="form-page">
        <div class="page-heading">
            <span class="eyebrow">ADMINISTRATION</span>
            <h1>Paramètres généraux</h1>
            <p>Gérez l’identité, les coordonnées et la devise par défaut de l’organisation.</p>
        </div>

        <form class="form-card" method="POST" action="{{ route('management.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="field-grid">
                <label>Nom de l’organisation
                    <input name="company_name" value="{{ old('company_name', $settings->company_name) }}" maxlength="160" required>
                    @error('company_name')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Slogan
                    <input name="slogan" value="{{ old('slogan', $settings->slogan) }}" maxlength="255" required>
                    @error('slogan')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Adresse e-mail de contact
                    <input type="email" name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}" maxlength="255">
                    @error('contact_email')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Téléphone
                    <input type="tel" name="phone" value="{{ old('phone', $settings->phone) }}" maxlength="40">
                    @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Site web
                    <input type="url" name="website" value="{{ old('website', $settings->website) }}" maxlength="255" placeholder="https://exemple.cd">
                    @error('website')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Devise par défaut
                    <select name="default_currency" required>
                        @foreach (['CDF' => 'Franc congolais (CDF)', 'USD' => 'Dollar américain (USD)', 'EUR' => 'Euro (EUR)'] as $currency => $label)
                            <option value="{{ $currency }}" @selected(old('default_currency', $settings->default_currency) === $currency)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('default_currency')<span class="field-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <label>Adresse physique
                <textarea name="address" rows="3" maxlength="500">{{ old('address', $settings->address) }}</textarea>
                @error('address')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <button class="button button-primary" type="submit">Enregistrer les paramètres</button>
        </form>
    </section>
@endsection
