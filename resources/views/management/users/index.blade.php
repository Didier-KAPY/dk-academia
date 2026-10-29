@extends('layouts.app')

@section('title', 'Utilisateurs')
@section('body-class', 'dashboard-page')

@section('content')
    <section class="listing-page">
        <div class="page-heading">
            <span class="eyebrow">ADMINISTRATION</span>
            <h1>Utilisateurs</h1>
            <p>Consultez les comptes et leurs espaces d’accès. Les mots de passe ne sont jamais affichés.</p>
            @if (auth()->user()->hasPermissionTo('roles.manage'))
                <a class="button button-outline" href="{{ route('management.roles.index') }}">Voir les rôles et permissions</a>
            @endif
        </div>

        <form method="GET" action="{{ route('management.users.index') }}" class="inline-form">
            <label for="user-search">Rechercher par nom ou adresse courriel</label>
            <input id="user-search" name="q" type="search" value="{{ $search }}" maxlength="255">
            <button class="button button-primary" type="submit">Rechercher</button>
            @if ($search !== '')
                <a class="button button-outline" href="{{ route('management.users.index') }}">Effacer</a>
            @endif
        </form>

        <section class="dashboard-table-section">
            <div class="dashboard-section-heading">
                <div><span class="eyebrow">COMPTES</span><h2>{{ number_format($users->total(), 0, ',', ' ') }} utilisateur(s)</h2></div>
            </div>
            <div class="dashboard-list">
                @forelse ($users as $user)
                    <article class="dashboard-list-row">
                        <div>
                            <strong>{{ $user->name }}</strong>
                            <span>{{ $user->email }} · {{ $user->roles_count }} rôle(s)</span>
                            <div class="role-list">
                                @forelse ($user->roles as $role)
                                    <span class="status-pill">{{ $role->name }}{{ $role->pivot->is_primary ? ' · Principal' : '' }}</span>
                                @empty
                                    <span class="status-pill">Aucun rôle</span>
                                @endforelse
                            </div>
                        </div>
                        @if (auth()->user()->hasPermissionTo('users.manage'))
                            <a class="text-link" href="{{ route('management.users.roles.edit', $user) }}">Gérer les rôles →</a>
                        @endif
                    </article>
                @empty
                    <div class="empty-state"><h2>Aucun utilisateur trouvé.</h2><p>Modifiez la recherche ou effacez le filtre.</p></div>
                @endforelse
            </div>
            {{ $users->links() }}
        </section>
    </section>
@endsection
