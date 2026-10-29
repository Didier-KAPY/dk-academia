<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Cashbox;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\ContactMessage;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\Intervention;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Progression;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quote;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\Training;
use App\Models\TrainingModule;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $roles = $request->user()->roles;

        if ($primaryRole = $roles->firstWhere('pivot.is_primary', true)) {
            return redirect()->route('dashboard.roles.show', $primaryRole);
        }

        if ($roles->count() === 1) {
            return redirect()->route('dashboard.roles.show', $roles->first());
        }

        return view($roles->isEmpty() ? 'dashboard.unassigned' : 'dashboard.select-role', [
            'roles' => $roles,
        ]);
    }

    public function show(Request $request, Role $role): View
    {
        $user = $request->user();

        abort_unless(
            $user->roles()->whereKey($role->getKey())->exists(),
            Response::HTTP_FORBIDDEN,
        );

        [$metrics, $recentEnrollments, $recentContacts, $financeOverview, $recentActivities, $trainingPerformance, $upcomingSessions, $alerts] = $this->dashboardData($user, $role->slug);
        $learnerFinance = $role->slug === 'apprenant' ? $this->learnerFinanceOverview($user) : [];

        return view('dashboard.role', [
            'role' => $role,
            'roles' => $user->roles,
            'metrics' => $metrics,
            'recentEnrollments' => $recentEnrollments,
            'recentContacts' => $recentContacts,
            'financeOverview' => $financeOverview,
            'recentActivities' => $recentActivities,
            'trainingPerformance' => $trainingPerformance,
            'upcomingSessions' => $upcomingSessions,
            'upcomingSessionsLabel' => match ($role->slug) {
                'administrateur' => 'Sessions à venir',
                'gestionnaire-administratif' => 'Planning des cours',
                'responsable-pedagogique' => 'Planning pédagogique',
                'formateur' => 'Mon calendrier',
                default => 'Mes prochaines séances',
            },
            'alerts' => $alerts,
            'learnerFinance' => $learnerFinance,
        ]);
    }

    /**
     * @return array{0: list<array{label: string, value: int, detail: string}>, 1: Collection, 2: Collection, 3: array<string, mixed>, 4: Collection, 5: Collection, 6: Collection, 7: list<array{label: string, count: int, detail: string, level: string}>}
     */
    private function dashboardData(User $user, string $role): array
    {
        $metrics = [];
        $recentEnrollments = collect();
        $recentContacts = collect();
        $financeOverview = [];
        $recentActivities = collect();
        $trainingPerformance = collect();
        $upcomingSessions = collect();
        $alerts = [];

        if ($role === 'administrateur') {
            $financeOverview = $this->financeOverview();
            $trainingPerformance = $this->trainingPerformance();
            $metrics = [
                $this->metric('Utilisateurs', User::query()->count(), 'Comptes sur la plateforme'),
                $this->metric('Apprenants', User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'apprenant'))->count(), 'Profils apprenants'),
                $this->metric('Formateurs', User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'formateur'))->count(), 'Profils formateurs'),
                $this->metric('Personnel', User::query()->whereHas('roles', fn ($query) => $query->whereNotIn('slug', ['apprenant']))->distinct('users.id')->count('users.id'), 'Utilisateurs avec un rôle opérationnel'),
                $this->metric('Nouvelles inscriptions ce mois', Enrollment::query()->whereBetween('registered_at', [now()->startOfMonth(), now()])->count(), 'Toutes les demandes reçues'),
                $this->metric('Formations actives', Training::query()->where('is_published', true)->count(), 'Publiées au catalogue'),
                $this->metric('Sessions à venir', TrainingSession::query()->where('status', 'scheduled')->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()))->count(), 'Programmées'),
                $this->metric('Inscriptions à traiter', Enrollment::query()->where('status', 'pending')->count(), 'Demandes en attente'),
                $this->metric('Messages reçus', ContactMessage::query()->where('status', 'new')->count(), 'Demandes non traitées'),
                $this->metric('Projets en cours', Project::query()->whereIn('status', ['in_progress', 'active'])->count(), 'Prestations informatiques'),
                $this->metric('Paiements à valider', Payment::query()->where('status', 'pending')->count(), 'Aucun montant compté avant validation'),
                $this->metric('Factures à suivre', Invoice::query()->whereIn('status', ['issued', 'partially_paid', 'overdue'])->count(), 'Factures émises ou impayées'),
                $this->metric('Absences ce mois', AttendanceRecord::query()->where('status', 'absent')->whereDate('attendance_date', '>=', now()->startOfMonth())->whereDate('attendance_date', '<=', now())->count(), 'Présences enregistrées'),
            ];
            $recentEnrollments = Enrollment::query()->with(['user:id,name', 'training:id,title'])->where('status', 'pending')->latest('registered_at')->limit(5)->get();
            $recentContacts = ContactMessage::query()->latest('received_at')->limit(5)->get(['id', 'name', 'subject', 'status', 'received_at']);
            $recentActivities = ActivityLog::query()->with('actor:id,name')->latest('id')->limit(8)->get(['id', 'user_id', 'event', 'description', 'created_at']);
            $upcomingSessions = TrainingSession::query()->with(['training:id,title', 'instructor:id,name'])
                ->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)
                ->get(['id', 'training_id', 'instructor_id', 'name', 'starts_at', 'venue']);
            $alerts = array_values(array_filter([
                $this->alert('Inscriptions à traiter', Enrollment::query()->where('status', 'pending')->count(), 'Demandes en attente de décision'),
                $this->alert('Paiements à valider', Payment::query()->where('status', 'pending')->count(), 'À vérifier avant comptabilisation'),
                $this->alert('Factures échues', Invoice::query()->where('status', 'overdue')->count(), 'Factures dont l’échéance est dépassée'),
                $this->alert('Nouveaux messages', ContactMessage::query()->where('status', 'new')->count(), 'Demandes de contact sans traitement'),
            ]));
        } elseif ($role === 'gestionnaire-administratif') {
            $metrics = [
                $this->metric('Inscriptions à traiter', Enrollment::query()->where('status', 'pending')->count(), 'Demandes en attente'),
                $this->metric('Apprenants', User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'apprenant'))->count(), 'Profils apprenants'),
                $this->metric('Formations actives', Training::query()->where('is_published', true)->count(), 'Publiées au catalogue'),
                $this->metric('Sessions à venir', TrainingSession::query()->where('status', 'scheduled')->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()))->count(), 'Programmées'),
                $this->metric('Messages reçus', ContactMessage::query()->where('status', 'new')->count(), 'Demandes non traitées'),
            ];
            $recentEnrollments = Enrollment::query()->with(['user:id,name', 'training:id,title'])->where('status', 'pending')->latest('registered_at')->limit(8)->get();
            $recentContacts = ContactMessage::query()->latest('received_at')->limit(5)->get(['id', 'name', 'subject', 'status', 'received_at']);
            $upcomingSessions = TrainingSession::query()->with(['training:id,title', 'instructor:id,name'])
                ->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)
                ->get(['id', 'training_id', 'instructor_id', 'name', 'starts_at', 'venue']);
            $alerts = array_values(array_filter([
                $this->alert('Inscriptions à traiter', Enrollment::query()->where('status', 'pending')->count(), 'Demandes en attente de décision'),
                $this->alert('Nouveaux messages', ContactMessage::query()->where('status', 'new')->count(), 'Demandes de contact sans traitement'),
            ]));
        } elseif ($role === 'comptable') {
            $financeOverview = $this->financeOverview();
            $metrics = [
                $this->metric('Factures émises', Invoice::query()->whereIn('status', ['issued', 'partially_paid', 'overdue'])->count(), 'À suivre'),
                $this->metric('Paiements en attente', Payment::query()->where('status', 'pending')->count(), 'À valider'),
                $this->metric('Paiements validés', Payment::query()->where('status', 'validated')->count(), 'Enregistrés'),
                $this->metric('Opérations à valider', CashMovement::query()->where('status', 'pending')->count(), 'Mouvements de caisse'),
                $this->metric('Caisses actives', Cashbox::query()->where('is_active', true)->count(), 'Comptes de caisse'),
            ];
        } elseif ($role === 'responsable-caisse') {
            $financeOverview = $this->financeOverview($user);
            $mySessions = CashSession::query()->where('cashier_id', $user->id);
            $metrics = [
                $this->metric('Mes caisses ouvertes', (clone $mySessions)->where('status', 'open')->count(), 'Sessions en cours'),
                $this->metric('Clôtures avec écart', (clone $mySessions)->where('status', 'closed')->whereNotNull('discrepancy')->where('discrepancy', '!=', 0)->count(), 'À expliquer ou vérifier'),
                $this->metric('Mes opérations à valider', CashMovement::query()->where('status', 'pending')->whereHas('cashSession', fn ($query) => $query->where('cashier_id', $user->id))->count(), 'Mouvements enregistrés'),
                $this->metric('Mes opérations validées', CashMovement::query()->where('status', 'validated')->whereHas('cashSession', fn ($query) => $query->where('cashier_id', $user->id))->count(), 'Sur vos sessions'),
            ];
            $alerts = array_values(array_filter([
                $this->alert('Opérations à valider', (clone $mySessions)->join('cash_movements', 'cash_movements.cash_session_id', '=', 'cash_sessions.id')->where('cash_movements.status', 'pending')->count(), 'Mouvements de vos sessions'),
                $this->alert('Clôtures avec écart', (clone $mySessions)->where('status', 'closed')->whereNotNull('discrepancy')->where('discrepancy', '!=', 0)->count(), 'Écarts à expliquer ou vérifier'),
            ]));
        } elseif ($role === 'responsable-pedagogique') {
            $trainingPerformance = $this->trainingPerformance();
            $gradedAttempts = $this->gradedAttempts();
            $gradedAttemptCount = (clone $gradedAttempts)->count();
            $passedAttemptCount = (clone $gradedAttempts)->whereHas('evaluation', fn (Builder $query): Builder => $query
                ->whereColumn('evaluation_attempts.score', '>=', 'evaluations.passing_points'))
                ->count();
            $metrics = [
                $this->metric('Formations actives', Training::query()->where('is_published', true)->count(), 'Publiées au catalogue'),
                $this->metric('Inscriptions à traiter', Enrollment::query()->where('status', 'pending')->count(), 'Demandes en attente'),
                $this->metric('Apprenants inscrits', Enrollment::query()->whereIn('status', ['confirmed', 'in_progress'])->distinct('user_id')->count('user_id'), 'Inscriptions confirmées ou en cours'),
                $this->metric('Sessions à venir', TrainingSession::query()->where('status', 'scheduled')->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()))->count(), 'Programmées'),
                $this->metric('Absences ce mois', AttendanceRecord::query()->where('status', 'absent')->whereDate('attendance_date', '>=', now()->startOfMonth())->whereDate('attendance_date', '<=', now())->count(), 'Présences enregistrées'),
                $this->metric('Évaluations publiées', Evaluation::query()->where('is_published', true)->count(), 'Évaluations actives'),
                $this->metric('Taux de réussite', $gradedAttemptCount === 0 ? 0 : (int) round($passedAttemptCount * 100 / $gradedAttemptCount), $gradedAttemptCount === 0 ? 'Aucun résultat noté avec seuil' : 'Sur les résultats notés avec seuil, en %'),
            ];
            $recentEnrollments = Enrollment::query()->with(['user:id,name', 'training:id,title'])->where('status', 'pending')->latest('registered_at')->limit(8)->get();
            $upcomingSessions = TrainingSession::query()->with(['training:id,title', 'instructor:id,name'])
                ->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)
                ->get(['id', 'training_id', 'instructor_id', 'name', 'starts_at', 'venue']);
            $sessionsToOrganize = TrainingSession::query()
                ->where('status', 'scheduled')
                ->whereNull('instructor_id')
                ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->addDays(7)))
                ->count();
            $alerts = array_values(array_filter([
                $this->alert('Inscriptions à traiter', Enrollment::query()->where('status', 'pending')->count(), 'Demandes en attente de décision'),
                $this->alert('Sessions à organiser', $sessionsToOrganize, 'Sessions proches sans formateur attribué'),
                $this->alert('Corrections en attente', EvaluationAttempt::query()->where('status', 'submitted')->whereNull('score')->count(), 'Réponses à corriger manuellement'),
            ]));
        } elseif ($role === 'formateur') {
            $trainingPerformance = $this->trainingPerformance($user);
            $sessionIds = TrainingSession::query()->where('instructor_id', $user->id)->select('id');
            $trainerTrainingIds = TrainingSession::query()->where('instructor_id', $user->id)->select('training_id')->distinct();
            $upcomingSessions = TrainingSession::query()->with(['training:id,title', 'instructor:id,name'])
                ->where('instructor_id', $user->id)->where('status', 'scheduled')->where('starts_at', '>=', now())
                ->orderBy('starts_at')->limit(5)
                ->get(['id', 'training_id', 'instructor_id', 'name', 'starts_at', 'venue']);
            $gradedAttempts = $this->gradedAttempts()->whereHas('evaluation', fn (Builder $query): Builder => $query
                ->where(fn (Builder $scope): Builder => $scope
                    ->where('created_by', $user->id)
                    ->orWhereIn('training_session_id', $sessionIds)));
            $gradedAttemptCount = (clone $gradedAttempts)->count();
            $passedAttemptCount = (clone $gradedAttempts)->whereHas('evaluation', fn (Builder $query): Builder => $query
                ->whereColumn('evaluation_attempts.score', '>=', 'evaluations.passing_points'))
                ->count();
            $metrics = [
                $this->metric('Mes formations', Training::query()->whereIn('id', $trainerTrainingIds)->count(), 'Formations de mes sessions'),
                $this->metric('Mes modules', TrainingModule::query()->whereIn('training_id', $trainerTrainingIds)->count(), 'Modules de mes formations'),
                $this->metric('Mes leçons', Lesson::query()->whereHas('module', fn (Builder $query): Builder => $query->whereIn('training_id', $trainerTrainingIds))->count(), 'Leçons de mes formations'),
                $this->metric('Mes sessions à venir', TrainingSession::query()->where('instructor_id', $user->id)->where('status', 'scheduled')->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()))->count(), 'Cours programmés'),
                $this->metric('Mes apprenants', Enrollment::query()->whereIn('training_session_id', $sessionIds)->whereIn('status', ['confirmed', 'in_progress', 'completed'])->distinct('user_id')->count('user_id'), 'Inscrits à mes sessions'),
                $this->metric('Mes absences ce mois', AttendanceRecord::query()->whereIn('training_session_id', $sessionIds)->where('status', 'absent')->whereDate('attendance_date', '>=', now()->startOfMonth())->whereDate('attendance_date', '<=', now())->count(), 'Présences de mes sessions'),
                $this->metric('Évaluations à corriger', EvaluationAttempt::query()->where('status', 'submitted')->whereNull('score')->whereHas('evaluation', fn (Builder $query): Builder => $query
                    ->where(fn (Builder $scope): Builder => $scope
                        ->where('created_by', $user->id)
                        ->orWhereIn('training_session_id', $sessionIds)))->count(), 'Réponses en attente de correction'),
                $this->metric('Taux de réussite', $gradedAttemptCount === 0 ? 0 : (int) round($passedAttemptCount * 100 / $gradedAttemptCount), $gradedAttemptCount === 0 ? 'Aucun résultat noté avec seuil' : 'Sur mes résultats notés avec seuil, en %'),
                $this->metric('Mes évaluations', Evaluation::query()->where('created_by', $user->id)->count(), 'Créées par vous'),
            ];
            $ungradedAttempts = EvaluationAttempt::query()->where('status', 'submitted')->whereNull('score')->whereHas('evaluation', fn (Builder $query): Builder => $query
                ->where(fn (Builder $scope): Builder => $scope
                    ->where('created_by', $user->id)
                    ->orWhereIn('training_session_id', $sessionIds)))->count();
            $alerts = array_values(array_filter([
                $this->alert('Évaluations à corriger', $ungradedAttempts, 'Réponses de vos évaluations'),
                $this->alert('Séances en retard de clôture', TrainingSession::query()->where('instructor_id', $user->id)->where('status', 'scheduled')->where('ends_at', '<', now())->count(), 'Séances dont la date de fin est passée'),
            ]));
        } elseif ($role === 'apprenant') {
            $enrolledTrainingIds = $user->enrollments()
                ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
                ->select('training_id');
            $averageProgress = Progression::query()
                ->where('user_id', $user->id)
                ->whereHas('lesson.module', fn (Builder $query): Builder => $query->whereIn('training_id', $enrolledTrainingIds))
                ->avg('completion_percent');
            $upcomingSessionCount = $user->enrollments()
                ->whereIn('status', ['confirmed', 'in_progress'])
                ->whereHas('session', fn (Builder $query): Builder => $query
                    ->where('status', 'scheduled')
                    ->whereNotNull('starts_at')
                    ->where('starts_at', '>=', now()))
                ->count();
            $upcomingSessions = TrainingSession::query()
                ->with('training:id,title')
                ->where('status', 'scheduled')
                ->where('starts_at', '>=', now())
                ->whereHas('enrollments', fn (Builder $query): Builder => $query
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['confirmed', 'in_progress']))
                ->orderBy('starts_at')
                ->limit(5)
                ->get(['id', 'training_id', 'name', 'starts_at', 'venue']);
            $metrics = [
                $this->metric('Mes inscriptions', $user->enrollments()->count(), 'Toutes les demandes'),
                $this->metric('En cours', $user->enrollments()->where('status', 'in_progress')->count(), 'Formations commencées'),
                $this->metric('Leçons suivies', $user->progressions()->count(), 'Votre progression'),
                $this->metric('Progression moyenne', $averageProgress === null ? 0 : (int) round((float) $averageProgress), $averageProgress === null ? 'Aucune progression enregistrée' : 'Pourcentage sur vos formations actives'),
                $this->metric('Mes prochaines séances', $upcomingSessionCount, 'Sessions planifiées à venir'),
                $this->metric('Certificats', $user->certificates()->whereNull('revoked_at')->count(), 'Attestations disponibles'),
            ];
            $recentEnrollments = $user->enrollments()->with(['training:id,title', 'deliveryMode:id,name'])->latest('registered_at')->limit(5)->get();
        } elseif ($role === 'responsable-informatique') {
            $overdueTasks = ProjectTask::query()->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count();
            $metrics = [
                $this->metric('Projets en cours', Project::query()->whereIn('status', ['in_progress', 'active'])->count(), 'Prestations informatiques'),
                $this->metric('Projets terminés', Project::query()->where('status', 'completed')->count(), 'Prestations livrées'),
                $this->metric('Tickets ouverts', SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count(), 'Demandes de support'),
                $this->metric('Interventions à venir', Intervention::query()->where('status', 'scheduled')->where('scheduled_at', '>=', now())->count(), 'Planning technique'),
                $this->metric('Tâches en retard', $overdueTasks, 'À suivre'),
                $this->metric('Échéances sous 7 jours', ProjectTask::query()->whereNotIn('status', ['completed', 'cancelled'])->whereBetween('due_at', [now(), now()->addDays(7)])->count(), 'Tâches actives à planifier'),
                $this->metric('Devis à traiter', Quote::query()->where('status', 'sent')->count(), 'Propositions transmises'),
            ];
            $alerts = array_values(array_filter([
                $this->alert('Tâches en retard', $overdueTasks, 'Échéances dépassées'),
                $this->alert('Tickets urgents', SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->whereIn('priority', ['high', 'urgent'])->count(), 'Demandes de support prioritaires'),
            ]));
        } elseif ($role === 'commercial') {
            $monthlyEnrollments = Enrollment::query()->whereBetween('registered_at', [now()->startOfMonth(), now()]);
            $monthlyEnrollmentCount = (clone $monthlyEnrollments)->count();
            $convertedEnrollmentCount = (clone $monthlyEnrollments)->whereIn('status', ['confirmed', 'in_progress', 'completed'])->count();
            $metrics = [
                $this->metric('Clients', Client::query()->count(), 'Fiches enregistrées'),
                $this->metric('Devis à suivre', Quote::query()->whereIn('status', ['sent', 'accepted'])->count(), 'Devis transmis'),
                $this->metric('Demandes de contact', ContactMessage::query()->where('status', 'new')->count(), 'Nouveaux prospects'),
                $this->metric('Inscriptions confirmées ce mois', $convertedEnrollmentCount, 'Demandes passées au suivi pédagogique'),
                $this->metric('Conversion des inscriptions', $monthlyEnrollmentCount === 0 ? 0 : (int) round($convertedEnrollmentCount * 100 / $monthlyEnrollmentCount), $monthlyEnrollmentCount === 0 ? 'Aucune inscription ce mois' : 'Demandes confirmées ou en cours, en %'),
                $this->metric('Projets actifs', Project::query()->whereIn('status', ['in_progress', 'active'])->count(), 'Prestations suivies'),
            ];
            $recentContacts = ContactMessage::query()->latest('received_at')->limit(8)->get(['id', 'name', 'subject', 'status', 'received_at']);
            $recentEnrollments = Enrollment::query()->with(['user:id,name', 'training:id,title'])
                ->where('status', 'pending')->latest('registered_at')->limit(8)->get();
            $alerts = array_values(array_filter([
                $this->alert('Demandes de contact', ContactMessage::query()->where('status', 'new')->count(), 'Prospects à recontacter'),
                $this->alert('Inscriptions à suivre', Enrollment::query()->where('status', 'pending')->count(), 'Demandes en attente de décision'),
                $this->alert('Devis arrivés à échéance', Quote::query()->where('status', 'sent')->where('valid_until', '<', now()->toDateString())->count(), 'Propositions à relancer ou clôturer'),
            ]));
        }

        return [$metrics, $recentEnrollments, $recentContacts, $financeOverview, $recentActivities, $trainingPerformance, $upcomingSessions, $alerts];
    }

    /** @return array{label: string, count: int, detail: string, level: string}|null */
    private function alert(string $label, int $count, string $detail, string $level = 'warning'): ?array
    {
        return $count > 0 ? compact('label', 'count', 'detail', 'level') : null;
    }

    /** @return Collection<int, array{title: string, attempts: int, success_rate: int}> */
    private function trainingPerformance(?User $instructor = null): Collection
    {
        return DB::table('evaluation_attempts')
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_attempts.evaluation_id')
            ->leftJoin('training_sessions', 'training_sessions.id', '=', 'evaluations.training_session_id')
            ->join('trainings', 'trainings.id', '=', DB::raw('COALESCE(evaluations.training_id, training_sessions.training_id)'))
            ->where('evaluation_attempts.status', 'submitted')
            ->whereNotNull('evaluation_attempts.score')
            ->whereNotNull('evaluations.passing_points')
            ->when($instructor !== null, fn ($query) => $query->where(function ($scope) use ($instructor): void {
                $scope->where('evaluations.created_by', $instructor->id)
                    ->orWhere('training_sessions.instructor_id', $instructor->id);
            }))
            ->select([
                'trainings.id',
                'trainings.title',
            ])
            ->selectRaw('COUNT(evaluation_attempts.id) AS attempts')
            ->selectRaw('SUM(CASE WHEN evaluation_attempts.score >= evaluations.passing_points THEN 1 ELSE 0 END) AS passed_attempts')
            ->groupBy('trainings.id', 'trainings.title')
            ->orderByDesc('attempts')
            ->orderBy('trainings.title')
            ->limit(8)
            ->get()
            ->map(fn (object $result): array => [
                'title' => $result->title,
                'attempts' => (int) $result->attempts,
                'success_rate' => (int) round(((int) $result->passed_attempts) * 100 / (int) $result->attempts),
            ]);
    }

    /**
     * @return array{
     *     outstanding_invoices: list<array{number: string, amount: string, currency: string, due_on: mixed}>,
     *     outstanding_totals: list<array{currency: string, total: string}>,
     *     recent_payments: list<array{invoice_number: string, receipt_number: ?string, amount: string, currency: string, status: string, received_at: mixed}>
     * }
     */
    private function learnerFinanceOverview(User $learner): array
    {
        $invoices = Invoice::query()
            ->select(['invoices.id', 'invoices.number', 'invoices.currency', 'invoices.total_amount', 'invoices.due_on'])
            ->whereIn('invoices.status', ['issued', 'partially_paid', 'overdue'])
            ->whereHas('enrollment', fn (Builder $query): Builder => $query->where('user_id', $learner->id))
            ->selectSub(
                Payment::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('invoice_id', 'invoices.id')
                    ->whereColumn('currency', 'invoices.currency')
                    ->where('status', 'validated'),
                'validated_paid_total',
            )
            ->get()
            ->map(function (Invoice $invoice): ?array {
                $remainingAmount = $this->toMinorUnits((string) $invoice->total_amount)
                    - $this->toMinorUnits((string) $invoice->validated_paid_total);

                if ($remainingAmount <= 0) {
                    return null;
                }

                return [
                    'number' => $invoice->number,
                    'amount' => $this->formatMinorUnits($remainingAmount),
                    'currency' => $invoice->currency,
                    'due_on' => $invoice->due_on,
                ];
            })
            ->filter()
            ->values();

        $outstandingTotals = $invoices
            ->groupBy('currency')
            ->map(fn (Collection $currencyInvoices, string $currency): array => [
                'currency' => $currency,
                'total' => $this->formatMinorUnits(array_sum(array_map(
                    fn (array $invoice): int => $this->toMinorUnits($invoice['amount']),
                    $currencyInvoices->all(),
                ))),
            ])
            ->sortBy('currency')
            ->values()
            ->all();

        $recentPayments = Payment::query()
            ->with('invoice:id,number,enrollment_id')
            ->whereHas('invoice.enrollment', fn (Builder $query): Builder => $query->where('user_id', $learner->id))
            ->whereNotNull('received_at')
            ->latest('received_at')
            ->limit(5)
            ->get(['id', 'invoice_id', 'receipt_number', 'amount', 'currency', 'status', 'received_at'])
            ->map(fn (Payment $payment): array => [
                'invoice_number' => $payment->invoice->number,
                'receipt_number' => $payment->receipt_number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'received_at' => $payment->received_at,
            ])
            ->all();

        return [
            'outstanding_invoices' => $invoices->all(),
            'outstanding_totals' => $outstandingTotals,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * @return array{
     *     daily_receipts: list<array{currency: string, total: string}>,
     *     monthly_receipts: list<array{currency: string, total: string}>,
     *     daily_expenses: list<array{currency: string, total: string}>,
     *     monthly_expenses: list<array{currency: string, total: string}>,
     *     outstanding_invoices: list<array{currency: string, total: string}>,
     *     open_cash_sessions: list<array{name: string, currency: string, balance: ?string, cashier: string, inflows: string, outflows: string}>,
     *     recent_cash_movements: list<array{cashbox: string, description: string, type: string, status: string, amount: string, currency: string, occurred_at: mixed}>
     * }
     */
    private function financeOverview(?User $cashier = null): array
    {
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $todayEnd = now();

        $payments = Payment::query()->where('status', 'validated')->whereNotNull('received_at');

        if ($cashier !== null) {
            $payments->whereHas('cashMovement.cashSession', fn (Builder $query): Builder => $query->where('cashier_id', $cashier->id));
        }

        $movements = CashMovement::query()
            ->where('status', 'validated')
            ->where('type', CashMovement::TYPE_EXPENSE)
            ->whereBetween('occurred_at', [$monthStart, $todayEnd]);

        if ($cashier !== null) {
            $movements->whereHas('cashSession', fn (Builder $query): Builder => $query->where('cashier_id', $cashier->id));
        }

        $cashSessions = CashSession::query()
            ->with(['cashbox:id,name,currency', 'cashier:id,name'])
            ->withSum([
                'movements as validated_inflows' => fn (Builder $query): Builder => $query
                    ->where('status', 'validated')
                    ->whereIn('type', [CashMovement::TYPE_INCOME, CashMovement::TYPE_TRANSFER_IN]),
                'movements as validated_outflows' => fn (Builder $query): Builder => $query
                    ->where('status', 'validated')
                    ->whereIn('type', [CashMovement::TYPE_EXPENSE, CashMovement::TYPE_TRANSFER_OUT]),
            ], 'amount')
            ->withCount([
                'movements as unclassified_validated_movements_count' => fn (Builder $query): Builder => $query
                    ->where('status', 'validated')
                    ->whereNotIn('type', [
                        CashMovement::TYPE_INCOME,
                        CashMovement::TYPE_EXPENSE,
                        CashMovement::TYPE_TRANSFER_IN,
                        CashMovement::TYPE_TRANSFER_OUT,
                    ]),
                'movements as mismatched_currency_movements_count' => fn (Builder $query): Builder => $query
                    ->where('status', 'validated')
                    ->whereHas('cashSession.cashbox', fn (Builder $cashboxQuery): Builder => $cashboxQuery
                        ->whereColumn('cashboxes.currency', '!=', 'cash_movements.currency')),
            ])
            ->where('status', 'open')
            ->when($cashier !== null, fn (Builder $query): Builder => $query->where('cashier_id', $cashier->id))
            ->orderBy('opened_at')
            ->get();

        $recentCashMovements = CashMovement::query()
            ->with(['cashSession.cashbox:id,name,currency'])
            ->when($cashier !== null, fn (Builder $query): Builder => $query
                ->whereHas('cashSession', fn (Builder $sessionQuery): Builder => $sessionQuery->where('cashier_id', $cashier->id)))
            ->latest('occurred_at')
            ->limit(8)
            ->get(['id', 'cash_session_id', 'type', 'status', 'description', 'amount', 'currency', 'occurred_at']);

        $outstandingInvoices = [];

        if ($cashier === null) {
            $invoiceBalances = Invoice::query()
                ->select(['invoices.id', 'invoices.currency', 'invoices.total_amount'])
                ->whereIn('invoices.status', ['issued', 'partially_paid', 'overdue'])
                ->selectSub(
                    Payment::query()
                        ->selectRaw('COALESCE(SUM(amount), 0)')
                        ->whereColumn('invoice_id', 'invoices.id')
                        ->whereColumn('currency', 'invoices.currency')
                        ->where('status', 'validated'),
                    'validated_paid_total',
                );

            $outstandingInvoices = DB::query()
                ->fromSub($invoiceBalances, 'invoice_balances')
                ->select('currency')
                ->selectRaw('SUM(CASE WHEN total_amount > validated_paid_total THEN total_amount - validated_paid_total ELSE 0 END) AS total')
                ->groupBy('currency')
                ->orderBy('currency')
                ->get()
                ->map(fn (object $total): array => [
                    'currency' => $total->currency,
                    'total' => $this->formatMinorUnits($this->toMinorUnits((string) $total->total)),
                ])
                ->all();
        }

        return [
            'daily_receipts' => $this->sumByCurrency((clone $payments)->whereBetween('received_at', [$todayStart, $todayEnd])),
            'monthly_receipts' => $this->sumByCurrency((clone $payments)->whereBetween('received_at', [$monthStart, $todayEnd])),
            'daily_expenses' => $this->sumByCurrency((clone $movements)->whereBetween('occurred_at', [$todayStart, $todayEnd])),
            'monthly_expenses' => $this->sumByCurrency($movements),
            'outstanding_invoices' => $outstandingInvoices,
            'open_cash_sessions' => $cashSessions->map(function (CashSession $session): array {
                $balance = null;

                if ($session->unclassified_validated_movements_count === 0 && $session->mismatched_currency_movements_count === 0) {
                    $balanceInCents = $this->toMinorUnits((string) $session->opening_balance)
                        + $this->toMinorUnits((string) ($session->validated_inflows ?? '0.00'))
                        - $this->toMinorUnits((string) ($session->validated_outflows ?? '0.00'));
                    $balance = $this->formatMinorUnits($balanceInCents);
                }

                return [
                    'name' => $session->cashbox->name,
                    'currency' => $session->cashbox->currency,
                    'balance' => $balance,
                    'cashier' => $session->cashier->name,
                    'inflows' => $this->formatMinorUnits($this->toMinorUnits((string) ($session->validated_inflows ?? '0.00'))),
                    'outflows' => $this->formatMinorUnits($this->toMinorUnits((string) ($session->validated_outflows ?? '0.00'))),
                ];
            })->all(),
            'recent_cash_movements' => $recentCashMovements->map(fn (CashMovement $movement): array => [
                'cashbox' => $movement->cashSession->cashbox->name,
                'description' => $movement->description,
                'type' => $movement->type,
                'status' => $movement->status,
                'amount' => $movement->amount,
                'currency' => $movement->currency,
                'occurred_at' => $movement->occurred_at,
            ])->all(),
        ];
    }

    /** @return list<array{currency: string, total: string}> */
    private function sumByCurrency(Builder $query): array
    {
        return $query
            ->selectRaw('currency, SUM(amount) AS total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (object $total): array => [
                'currency' => $total->currency,
                'total' => $this->formatMinorUnits($this->toMinorUnits((string) $total->total)),
            ])
            ->all();
    }

    private function toMinorUnits(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $normalizedAmount = ltrim($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalizedAmount, 2), 2, '0');
        $minorUnits = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$minorUnits : $minorUnits;
    }

    /** @return Builder<EvaluationAttempt> */
    private function gradedAttempts(): Builder
    {
        return EvaluationAttempt::query()
            ->where('status', 'submitted')
            ->whereNotNull('score')
            ->whereHas('evaluation', fn (Builder $query): Builder => $query->whereNotNull('passing_points'));
    }

    private function formatMinorUnits(int $amount): string
    {
        $absoluteAmount = abs($amount);
        $formattedAmount = number_format(intdiv($absoluteAmount, 100), 0, ',', ' ')
            .'.'.str_pad((string) ($absoluteAmount % 100), 2, '0', STR_PAD_LEFT);

        return $amount < 0 ? '-'.$formattedAmount : $formattedAmount;
    }

    /** @return array{label: string, value: int, detail: string} */
    private function metric(string $label, int $value, string $detail): array
    {
        return compact('label', 'value', 'detail');
    }
}
