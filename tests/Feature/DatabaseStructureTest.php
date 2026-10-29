<?php

namespace Tests\Feature;

use App\Models\Cashbox;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Client;
use App\Models\DeliveryMode;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Role;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_migrations_create_the_expected_tables(): void
    {
        $tables = [
            'roles',
            'permissions',
            'categories',
            'trainings',
            'training_sessions',
            'enrollments',
            'evaluation_attempts',
            'certificates',
            'clients',
            'quotes',
            'projects',
            'invoices',
            'payments',
            'cashboxes',
            'cash_sessions',
            'cash_movements',
            'activity_logs',
            'notifications',
            'app_settings',
            'backup_runs',
        ];

        $this->assertTrue(collect($tables)->every(fn (string $table): bool => Schema::hasTable($table)));
    }

    public function test_base_roles_permissions_and_delivery_modes_seed_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(9, Role::count());
        $this->assertSame(11, Permission::count());
        $this->assertSame(3, DeliveryMode::count());
        $this->assertSame(11, Role::where('slug', 'administrateur')->firstOrFail()->permissions()->count());
    }

    public function test_training_modes_sessions_and_enrollments_follow_their_relations(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Informatique', 'slug' => 'informatique']);
        $mode = DeliveryMode::create(['name' => 'En ligne', 'slug' => 'en-ligne']);
        $training = Training::create([
            'category_id' => $category->id,
            'title' => 'Développement web',
            'slug' => 'developpement-web',
            'is_published' => true,
        ]);
        $training->deliveryModes()->attach($mode);
        $session = TrainingSession::create([
            'training_id' => $training->id,
            'delivery_mode_id' => $mode->id,
            'instructor_id' => $user->id,
            'status' => 'scheduled',
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'delivery_mode_id' => $mode->id,
            'registration_key' => hash('sha256', $user->id.'|'.$training->id.'|'.$session->id),
            'status' => 'pending',
            'currency' => 'USD',
        ]);

        $this->assertTrue($training->category->is($category));
        $this->assertTrue($session->instructor->is($user));
        $this->assertTrue($enrollment->training->is($training));
        $this->assertTrue($user->enrollments->first()->is($enrollment));
        $this->assertSame('En ligne', $training->deliveryModes->first()->name);
    }

    public function test_service_invoices_payments_and_cash_movements_are_linked(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'created_by' => $user->id,
            'type' => 'organization',
            'name' => 'Client de démonstration',
            'email' => 'client@example.com',
        ]);
        $quote = Quote::create([
            'client_id' => $client->id,
            'created_by' => $user->id,
            'number' => 'DEV-2026-0001',
            'currency' => 'USD',
        ]);
        QuoteLine::create([
            'quote_id' => $quote->id,
            'description' => 'Création de site web',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'quote_id' => $quote->id,
            'manager_id' => $user->id,
            'title' => 'Site web client',
            'currency' => 'USD',
        ]);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'issued_by' => $user->id,
            'number' => 'FAC-2026-0001',
            'currency' => 'USD',
            'total_amount' => 100,
        ]);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'amount' => 100,
            'currency' => 'USD',
            'method' => 'cash',
            'status' => 'validated',
        ]);
        $cashbox = Cashbox::create([
            'name' => 'Caisse USD',
            'code' => 'USD-MAIN',
            'currency' => 'USD',
        ]);
        $cashSession = CashSession::create([
            'cashbox_id' => $cashbox->id,
            'cashier_id' => $user->id,
            'opening_balance' => 0,
            'opened_at' => now(),
        ]);
        $movement = CashMovement::create([
            'cash_session_id' => $cashSession->id,
            'payment_id' => $payment->id,
            'recorded_by' => $user->id,
            'type' => 'income',
            'status' => 'validated',
            'description' => 'Règlement de facture',
            'amount' => 100,
            'currency' => 'USD',
            'occurred_at' => now(),
        ]);

        $this->assertTrue($client->quotes->first()->is($quote));
        $this->assertTrue($quote->projects->first()->is($project));
        $this->assertTrue($invoice->payments->first()->is($payment));
        $this->assertTrue($payment->cashMovement->is($movement));
        $this->assertSame('USD', $movement->cashSession->cashbox->currency);
    }
}
