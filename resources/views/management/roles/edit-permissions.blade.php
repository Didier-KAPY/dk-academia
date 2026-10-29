@extends('layouts.app')

@section('title', 'Permissions de '.$role->name)
@section('body-class', 'dashboard-page')

@section('content')
    <section class="listing-page">
        <div class="page-heading">
            <span class="eyebrow">ADMINISTRATION DES ACCÈS</span>
            <h1>Permissions : {{ $role->name }}</h1>
            <p>Choisissez les permissions accordées aux utilisateurs qui possèdent ce rôle.</p>
            <a class="text-link" href="{{ route('management.roles.index') }}">← Retour aux rôles</a>
        </div>

        @if ($errors->any())
            <div class="form-alert" role="alert">{{ $errors->first() }}</div>
        @endif

        @php
            $selectedPermissionIds = old('permission_ids', $role->permissions->pluck('id')->all());
            $selectedPermissionIds = is_array($selectedPermissionIds) ? array_map('intval', $selectedPermissionIds) : [];
        @endphp

        <form method="POST" action="{{ route('management.roles.permissions.update', $role) }}" class="inline-form">
            @csrf
            @method('PUT')
            <fieldset class="checklist-field">
                <legend>Permissions disponibles</legend>
                <div class="field-grid">
                    @foreach ($permissions as $permission)
                        <label class="checkbox-field">
                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}"
                                @checked(in_array($permission->id, $selectedPermissionIds, true))>
                            <span>{{ $permission->name }} <small>{{ $permission->slug }}</small></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('permission_ids')<p class="form-alert" role="alert">{{ $message }}</p>@enderror

            @if ($role->slug === 'administrateur')
                <p>Le rôle administrateur doit conserver les droits de consultation des utilisateurs, de gestion des utilisateurs et de gestion des rôles.</p>
            @endif

            <div class="form-actions">
                <button class="button button-primary" type="submit">Enregistrer les permissions</button>
                <a class="button button-outline" href="{{ route('management.roles.index') }}">Annuler</a>
            </div>
        </form>
    </section>
@endsection
