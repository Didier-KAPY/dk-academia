<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Training;
use App\Models\TrainingModule;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_create_an_apprenant_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Amina Kalala',
            'email' => 'amina@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'amina@example.com']);

        $user = User::where('email', 'amina@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertTrue($user->hasRole('apprenant'));
        $this->assertTrue($user->hasPermissionTo('learning.access'));
        $this->assertFalse($user->hasRole('administrateur'));

        $this->actingAs($user)
            ->get(route('learning.index'))
            ->assertOk();
    }

    public function test_a_visitor_can_log_in_and_is_redirected_to_the_intended_page(): void
    {
        $user = User::factory()->create(['password' => 'SecurePass123!']);

        $response = $this->withSession(['url.intended' => route('dashboard')])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'SecurePass123!',
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_a_user_is_redirected_to_their_only_role_dashboard(): void
    {
        $role = Role::create([
            'name' => 'Formateur',
            'slug' => 'formateur',
            'description' => 'Espace de travail pédagogique.',
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role, ['is_primary' => true]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('dashboard.roles.show', $role->slug));

        $this->actingAs($user)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertOk()
            ->assertSee('Espace de travail pédagogique.');
    }

    public function test_users_cannot_open_a_role_dashboard_they_do_not_have(): void
    {
        $role = Role::create([
            'name' => 'Administrateur général',
            'slug' => 'administrateur',
            'description' => 'Administration de la plateforme.',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.roles.show', $role->slug))
            ->assertForbidden();
    }

    public function test_a_user_with_multiple_roles_must_choose_a_dashboard(): void
    {
        $user = User::factory()->create();
        $roles = collect([
            ['name' => 'Formateur', 'slug' => 'formateur'],
            ['name' => 'Commercial', 'slug' => 'commercial'],
        ])->map(fn (array $attributes): Role => Role::create($attributes));

        $user->roles()->attach(
            $roles->mapWithKeys(fn (Role $role): array => [$role->getKey() => ['is_primary' => false]]),
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Choisissez celui que vous souhaitez ouvrir.')
            ->assertSee(route('dashboard.roles.show', 'formateur'))
            ->assertSee(route('dashboard.roles.show', 'commercial'));
    }

    public function test_a_user_is_redirected_to_their_primary_role_when_one_is_set(): void
    {
        $user = User::factory()->create();
        $primaryRole = Role::create(['name' => 'Formateur', 'slug' => 'formateur']);
        $secondaryRole = Role::create(['name' => 'Commercial', 'slug' => 'commercial']);
        $user->roles()->attach([
            $primaryRole->id => ['is_primary' => true],
            $secondaryRole->id => ['is_primary' => false],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('dashboard.roles.show', $primaryRole->slug));
    }

    public function test_each_staff_and_learner_dashboard_loads_its_role_specific_metrics(): void
    {
        $roleLabels = [
            'administrateur' => 'Utilisateurs',
            'gestionnaire-administratif' => 'Inscriptions à traiter',
            'comptable' => 'Factures émises',
            'responsable-caisse' => 'Mes caisses ouvertes',
            'responsable-pedagogique' => 'Évaluations publiées',
            'formateur' => 'Mes sessions à venir',
            'apprenant' => 'Mes inscriptions',
            'responsable-informatique' => 'Tickets ouverts',
            'commercial' => 'Demandes de contact',
        ];

        foreach ($roleLabels as $slug => $metricLabel) {
            $role = Role::create(['name' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug]);
            $user = User::factory()->create();
            $user->roles()->attach($role, ['is_primary' => true]);

            $response = $this->actingAs($user)
                ->get(route('dashboard.roles.show', $role->slug))
                ->assertOk()
                ->assertSee($metricLabel);

            if (in_array($slug, ['administrateur', 'gestionnaire-administratif', 'responsable-pedagogique'], true)) {
                $response->assertSee(route('management.trainings.index'))
                    ->assertSee(route('management.pedagogy.enrollments.index'));
            }

            if ($slug === 'formateur') {
                $response->assertSee(route('management.pedagogy.evaluations.index'));
            }

            if (in_array($slug, ['comptable', 'responsable-caisse'], true)) {
                $response->assertDontSee('dashboard-shortcuts');
            }
        }
    }

    public function test_trainer_dashboard_counts_only_learners_with_an_active_enrollment_in_their_sessions(): void
    {
        $trainer = User::factory()->create();
        $trainerRole = Role::create(['name' => 'Formateur', 'slug' => 'formateur']);
        $trainer->roles()->attach($trainerRole, ['is_primary' => true]);
        $training = Training::create(['title' => 'Cours du formateur', 'slug' => 'cours-du-formateur']);
        $module = TrainingModule::create(['training_id' => $training->id, 'title' => 'Module du formateur']);
        Lesson::create(['training_module_id' => $module->id, 'title' => 'Leçon du formateur', 'slug' => 'lecon-du-formateur']);
        $session = TrainingSession::create([
            'training_id' => $training->id,
            'instructor_id' => $trainer->id,
            'status' => 'scheduled',
        ]);
        $pendingLearner = User::factory()->create();
        $activeLearner = User::factory()->create();
        Enrollment::create([
            'user_id' => $pendingLearner->id,
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'registration_key' => 'pending-enrollment',
            'status' => 'pending',
        ]);
        Enrollment::create([
            'user_id' => $activeLearner->id,
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'registration_key' => 'active-enrollment',
            'status' => 'confirmed',
        ]);

        $this->actingAs($trainer)
            ->get(route('dashboard.roles.show', $trainerRole->slug))
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                $metricsByLabel = collect($metrics)->keyBy('label');
                $this->assertSame(1, $metricsByLabel['Mes formations']['value']);
                $this->assertSame(1, $metricsByLabel['Mes modules']['value']);
                $this->assertSame(1, $metricsByLabel['Mes leçons']['value']);

                return ($metricsByLabel['Mes apprenants']['value'] ?? null) === 1;
            });
    }

    public function test_learner_dashboard_counts_only_available_certificates(): void
    {
        $learner = User::factory()->create();
        $learnerRole = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);
        $learner->roles()->attach($learnerRole, ['is_primary' => true]);
        $training = Training::create(['title' => 'Cours terminé', 'slug' => 'cours-termine']);
        $enrollment = Enrollment::create([
            'user_id' => $learner->id,
            'training_id' => $training->id,
            'registration_key' => 'completed-enrollment',
            'status' => 'completed',
        ]);
        Certificate::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $learner->id,
            'certificate_number' => 'CERT-AVAILABLE',
            'issued_at' => now(),
        ]);
        $revokedEnrollment = Enrollment::create([
            'user_id' => $learner->id,
            'training_id' => $training->id,
            'registration_key' => 'revoked-enrollment',
            'status' => 'completed',
        ]);
        Certificate::create([
            'enrollment_id' => $revokedEnrollment->id,
            'user_id' => $learner->id,
            'certificate_number' => 'CERT-REVOKED',
            'issued_at' => now(),
            'revoked_at' => now(),
        ]);

        $this->actingAs($learner)
            ->get(route('dashboard.roles.show', $learnerRole->slug))
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                $metric = collect($metrics)->firstWhere('label', 'Certificats');

                return ($metric['value'] ?? null) === 1;
            });
    }
}
