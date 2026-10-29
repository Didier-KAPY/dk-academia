<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Cashbox;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Progression;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Role;
use App\Models\Training;
use App\Models\TrainingModule;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_dashboard_summarizes_validated_operations_per_currency(): void
    {
        $this->freezeTime();
        $accountant = User::factory()->create();
        $accountantRole = Role::create(['name' => 'Comptable', 'slug' => 'comptable']);
        $accountant->roles()->attach($accountantRole, ['is_primary' => true]);
        $cashier = User::factory()->create();
        $cashbox = Cashbox::create(['name' => 'Caisse dollars', 'code' => 'DASH-USD', 'currency' => 'USD']);
        $cdfCashbox = Cashbox::create(['name' => 'Caisse francs', 'code' => 'DASH-CDF', 'currency' => 'CDF']);
        $cashSession = CashSession::create([
            'cashbox_id' => $cashbox->id,
            'cashier_id' => $cashier->id,
            'status' => 'open',
            'opening_balance' => '100.00',
            'opened_at' => now(),
        ]);
        $cdfCashSession = $this->openSession($cdfCashbox, $cashier);

        $this->payment($cashier, 'INVOICE-USD-TODAY', 'USD', '100.00', 'validated', now());
        $this->payment($cashier, 'INVOICE-CDF-TODAY', 'CDF', '50.00', 'validated', now());
        $this->payment($cashier, 'INVOICE-USD-OLDER', 'USD', '7.00', 'validated', now()->subDay());
        $this->payment($cashier, 'INVOICE-USD-PENDING', 'USD', '900.00', 'pending', now());

        $this->movement($cashSession, $cashier, CashMovement::TYPE_INCOME, '20.00', now());
        $this->movement($cashSession, $cashier, CashMovement::TYPE_EXPENSE, '4.00', now());
        $this->movement($cashSession, $cashier, CashMovement::TYPE_TRANSFER_OUT, '5.00', now());
        $this->movement($cashSession, $cashier, CashMovement::TYPE_TRANSFER_IN, '3.00', now());
        $this->movement($cdfCashSession, $cashier, CashMovement::TYPE_EXPENSE, '10.00', now()->subDay());
        $this->movement($cashSession, $cashier, CashMovement::TYPE_EXPENSE, '400.00', now(), 'pending');

        $usdInvoice = Invoice::create([
            'number' => 'OUTSTANDING-USD',
            'currency' => 'USD',
            'total_amount' => '200.00',
            'status' => 'partially_paid',
        ]);
        $cdfInvoice = Invoice::create([
            'number' => 'OUTSTANDING-CDF',
            'currency' => 'CDF',
            'total_amount' => '500.00',
            'status' => 'issued',
        ]);
        $this->recordPayment($usdInvoice, $cashier, '150.00', 'USD', 'validated');
        $this->recordPayment($usdInvoice, $cashier, '25.00', 'USD', 'pending');
        $this->recordPayment($usdInvoice, $cashier, '40.00', 'CDF', 'validated');
        $this->recordPayment($cdfInvoice, $cashier, '400.00', 'CDF', 'validated');

        $this->actingAs($accountant)
            ->get(route('dashboard.roles.show', $accountantRole->slug))
            ->assertOk()
            ->assertViewHas('financeOverview', function (array $overview): bool {
                $dailyReceipts = collect($overview['daily_receipts'])->keyBy('currency');
                $monthlyReceipts = collect($overview['monthly_receipts'])->keyBy('currency');
                $dailyExpenses = collect($overview['daily_expenses'])->keyBy('currency');
                $monthlyExpenses = collect($overview['monthly_expenses'])->keyBy('currency');
                $outstandingInvoices = collect($overview['outstanding_invoices'])->keyBy('currency');

                $this->assertSame('250.00', $dailyReceipts['USD']['total'] ?? null);
                $this->assertSame('490.00', $dailyReceipts['CDF']['total'] ?? null);
                $this->assertSame('257.00', $monthlyReceipts['USD']['total'] ?? null);
                $this->assertSame('4.00', $dailyExpenses['USD']['total'] ?? null);
                $this->assertArrayNotHasKey('CDF', $dailyExpenses->all());
                $this->assertSame('10.00', $monthlyExpenses['CDF']['total'] ?? null);
                $this->assertSame('50.00', $outstandingInvoices['USD']['total'] ?? null);
                $this->assertSame('100.00', $outstandingInvoices['CDF']['total'] ?? null);
                $usdCashSession = collect($overview['open_cash_sessions'])->firstWhere('name', 'Caisse dollars');
                $this->assertSame('114.00', $usdCashSession['balance'] ?? null);
                $this->assertSame('23.00', $usdCashSession['inflows'] ?? null);
                $this->assertSame('9.00', $usdCashSession['outflows'] ?? null);

                return true;
            });
    }

    public function test_cashier_dashboard_only_shows_the_cashiers_own_validated_receipts_and_open_sessions(): void
    {
        $this->freezeTime();
        $cashier = User::factory()->create();
        $cashierRole = Role::create(['name' => 'Responsable de caisse', 'slug' => 'responsable-caisse']);
        $cashier->roles()->attach($cashierRole, ['is_primary' => true]);
        $otherCashier = User::factory()->create();
        $cashbox = Cashbox::create(['name' => 'Caisse attribuée', 'code' => 'ASSIGNED-USD', 'currency' => 'USD']);
        $otherCashbox = Cashbox::create(['name' => 'Autre caisse', 'code' => 'OTHER-USD', 'currency' => 'USD']);
        $cashSession = $this->openSession($cashbox, $cashier);
        $otherCashSession = $this->openSession($otherCashbox, $otherCashier);
        $ownPayment = $this->payment($cashier, 'INVOICE-OWN', 'USD', '30.00', 'validated', now());
        $otherPayment = $this->payment($otherCashier, 'INVOICE-OTHER', 'USD', '99.00', 'validated', now());
        $this->movement($cashSession, $cashier, CashMovement::TYPE_INCOME, '30.00', now(), 'validated', $ownPayment);
        $this->movement($cashSession, $cashier, CashMovement::TYPE_EXPENSE, '6.00', now());
        $this->movement($cashSession, $cashier, CashMovement::TYPE_EXPENSE, '99.00', now(), 'pending');
        $this->movement($otherCashSession, $otherCashier, CashMovement::TYPE_INCOME, '99.00', now(), 'validated', $otherPayment);
        $this->movement($otherCashSession, $otherCashier, CashMovement::TYPE_EXPENSE, '88.00', now(), 'pending');

        $this->actingAs($cashier)
            ->get(route('dashboard.roles.show', $cashierRole->slug))
            ->assertOk()
            ->assertDontSee('Soldes des factures dues')
            ->assertSee('Opérations à valider')
            ->assertViewHas('financeOverview', function (array $overview): bool {
                $this->assertSame([['currency' => 'USD', 'total' => '30.00']], $overview['daily_receipts']);
                $this->assertCount(1, $overview['open_cash_sessions']);
                $this->assertSame('24.00', $overview['open_cash_sessions'][0]['balance']);
                $this->assertSame('30.00', $overview['open_cash_sessions'][0]['inflows']);
                $this->assertSame('6.00', $overview['open_cash_sessions'][0]['outflows']);
                $this->assertCount(3, $overview['recent_cash_movements']);
                $this->assertSame([], $overview['outstanding_invoices']);
                $this->assertSame('Caisse attribuée', $overview['open_cash_sessions'][0]['name']);

                return true;
            });
    }

    public function test_cashier_dashboard_marks_a_balance_unavailable_for_an_unclassified_movement(): void
    {
        $cashier = User::factory()->create();
        $cashierRole = Role::create(['name' => 'Responsable de caisse', 'slug' => 'responsable-caisse']);
        $cashier->roles()->attach($cashierRole, ['is_primary' => true]);
        $cashbox = Cashbox::create(['name' => 'Caisse à contrôler', 'code' => 'UNKNOWN-USD', 'currency' => 'USD']);
        $cashSession = $this->openSession($cashbox, $cashier);
        $this->movement($cashSession, $cashier, 'adjustment', '12.00', now());

        $this->actingAs($cashier)
            ->get(route('dashboard.roles.show', $cashierRole->slug))
            ->assertOk()
            ->assertViewHas('financeOverview', function (array $overview): bool {
                return $overview['open_cash_sessions'][0]['balance'] === null;
            });
    }

    public function test_cashier_dashboard_marks_a_balance_unavailable_for_a_currency_mismatch(): void
    {
        $cashier = User::factory()->create();
        $cashierRole = Role::create(['name' => 'Responsable de caisse', 'slug' => 'responsable-caisse']);
        $cashier->roles()->attach($cashierRole, ['is_primary' => true]);
        $cashbox = Cashbox::create(['name' => 'Caisse francs', 'code' => 'MISMATCH-CDF', 'currency' => 'CDF']);
        $cashSession = $this->openSession($cashbox, $cashier);
        $this->movement($cashSession, $cashier, CashMovement::TYPE_INCOME, '100.00', now())->update(['currency' => 'USD']);

        $this->actingAs($cashier)
            ->get(route('dashboard.roles.show', $cashierRole->slug))
            ->assertOk()
            ->assertViewHas('financeOverview', function (array $overview): bool {
                return $overview['open_cash_sessions'][0]['balance'] === null;
            });
    }

    public function test_pedagogical_dashboard_uses_recorded_attendance_and_graded_results(): void
    {
        $this->freezeTime();
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'Responsable pédagogique', 'slug' => 'responsable-pedagogique']);
        $manager->roles()->attach($role, ['is_primary' => true]);
        $instructor = User::factory()->create();
        $training = Training::create(['title' => 'Formation tableau de bord', 'slug' => 'formation-tableau-de-bord']);
        $session = TrainingSession::create([
            'training_id' => $training->id,
            'instructor_id' => $instructor->id,
            'name' => 'Session active',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);

        foreach (['confirmed', 'in_progress', 'pending'] as $index => $status) {
            Enrollment::create([
                'user_id' => User::factory()->create()->id,
                'training_id' => $training->id,
                'training_session_id' => $session->id,
                'registration_key' => 'DASHBOARD-ENROLLMENT-'.$index,
                'status' => $status,
            ]);
        }

        AttendanceRecord::create([
            'training_session_id' => $session->id,
            'user_id' => User::factory()->create()->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);
        AttendanceRecord::create([
            'training_session_id' => $session->id,
            'user_id' => User::factory()->create()->id,
            'attendance_date' => now()->subMonth()->toDateString(),
            'status' => 'absent',
        ]);

        $evaluation = Evaluation::create([
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'created_by' => $instructor->id,
            'title' => 'Évaluation notée',
            'total_points' => '20.00',
            'passing_points' => '10.00',
        ]);
        foreach ([15, 8, null] as $index => $score) {
            EvaluationAttempt::create([
                'evaluation_id' => $evaluation->id,
                'user_id' => User::factory()->create()->id,
                'attempt_number' => 1,
                'status' => 'submitted',
                'score' => $score,
                'submitted_at' => now(),
            ]);
        }

        $this->assertSame(1, AttendanceRecord::query()
            ->where('status', 'absent')
            ->whereDate('attendance_date', '>=', now()->startOfMonth())
            ->whereDate('attendance_date', '<=', now())
            ->count());

        $this->actingAs($manager)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                $metricsByLabel = collect($metrics)->keyBy('label');
                $this->assertSame(2, $metricsByLabel['Apprenants inscrits']['value']);
                $this->assertSame(1, $metricsByLabel['Absences ce mois']['value']);
                $this->assertSame(50, $metricsByLabel['Taux de réussite']['value']);

                return true;
            })
            ->assertViewHas('trainingPerformance', function ($performance): bool {
                $this->assertSame([
                    ['title' => 'Formation tableau de bord', 'attempts' => 2, 'success_rate' => 50],
                ], $performance->all());

                return true;
            })
            ->assertViewHas('upcomingSessions', function ($sessions): bool {
                $this->assertCount(1, $sessions);
                $this->assertSame('Session active', $sessions->first()->name);

                return true;
            });
    }

    public function test_administrator_dashboard_shows_recent_activity_entries(): void
    {
        $administrator = User::factory()->create();
        $role = Role::create(['name' => 'Administrateur', 'slug' => 'administrateur']);
        $administrator->roles()->attach($role, ['is_primary' => true]);
        ActivityLog::create([
            'user_id' => $administrator->id,
            'event' => 'training.published',
            'description' => 'Une formation a été publiée.',
        ]);
        $this->payment($administrator, 'ALERT-PENDING', 'USD', '20.00', 'pending', now());

        $this->actingAs($administrator)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertOk()
            ->assertSee('Dernières activités')
            ->assertSee('Une formation a été publiée.')
            ->assertSee($administrator->name)
            ->assertSee('Alertes')
            ->assertSee('Paiements à valider');
    }

    public function test_learner_dashboard_limits_progress_and_upcoming_sessions_to_own_enrollments(): void
    {
        $this->freezeTime();
        $learner = User::factory()->create();
        $role = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);
        $learner->roles()->attach($role, ['is_primary' => true]);
        $training = Training::create(['title' => 'Formation suivie', 'slug' => 'formation-suivie']);
        $otherTraining = Training::create(['title' => 'Formation non suivie', 'slug' => 'formation-non-suivie']);
        $module = TrainingModule::create(['training_id' => $training->id, 'title' => 'Module 1']);
        $lesson = Lesson::create(['training_module_id' => $module->id, 'title' => 'Leçon 1', 'slug' => 'lecon-1']);
        $otherModule = TrainingModule::create(['training_id' => $otherTraining->id, 'title' => 'Module 2']);
        $otherLesson = Lesson::create(['training_module_id' => $otherModule->id, 'title' => 'Leçon 2', 'slug' => 'lecon-2']);
        $ownFutureSession = TrainingSession::create([
            'training_id' => $training->id,
            'name' => 'Séance confirmée',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);
        $pendingFutureSession = TrainingSession::create([
            'training_id' => $training->id,
            'name' => 'Séance en attente',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(2),
        ]);

        foreach ([[$ownFutureSession, 'confirmed'], [$pendingFutureSession, 'pending']] as $index => [$session, $status]) {
            Enrollment::create([
                'user_id' => $learner->id,
                'training_id' => $training->id,
                'training_session_id' => $session->id,
                'registration_key' => 'LEARNER-DASH-'.$index,
                'status' => $status,
            ]);
        }
        Progression::create(['user_id' => $learner->id, 'lesson_id' => $lesson->id, 'completion_percent' => '80.00']);
        Progression::create(['user_id' => $learner->id, 'lesson_id' => $otherLesson->id, 'completion_percent' => '100.00']);

        $this->actingAs($learner)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertOk()
            ->assertSee('Séance confirmée')
            ->assertDontSee('Séance en attente')
            ->assertViewHas('metrics', function (array $metrics): bool {
                $metricsByLabel = collect($metrics)->keyBy('label');
                $this->assertSame(80, $metricsByLabel['Progression moyenne']['value']);
                $this->assertSame(1, $metricsByLabel['Mes prochaines séances']['value']);

                return true;
            })
            ->assertViewHas('upcomingSessions', function ($sessions): bool {
                $this->assertCount(1, $sessions);
                $this->assertSame('Séance confirmée', $sessions->first()->name);

                return true;
            });
    }

    public function test_learner_dashboard_shows_only_own_payments_and_remaining_invoice_balances(): void
    {
        $learner = User::factory()->create();
        $role = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);
        $learner->roles()->attach($role, ['is_primary' => true]);
        $otherLearner = User::factory()->create();
        $training = Training::create(['title' => 'Formation financière', 'slug' => 'formation-financiere']);
        $ownEnrollment = Enrollment::create([
            'user_id' => $learner->id,
            'training_id' => $training->id,
            'registration_key' => 'LEARNER-FINANCE-OWN',
            'status' => 'confirmed',
        ]);
        $otherEnrollment = Enrollment::create([
            'user_id' => $otherLearner->id,
            'training_id' => $training->id,
            'registration_key' => 'LEARNER-FINANCE-OTHER',
            'status' => 'confirmed',
        ]);
        $ownInvoice = Invoice::create([
            'enrollment_id' => $ownEnrollment->id,
            'number' => 'OWN-INVOICE-200',
            'status' => 'partially_paid',
            'currency' => 'USD',
            'total_amount' => '200.00',
            'due_on' => now()->addDays(5),
        ]);
        Invoice::create([
            'enrollment_id' => $otherEnrollment->id,
            'number' => 'OTHER-LEARNER-INVOICE',
            'status' => 'issued',
            'currency' => 'USD',
            'total_amount' => '500.00',
        ]);
        $this->recordPayment($ownInvoice, $learner, '150.00', 'USD', 'validated', 'RECEIPT-OWN-01');
        $this->recordPayment($ownInvoice, $learner, '25.00', 'USD', 'pending');
        $this->recordPayment($ownInvoice, $learner, '40.00', 'CDF', 'validated');

        $this->actingAs($learner)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertOk()
            ->assertSee('OWN-INVOICE-200')
            ->assertSee('RECEIPT-OWN-01')
            ->assertDontSee('OTHER-LEARNER-INVOICE')
            ->assertViewHas('learnerFinance', function (array $finance): bool {
                $this->assertSame([['currency' => 'USD', 'total' => '50.00']], $finance['outstanding_totals']);
                $this->assertSame('50.00', $finance['outstanding_invoices'][0]['amount']);
                $this->assertCount(3, $finance['recent_payments']);
                $this->assertSame(['OWN-INVOICE-200', 'OWN-INVOICE-200', 'OWN-INVOICE-200'], array_column($finance['recent_payments'], 'invoice_number'));

                return true;
            });
    }

    public function test_it_dashboard_surfaces_project_status_and_near_term_task_deadlines(): void
    {
        $this->freezeTime();
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'Responsable informatique', 'slug' => 'responsable-informatique']);
        $manager->roles()->attach($role, ['is_primary' => true]);
        $client = Client::create(['name' => 'Client tableau de bord']);
        $activeProject = Project::create(['client_id' => $client->id, 'title' => 'Projet actif', 'status' => 'in_progress']);
        Project::create(['client_id' => $client->id, 'title' => 'Projet terminé', 'status' => 'completed']);
        ProjectTask::create([
            'project_id' => $activeProject->id,
            'title' => 'Échéance proche',
            'status' => 'pending',
            'due_at' => now()->addDays(3),
        ]);
        ProjectTask::create([
            'project_id' => $activeProject->id,
            'title' => 'Tâche terminée',
            'status' => 'completed',
            'due_at' => now()->addDays(2),
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                $metricsByLabel = collect($metrics)->keyBy('label');
                $this->assertSame(1, $metricsByLabel['Projets en cours']['value']);
                $this->assertSame(1, $metricsByLabel['Projets terminés']['value']);
                $this->assertSame(1, $metricsByLabel['Échéances sous 7 jours']['value']);

                return true;
            });
    }

    public function test_commercial_dashboard_calculates_monthly_enrollment_conversion(): void
    {
        $this->freezeTime();
        $salesperson = User::factory()->create();
        $role = Role::create(['name' => 'Commercial', 'slug' => 'commercial']);
        $salesperson->roles()->attach($role, ['is_primary' => true]);
        $training = Training::create(['title' => 'Formation commerciale', 'slug' => 'formation-commerciale']);

        foreach ([['confirmed', now()], ['pending', now()], ['confirmed', now()->subMonth()]] as $index => [$status, $registeredAt]) {
            Enrollment::create([
                'user_id' => User::factory()->create()->id,
                'training_id' => $training->id,
                'registration_key' => 'SALES-DASH-'.$index,
                'status' => $status,
                'registered_at' => $registeredAt,
            ]);
        }

        $this->actingAs($salesperson)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertOk()
            ->assertSee('Formation commerciale')
            ->assertViewHas('metrics', function (array $metrics): bool {
                $metricsByLabel = collect($metrics)->keyBy('label');
                $this->assertSame(1, $metricsByLabel['Inscriptions confirmées ce mois']['value']);
                $this->assertSame(50, $metricsByLabel['Conversion des inscriptions']['value']);

                return true;
            });
    }

    private function payment(User $receiver, string $invoiceNumber, string $currency, string $amount, string $status, \DateTimeInterface $receivedAt): Payment
    {
        $invoice = Invoice::create([
            'number' => $invoiceNumber,
            'currency' => $currency,
            'total_amount' => $amount,
        ]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $receiver->id,
            'amount' => $amount,
            'currency' => $currency,
            'method' => 'cash',
            'status' => $status,
            'received_at' => $receivedAt,
            'validated_at' => $status === 'validated' ? $receivedAt : null,
        ]);
    }

    private function openSession(Cashbox $cashbox, User $cashier): CashSession
    {
        return CashSession::create([
            'cashbox_id' => $cashbox->id,
            'cashier_id' => $cashier->id,
            'status' => 'open',
            'opening_balance' => '0.00',
            'opened_at' => now(),
        ]);
    }

    private function recordPayment(Invoice $invoice, User $receiver, string $amount, string $currency, string $status, ?string $receiptNumber = null): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $receiver->id,
            'amount' => $amount,
            'currency' => $currency,
            'receipt_number' => $receiptNumber,
            'method' => 'cash',
            'status' => $status,
            'received_at' => now(),
            'validated_at' => $status === 'validated' ? now() : null,
        ]);
    }

    private function movement(
        CashSession $cashSession,
        User $cashier,
        string $type,
        string $amount,
        \DateTimeInterface $occurredAt,
        string $status = 'validated',
        ?Payment $payment = null,
    ): CashMovement {
        return CashMovement::create([
            'cash_session_id' => $cashSession->id,
            'payment_id' => $payment?->id,
            'recorded_by' => $cashier->id,
            'type' => $type,
            'status' => $status,
            'description' => 'Mouvement de test',
            'amount' => $amount,
            'currency' => $cashSession->cashbox->currency,
            'occurred_at' => $occurredAt,
        ]);
    }
}
