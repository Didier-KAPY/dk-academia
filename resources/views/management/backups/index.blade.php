@extends('layouts.app')

@section('title', 'Sauvegardes')

@section('content')
    <section class="dashboard-wrap">
        <div class="dashboard-heading">
            <div class="welcome-block">
                <span class="eyebrow">ADMINISTRATION</span>
                <h1>Sauvegardes</h1>
                <p>Créez une copie de la base MySQL et des documents conservés par l’application.</p>
            </div>
        </div>

        <div class="dashboard-panel backup-create-panel">
            <div>
                <strong>Créer et vérifier une sauvegarde</strong>
                <p>Les fichiers restent dans le stockage privé du serveur. La base SQL et l’archive documentaire sont contrôlées avant leur mise à disposition.</p>
            </div>
            <form method="POST" action="{{ route('management.backups.store') }}">
                @csrf
                <button class="button button-primary" type="submit">Lancer la sauvegarde</button>
            </form>
        </div>

        <section class="dashboard-table-section">
            <div class="dashboard-section-heading"><div><span class="eyebrow">HISTORIQUE</span><h2>Opérations récentes</h2></div></div>
            <div class="backup-list">
                @forelse ($backups as $backup)
                    <article class="backup-card">
                        <div class="backup-card-heading">
                            <div>
                                <strong>Sauvegarde du {{ $backup->created_at?->format('d/m/Y à H:i') }}</strong>
                                <span>Par {{ $backup->creator?->name ?? 'Système' }}</span>
                            </div>
                            <span class="status-pill {{ $backup->status === 'verified' ? 'backup-status-verified' : ($backup->status === 'failed' ? 'backup-status-failed' : '') }}">
                                {{ $backup->status === 'verified' ? 'Vérifiée' : ($backup->status === 'failed' ? 'Échec' : 'En cours') }}
                            </span>
                        </div>

                        @if ($backup->status === 'verified')
                            <div class="backup-files">
                                <div>
                                    <span>Base de données · {{ number_format($backup->database_size / 1048576, 2, ',', ' ') }} Mo</span>
                                    <a class="text-link" href="{{ route('management.backups.database.download', $backup) }}">Télécharger le fichier SQL</a>
                                </div>
                                <div>
                                    <span>Documents · {{ number_format(($backup->documents_size ?? 0) / 1048576, 2, ',', ' ') }} Mo</span>
                                    <a class="text-link" href="{{ route('management.backups.documents.download', $backup) }}">Télécharger l’archive</a>
                                </div>
                            </div>
                            <small class="backup-verified-at">Empreintes SHA-256 contrôlées le {{ $backup->verified_at?->format('d/m/Y à H:i') }}</small>
                        @elseif ($backup->error_message)
                            <p class="field-error">{{ $backup->error_message }}</p>
                        @endif
                    </article>
                @empty
                    <div class="empty-state"><h2>Aucune sauvegarde pour le moment</h2><p>La première sauvegarde enregistrera la base et les documents locaux de l’application.</p></div>
                @endforelse
            </div>
            {{ $backups->links() }}
        </section>
    </section>
@endsection
