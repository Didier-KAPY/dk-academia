@extends('layouts.app')

@section('title', $role->name)
@section('body-class', 'dashboard-page')

@section('content')
    <section class="dashboard-wrap dashboard-workspace">
        <div class="dashboard-heading">
            <div class="welcome-block">
                <span class="eyebrow">ESPACE PERSONNEL</span>
                <h1>Bonjour, {{ auth()->user()->name }}.</h1>
                <p>Voici les éléments utiles à votre espace <strong>{{ $role->name }}</strong>.</p>
                @if ($role->description)<p>{{ $role->description }}</p>@endif
            </div>
            <span class="role-badge"><span></span>{{ $role->name }}</span>
        </div>

        @if ($metrics !== [])
            <div class="dashboard-metrics">
                @foreach ($metrics as $metric)
                    <article class="metric-card">
                        <span>{{ $metric['label'] }}</span>
                        <strong>{{ number_format($metric['value'], 0, ',', ' ') }}</strong>
                        <small>{{ $metric['detail'] }}</small>
                    </article>
                @endforeach
            </div>
        @else
            <div class="dashboard-panel"><div class="panel-symbol">✦</div><div><h2>{{ $role->name }}</h2><p>{{ $role->description }}</p></div></div>
        @endif

        @if ($alerts !== [])
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">À SUIVRE</span><h2>Alertes</h2></div></div>
                <div class="dashboard-list">
                    @foreach ($alerts as $alert)
                        <article class="dashboard-list-row">
                            <div><strong>{{ $alert['label'] }}</strong><span>{{ $alert['detail'] }}</span></div>
                            <span class="status-pill">{{ number_format($alert['count'], 0, ',', ' ') }}</span>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($financeOverview !== [])
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">TRÉSORERIE</span><h2>Opérations validées</h2></div></div>
                <div class="dashboard-metrics">
                    @foreach ([
                        'daily_receipts' => 'Recettes du jour',
                        'monthly_receipts' => 'Recettes du mois',
                        'daily_expenses' => 'Dépenses du jour',
                        'monthly_expenses' => 'Dépenses du mois',
                    ] as $summaryKey => $summaryLabel)
                        <article class="metric-card">
                            <span>{{ $summaryLabel }}</span>
                            @forelse ($financeOverview[$summaryKey] as $total)
                                <strong>{{ $total['total'] }} {{ $total['currency'] }}</strong>
                            @empty
                                <strong>0</strong>
                                <small>Aucune opération validée</small>
                            @endforelse
                        </article>
                    @endforeach
                    @if ($role->slug !== 'responsable-caisse')
                        <article class="metric-card">
                            <span>Soldes des factures dues</span>
                            @forelse ($financeOverview['outstanding_invoices'] as $total)
                                <strong>{{ $total['total'] }} {{ $total['currency'] }}</strong>
                            @empty
                                <strong>0</strong>
                                <small>Aucune facture restant due</small>
                            @endforelse
                        </article>
                    @endif
                </div>

                <div class="dashboard-section-heading"><div><span class="eyebrow">CAISSES OUVERTES</span><h2>Soldes par caisse et devise</h2></div></div>
                <div class="dashboard-list">
                    @forelse ($financeOverview['open_cash_sessions'] as $cashSession)
                        <article class="dashboard-list-row">
                            <div><strong>{{ $cashSession['name'] }} · {{ $cashSession['currency'] }}</strong><span>Responsable : {{ $cashSession['cashier'] }} · Entrées validées : {{ $cashSession['inflows'] }} · Sorties validées : {{ $cashSession['outflows'] }}</span></div>
                            <strong>{{ $cashSession['balance'] !== null ? $cashSession['balance'].' '.$cashSession['currency'] : 'Solde à vérifier' }}</strong>
                        </article>
                    @empty
                        <p>Aucune session de caisse ouverte.</p>
                    @endforelse
                </div>

                <div class="dashboard-section-heading"><div><span class="eyebrow">JOURNAL DE CAISSE</span><h2>Dernières opérations</h2></div></div>
                <div class="dashboard-list">
                    @forelse ($financeOverview['recent_cash_movements'] as $movement)
                        <article class="dashboard-list-row">
                            <div><strong>{{ $movement['description'] }}</strong><span>{{ $movement['cashbox'] }} · {{ $movement['type'] }} · {{ $movement['occurred_at']?->format('d/m/Y H:i') }}</span></div>
                            <span class="status-pill">{{ $movement['amount'] }} {{ $movement['currency'] }} · {{ $movement['status'] }}</span>
                        </article>
                    @empty
                        <p>Aucune opération enregistrée.</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if (in_array($role->slug, ['apprenant', 'formateur', 'administrateur', 'gestionnaire-administratif', 'responsable-pedagogique', 'responsable-informatique', 'commercial'], true))
        <div class="dashboard-shortcuts">
            @if ($role->slug === 'apprenant')
                <a class="button button-primary" href="{{ route('learning.index') }}">Mes cours</a>
                <a class="button button-outline" href="{{ route('my-enrollments.index') }}">Mes inscriptions</a>
                <a class="button button-outline" href="{{ route('trainings.index') }}">Catalogue des formations</a>
            @elseif ($role->slug === 'formateur')
                <a class="button button-primary" href="{{ route('management.pedagogy.index') }}">Mes sessions et présences</a>
                <a class="button button-outline" href="{{ route('management.pedagogy.evaluations.index') }}">Mes évaluations</a>
            @elseif (in_array($role->slug, ['administrateur', 'gestionnaire-administratif', 'responsable-pedagogique'], true))
                <a class="button button-primary" href="{{ route('management.trainings.index') }}">Gérer les formations</a>
                <a class="button button-outline" href="{{ route('management.pedagogy.index') }}">Gestion pédagogique</a>
                <a class="button button-outline" href="{{ route('management.pedagogy.enrollments.index') }}">Traiter les inscriptions</a>
                <a class="button button-outline" href="{{ route('management.pedagogy.evaluations.index') }}">Gérer les évaluations</a>
                @if ($role->slug === 'administrateur' && auth()->user()->hasPermissionTo('users.view'))
                    <a class="button button-outline" href="{{ route('management.users.index') }}">Gérer les utilisateurs</a>
                @endif
                @if ($role->slug === 'administrateur' && auth()->user()->hasPermissionTo('roles.manage'))
                    <a class="button button-outline" href="{{ route('management.roles.index') }}">Rôles et permissions</a>
                @endif
            @elseif (in_array($role->slug, ['responsable-informatique', 'commercial'], true))
                <a class="button button-primary" href="{{ route('services.index') }}">Services</a>
            @endif
        </div>
        @endif

        @if ($recentEnrollments->isNotEmpty())
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">SUIVI PÉDAGOGIQUE</span><h2>{{ $role->slug === 'apprenant' ? 'Mes formations récentes' : 'Inscriptions à traiter' }}</h2></div></div>
                <div class="dashboard-list">
                    @foreach ($recentEnrollments as $enrollment)
                        <article class="dashboard-list-row">
                            <div><strong>{{ $enrollment->training?->title ?? 'Formation indisponible' }}</strong><span>{{ $role->slug === 'apprenant' ? ($enrollment->deliveryMode?->name ?? 'Modalité à confirmer') : ($enrollment->user?->name ?? 'Apprenant') }}</span></div>
                            <span class="status-pill">@switch($enrollment->status)@case('pending')En attente@break @case('confirmed')Confirmée@break @case('in_progress')En cours@break @case('completed')Terminée@break @case('cancelled')Annulée@break @default{{ $enrollment->status }}@endswitch</span>
                        </article>
                    @endforeach
                </div>
                @if ($role->slug === 'apprenant')<a class="text-link dashboard-list-link" href="{{ route('my-enrollments.index') }}">Voir mes inscriptions →</a>@endif
            </section>
        @endif

        @if ($upcomingSessions->isNotEmpty())
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">AGENDA</span><h2>{{ $upcomingSessionsLabel }}</h2></div></div>
                <div class="dashboard-list">
                    @foreach ($upcomingSessions as $session)
                        <article class="dashboard-list-row">
                            <div><strong>{{ $session->name ?: $session->training?->title }}</strong><span>{{ $session->training?->title }}{{ $session->instructor?->name ? ' · '.$session->instructor->name : '' }}{{ $session->venue ? ' · '.$session->venue : '' }}</span></div>
                            <span>{{ $session->starts_at?->format('d/m/Y H:i') }}</span>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($trainingPerformance->isNotEmpty())
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">RÉSULTATS PÉDAGOGIQUES</span><h2>Réussite par formation</h2></div></div>
                <div class="dashboard-list">
                    @foreach ($trainingPerformance as $performance)
                        <article class="dashboard-list-row">
                            <div><strong>{{ $performance['title'] }}</strong><span>{{ $performance['attempts'] }} résultat(s) noté(s)</span></div>
                            <strong>{{ $performance['success_rate'] }} %</strong>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($role->slug === 'apprenant' && $learnerFinance !== [])
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">MON SUIVI FINANCIER</span><h2>Factures et paiements</h2></div></div>
                <div class="dashboard-metrics">
                    @forelse ($learnerFinance['outstanding_totals'] as $total)
                        <article class="metric-card"><span>Montant restant dû</span><strong>{{ $total['total'] }} {{ $total['currency'] }}</strong></article>
                    @empty
                        <article class="metric-card"><span>Montant restant dû</span><strong>0</strong><small>Aucune facture en attente de règlement</small></article>
                    @endforelse
                </div>
                @if ($learnerFinance['outstanding_invoices'] !== [])
                    <div class="dashboard-list">
                        @foreach ($learnerFinance['outstanding_invoices'] as $invoice)
                            <article class="dashboard-list-row"><div><strong>Facture {{ $invoice['number'] }}</strong><span>{{ $invoice['due_on']?->format('d/m/Y') ? 'Échéance : '.$invoice['due_on']->format('d/m/Y') : 'Échéance non définie' }}</span></div><strong>{{ $invoice['amount'] }} {{ $invoice['currency'] }}</strong></article>
                        @endforeach
                    </div>
                @endif
                <div class="dashboard-section-heading"><div><span class="eyebrow">HISTORIQUE</span><h2>Mes paiements et reçus</h2></div></div>
                <div class="dashboard-list">
                    @forelse ($learnerFinance['recent_payments'] as $payment)
                        <article class="dashboard-list-row">
                            <div><strong>Facture {{ $payment['invoice_number'] }} · {{ $payment['amount'] }} {{ $payment['currency'] }}</strong><span>{{ $payment['receipt_number'] ? 'Reçu n° '.$payment['receipt_number'] : 'Aucun numéro de reçu' }} · {{ $payment['received_at']?->format('d/m/Y H:i') }}</span></div>
                            <span class="status-pill">{{ $payment['status'] === 'validated' ? 'Validé' : ($payment['status'] === 'pending' ? 'En attente' : $payment['status']) }}</span>
                        </article>
                    @empty
                        <p>Aucun paiement enregistré.</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if ($recentContacts->isNotEmpty())
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">RELATION CLIENT</span><h2>Messages récents</h2></div></div>
                <div class="dashboard-list">
                    @foreach ($recentContacts as $contact)
                        <article class="dashboard-list-row"><div><strong>{{ $contact->subject }}</strong><span>{{ $contact->name }} · {{ $contact->received_at?->format('d/m/Y') }}</span></div><span class="status-pill">{{ $contact->status === 'new' ? 'Nouveau' : 'Reçu' }}</span></article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($recentActivities->isNotEmpty())
            <section class="dashboard-table-section">
                <div class="dashboard-section-heading"><div><span class="eyebrow">JOURNAL</span><h2>Dernières activités</h2></div></div>
                <div class="dashboard-list">
                    @foreach ($recentActivities as $activity)
                        <article class="dashboard-list-row"><div><strong>{{ $activity->description ?: $activity->event }}</strong><span>{{ $activity->actor?->name ?? 'Système' }}</span></div><span>{{ $activity->created_at?->format('d/m/Y H:i') }}</span></article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($roles->count() > 1)
            <div class="switch-roles">
                <span class="eyebrow">VOS AUTRES ESPACES</span>
                <div class="switch-role-list">
                    @foreach ($roles->where('id', '!=', $role->id) as $otherRole)
                        <a href="{{ route('dashboard.roles.show', $otherRole->slug) }}">{{ $otherRole->name }} <span aria-hidden="true">→</span></a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endsection
