@extends('layouts.app')

@section('title', 'Rôles et permissions')
@section('body-class', 'dashboard-page')

@section('content')
    <section class="listing-page">
        <div class="page-heading">
            <span class="eyebrow">ADMINISTRATION DES ACCÈS</span>
            <h1>Rôles et permissions</h1>
            <p>Consultez les permissions attribuées à chaque rôle et le nombre de comptes associés.</p>
            <a class="text-link" href="{{ route('management.users.index') }}">Gérer les utilisateurs →</a>
        </div>

        <div class="dashboard-list">
            @foreach ($roles as $role)
                <article class="dashboard-list-row">
                    <div>
                        <strong>{{ $role->name }}</strong>
                        <span>{{ $role->slug }} · {{ $role->users_count }} utilisateur(s)</span>
                        @if ($role->description)<p>{{ $role->description }}</p>@endif
                        <div class="role-list">
                            @forelse ($role->permissions as $permission)
                                <span class="status-pill" title="{{ $permission->slug }}">{{ $permission->name }}</span>
                            @empty
                                <span class="status-pill">Aucune permission attribuée</span>
                            @endforelse
                        </div>
                    </div>
                    <a class="text-link" href="{{ route('management.roles.permissions.edit', $role) }}">Modifier les permissions →</a>
                </article>
            @endforeach
        </div>
    </section>
@endsection
