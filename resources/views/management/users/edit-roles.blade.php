@extends('layouts.app')

@section('title', 'Rôles de '.$user->name)
@section('body-class', 'dashboard-page')

@section('content')
    <section class="listing-page">
        <div class="page-heading">
            <span class="eyebrow">ADMINISTRATION DES ACCÈS</span>
            <h1>Rôles de {{ $user->name }}</h1>
            <p>{{ $user->email }}</p>
            <a class="text-link" href="{{ route('management.users.index') }}">← Retour aux utilisateurs</a>
        </div>

        @if ($errors->any())
            <div class="form-alert" role="alert">{{ $errors->first() }}</div>
        @endif

        @php
            $selectedRoleIds = old('role_ids', $user->roles->pluck('id')->all());
            $selectedRoleIds = is_array($selectedRoleIds) ? array_map('intval', $selectedRoleIds) : [];
            $oldPrimaryRoleId = old('primary_role_id', $primaryRoleId);
            $selectedPrimaryRoleId = is_scalar($oldPrimaryRoleId) ? (int) $oldPrimaryRoleId : null;
        @endphp

        <form method="POST" action="{{ route('management.users.roles.update', $user) }}" class="inline-form">
            @csrf
            @method('PUT')
            <fieldset class="checklist-field">
                <legend>Rôles attribués</legend>
                <p>Sélectionnez au moins un rôle. Les permissions associées à chaque rôle s’appliquent à ce compte.</p>
                <div class="field-grid">
                    @foreach ($roles as $role)
                        <label class="checkbox-field">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}"
                                @checked(in_array($role->id, $selectedRoleIds, true))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('role_ids')<p class="form-alert" role="alert">{{ $message }}</p>@enderror
            </fieldset>

            <label for="primary-role">Rôle principal</label>
            <select id="primary-role" name="primary_role_id" required>
                <option value="">Choisir parmi les rôles sélectionnés</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($selectedPrimaryRoleId === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('primary_role_id')<p class="form-alert" role="alert">{{ $message }}</p>@enderror

            @if (auth()->id() === $user->id)
                <p>Votre rôle administrateur ne peut pas être retiré de votre propre compte.</p>
            @endif

            <div class="form-actions">
                <button class="button button-primary" type="submit">Enregistrer les rôles</button>
                <a class="button button-outline" href="{{ route('management.users.index') }}">Annuler</a>
            </div>
        </form>
    </section>
@endsection
