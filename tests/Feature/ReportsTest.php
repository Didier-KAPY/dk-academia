<?php

namespace Tests\Feature;

use App\Models\Cashbox;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_shows_period_totals_by_currency_and_enrollments_by_status(): void
    {
        $accountant = $this->userWithFinancePermission();
        $learner = User::factory()->create();
        $training = Training::create(['title' => 'Laravel avancé', 'slug' => 'laravel-avance']);
        Enrollment::create([
            'user_id' => $learner->id,
            'training_id' => $training->id,
            'registration_key' => 'report-enrollment-current',
            'status' => 'confirmed',
            'registered_at' => '2026-10-15 09:00:00',
        ]);
        Enrollment::create([
            'user_id' => $learner->id,
            'training_id' => $training->id,
            'registration_key' => 'report-enrollment-old',
            'status' => 'pending',
            'registered_at' => '2026-09-15 09:00:00',
        ]);
        $invoice = Invoice::create(['number' => 'REPORT-INV-1', 'currency' => 'USD', 'total_amount' => 125]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 125,
            'currency' => 'USD',
            'method' => 'cash',
            'status' => 'validated',
            'received_at' => '2026-10-20 10:00:00',
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 90,
            'currency' => 'USD',
            'method' => 'cash',
            'status' => 'pending',
            'received_at' => '2026-10-21 10:00:00',
        ]);

        $cashbox = Cashbox::create(['name' => 'Caisse USD', 'code' => 'REPORT-USD', 'currency' => 'USD']);
        $cashSession = CashSession::create([
            'cashbox_id' => $cashbox->id,
            'cashier_id' => $accountant->id,
            'opening_balance' => 0,
            'opened_at' => now(),
        ]);
        CashMovement::create([
            'cash_session_id' => $cashSession->id,
            'recorded_by' => $accountant->id,
            'type' => 'expense',
            'status' => 'validated',
            'description' => 'Fournitures',
            'amount' => 25,
            'currency' => 'USD',
            'occurred_at' => '2026-10-22 10:00:00',
        ]);

        $this->actingAs($accountant)
            ->get(route('management.reports.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('125,00 USD')
            ->assertSee('25,00 USD')
            ->assertSee('Laravel avancé')
            ->assertSee('confirmed')
            ->assertDontSee('pending');
    }

    public function test_report_routes_require_finance_permission(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)
            ->get(route('management.reports.index'))
            ->assertForbidden();

        $this->get(route('management.reports.export'))
            ->assertForbidden();
    }

    public function test_report_can_be_downloaded_as_csv(): void
    {
        $accountant = $this->userWithFinancePermission();

        $response = $this->actingAs($accountant)
            ->get(route('management.reports.export', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertDownload('rapport-dk-academia-2026-10-01-2026-10-31.csv');

        $this->assertStringContainsString("\xEF\xBB\xBF\"Rapport DK Academia\";2026-10-01;2026-10-31", $response->streamedContent());
        $this->assertStringContainsString('Type;Devise;Montant;Statut;Formation', $response->streamedContent());
    }

    private function userWithFinancePermission(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Comptable', 'slug' => 'comptable']);
        $permission = Permission::create(['name' => 'Consulter les finances', 'slug' => 'finance.view']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }
}
