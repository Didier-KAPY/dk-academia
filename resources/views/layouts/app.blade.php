<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2a55">
    <title>@yield('title', $appSettings?->company_name ?? 'DK ACADEMIA') · {{ $appSettings?->company_name ?? 'DK ACADEMIA' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dk-academia.css') }}">
</head>
<body class="@yield('body-class')">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="DK ACADEMIA, accueil">
            <span class="brand-mark">DK</span>
            <span class="brand-name">{{ $appSettings?->company_name ?? 'DK ACADEMIA' }}<small>{{ $appSettings?->slogan ?? 'APPRENDRE, MAÎTRISER, RÉUSSIR.' }}</small></span>
        </a>
        <nav class="site-nav" aria-label="Navigation du site">
            <a href="{{ route('trainings.index') }}">Formations</a>
            <a href="{{ route('services.index') }}">Services</a>
            <a href="{{ route('trainers.index') }}">Formateurs</a>
            <a href="{{ route('news.index') }}">Actualités</a>
            <a href="{{ route('about') }}">À propos</a>
            <a href="{{ route('mission') }}">Mission et vision</a>
            <a href="{{ route('prices') }}">Tarifs</a>
            <a href="{{ route('contact') }}">Contact</a>
        </nav>
        <nav class="topbar-actions" aria-label="Navigation principale">
            @guest
                <a class="nav-link" href="{{ route('login') }}">Connexion</a>
                <a class="button button-small button-primary" href="{{ route('register') }}">Créer un compte</a>
            @else
                <a class="nav-link" href="{{ route('dashboard') }}">Mon espace</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button-small button-quiet" type="submit">Déconnexion</button>
                </form>
            @endguest
        </nav>
    </header>

    @if (session('status'))
        <div class="flash flash-success" role="status">{{ session('status') }}</div>
    @endif

    @php
        $sidebarUser = auth()->user();
        $hasSidebar = $sidebarUser && collect([
            'pedagogy.manage', 'trainings.manage', 'users.view', 'users.manage',
            'roles.manage', 'finance.view', 'settings.manage', 'learning.access',
        ])->contains(fn (string $permission): bool => $sidebarUser->hasPermissionTo($permission));
    @endphp

    @if ($hasSidebar)
        <div class="app-shell">
            <aside class="app-sidebar" aria-label="Menu de l’espace personnel">
                <a class="sidebar-home {{ request()->routeIs('dashboard', 'dashboard.roles.show') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Mon espace</a>

                @if ($sidebarUser->hasPermissionTo('pedagogy.manage') || $sidebarUser->hasPermissionTo('trainings.manage') || $sidebarUser->hasPermissionTo('users.view') || $sidebarUser->hasPermissionTo('users.manage') || $sidebarUser->hasPermissionTo('roles.manage') || $sidebarUser->hasPermissionTo('finance.view') || $sidebarUser->hasPermissionTo('settings.manage'))
                    <span class="sidebar-heading">Administration</span>
                    <nav class="sidebar-nav" aria-label="Administration">
                        @if ($sidebarUser->hasPermissionTo('pedagogy.manage'))<a class="{{ request()->routeIs('management.pedagogy.*') ? 'is-active' : '' }}" href="{{ route('management.pedagogy.index') }}">Gestion pédagogique</a>@endif
                        @if ($sidebarUser->hasPermissionTo('trainings.manage'))<a class="{{ request()->routeIs('management.trainings.*') ? 'is-active' : '' }}" href="{{ route('management.trainings.index') }}">Formations</a>@endif
                        @if ($sidebarUser->hasPermissionTo('users.view'))<a class="{{ request()->routeIs('management.users.*') ? 'is-active' : '' }}" href="{{ route('management.users.index') }}">Utilisateurs</a>@endif
                        @if ($sidebarUser->hasPermissionTo('roles.manage'))<a class="{{ request()->routeIs('management.roles.*') ? 'is-active' : '' }}" href="{{ route('management.roles.index') }}">Rôles et permissions</a>@endif
                        @if ($sidebarUser->hasPermissionTo('finance.view'))<a class="{{ request()->routeIs('management.reports.*') ? 'is-active' : '' }}" href="{{ route('management.reports.index') }}">Rapports</a>@endif
                        @if ($sidebarUser->hasPermissionTo('settings.manage'))<a class="{{ request()->routeIs('management.settings.*') ? 'is-active' : '' }}" href="{{ route('management.settings.index') }}">Paramètres généraux</a>@endif
                    </nav>
                @endif

                @if ($sidebarUser->hasPermissionTo('learning.access'))
                    <span class="sidebar-heading">Apprentissage</span>
                    <nav class="sidebar-nav" aria-label="Apprentissage">
                        <a class="{{ request()->routeIs('learning.index', 'learning.lessons.*', 'learning.evaluations.*') ? 'is-active' : '' }}" href="{{ route('learning.index') }}">Mes cours</a>
                        <a class="{{ request()->routeIs('learning.certificates.*') ? 'is-active' : '' }}" href="{{ route('learning.certificates.index') }}">Certificats</a>
                    </nav>
                @endif
            </aside>
            <main class="page-content">
                @yield('content')
            </main>
        </div>
    @else
        <main class="page-content">
            @yield('content')
        </main>
    @endif

    <footer class="site-footer">
        <span>© {{ date('Y') }} {{ $appSettings?->company_name ?? 'DK ACADEMIA' }}</span>
        @if ($appSettings?->contact_email || $appSettings?->phone)
            <span class="footer-contact">
                @if ($appSettings?->contact_email)<a href="mailto:{{ $appSettings->contact_email }}">{{ $appSettings->contact_email }}</a>@endif
                @if ($appSettings?->phone)<a href="tel:{{ $appSettings->phone }}">{{ $appSettings->phone }}</a>@endif
            </span>
        @endif
        <span>{{ $appSettings?->slogan ?? 'Apprendre, maîtriser, réussir.' }}</span>
    </footer>
</body>
</html>
