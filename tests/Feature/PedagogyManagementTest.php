<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Category;
use App\Models\DeliveryMode;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\EvaluationResponse;
use App\Models\Lesson;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedagogyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_published_training_with_delivery_modes(): void
    {
        $manager = $this->userWithPermissions(['trainings.manage']);
        $category = Category::create(['name' => 'Gestion', 'slug' => 'gestion']);
        $online = DeliveryMode::create(['name' => 'En ligne', 'slug' => 'en-ligne']);

        $this->actingAs($manager)->get(route('management.trainings.create'))
            ->assertOk()
            ->assertSee('Créer une formation');

        $this->actingAs($manager)->post(route('management.trainings.store'), [
            'category_id' => $category->id,
            'title' => 'Comptabilité pratique',
            'summary' => 'Apprendre la comptabilité appliquée.',
            'level' => 'debutant',
            'currency' => 'USD',
            'price' => '75.00',
            'objectives' => ['Comprendre les écritures', '', ''],
            'syllabus' => ['Bases comptables', '', ''],
            'delivery_modes' => [$online->id],
            'is_published' => '1',
        ])->assertRedirect();

        $training = Training::query()->where('slug', 'comptabilite-pratique')->firstOrFail();
        $this->assertTrue($training->is_published);
        $this->assertSame(['Comprendre les écritures'], $training->objectives);
        $this->assertTrue($training->deliveryModes()->whereKey($online->id)->exists());
        $this->get(route('trainings.show', $training))->assertOk()->assertSee('Comptabilité pratique');
        $this->get(route('management.trainings.show', $training))->assertOk()->assertSee('Modules et leçons');
    }

    public function test_user_without_training_permission_is_forbidden_from_management(): void
    {
        $learner = $this->userWithPermissions([], 'apprenant');

        $this->actingAs($learner)->get(route('management.trainings.index'))->assertForbidden();
    }

    public function test_staff_can_add_a_module_and_published_lesson_to_a_training(): void
    {
        $manager = $this->userWithPermissions(['trainings.manage']);
        $training = $this->training('Excel professionnel');

        $this->actingAs($manager)->post(route('management.modules.store', $training), [
            'title' => 'Formules essentielles',
            'description' => 'Maîtriser les calculs courants.',
        ])->assertRedirect();

        $module = $training->modules()->firstOrFail();
        $this->get(route('management.trainings.show', $training))->assertOk()->assertSee('Formules essentielles');
        $this->actingAs($manager)->post(route('management.lessons.store', $module), [
            'title' => 'Premières formules',
            'content' => 'Découverte des formules de calcul.',
            'duration_minutes' => 30,
            'is_published' => '1',
        ])->assertRedirect();

        $lesson = Lesson::query()->where('training_module_id', $module->id)->firstOrFail();
        $this->assertSame('premieres-formules', $lesson->slug);
        $this->assertTrue($lesson->is_published);

        $this->actingAs($manager)->put(route('management.modules.update', $module), [
            'title' => 'Formules de calcul',
            'description' => 'Mettre en pratique les calculs.',
            'sort_order' => 1,
        ])->assertRedirect();
        $this->actingAs($manager)->put(route('management.lessons.update', $lesson->id), [
            'title' => 'Formules de base',
            'slug' => 'premieres-formules',
            'content' => 'Utiliser les formules courantes dans un tableau.',
            'duration_minutes' => 40,
            'sort_order' => 1,
            'is_published' => '1',
        ])->assertRedirect();

        $this->assertSame('Formules de base', $lesson->fresh()->title);
        $this->assertSame('Formules de calcul', $module->fresh()->title);
    }

    public function test_assigned_trainer_can_record_attendance_for_confirmed_learners(): void
    {
        $trainer = $this->userWithPermissions(['pedagogy.manage'], 'formateur');
        $learner = User::factory()->create();
        $training = $this->training('Développement web');
        $mode = DeliveryMode::create(['name' => 'À distance', 'slug' => 'a-distance']);
        $training->deliveryModes()->attach($mode);
        $session = TrainingSession::create([
            'training_id' => $training->id,
            'delivery_mode_id' => $mode->id,
            'instructor_id' => $trainer->id,
            'name' => 'Groupe A',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);
        Enrollment::create([
            'user_id' => $learner->id,
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'delivery_mode_id' => $mode->id,
            'registration_key' => hash('sha256', 'attendance-test'),
            'status' => 'confirmed',
        ]);

        $this->actingAs($trainer)->post(route('management.pedagogy.attendance.store', $session), [
            'attendance_date' => now()->toDateString(),
            'attendees' => [$learner->id => ['status' => 'late', 'notes' => 'Arrivée après le début']],
        ])->assertRedirect();

        $this->actingAs($trainer)->get(route('management.pedagogy.attendance.show', $session))
            ->assertOk()
            ->assertSee('Arrivée après le début');

        $this->assertDatabaseHas('attendance_records', [
            'training_session_id' => $session->id,
            'user_id' => $learner->id,
            'recorded_by' => $trainer->id,
            'status' => 'late',
        ]);
        $this->assertInstanceOf(AttendanceRecord::class, AttendanceRecord::first());
    }

    public function test_pedagogy_manager_can_create_a_session_with_a_formateur(): void
    {
        $manager = $this->userWithPermissions(['pedagogy.manage'], 'responsable-pedagogique');
        $trainer = $this->userWithPermissions([], 'formateur');
        $training = $this->training('Initiation à la programmation');
        $mode = DeliveryMode::create(['name' => 'En ligne', 'slug' => 'en-ligne']);
        $training->deliveryModes()->attach($mode);

        $this->actingAs($manager)->get(route('management.pedagogy.sessions.index'))
            ->assertOk()
            ->assertSee('Programmer une session');

        $this->actingAs($manager)->post(route('management.pedagogy.sessions.store'), [
            'training_id' => $training->id,
            'delivery_mode_id' => $mode->id,
            'instructor_id' => $trainer->id,
            'name' => 'Groupe de juillet',
            'status' => 'scheduled',
            'capacity' => 15,
            'starts_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addWeeks(2)->addHours(3)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseHas('training_sessions', [
            'training_id' => $training->id,
            'instructor_id' => $trainer->id,
            'name' => 'Groupe de juillet',
        ]);
        $session = TrainingSession::query()->firstOrFail();
        $this->actingAs($manager)->get(route('management.pedagogy.sessions.index'))
            ->assertOk()
            ->assertSee('Modifier la session');
        $this->actingAs($manager)->put(route('management.pedagogy.sessions.update', $session), [
            'training_id' => $training->id,
            'delivery_mode_id' => $mode->id,
            'instructor_id' => $trainer->id,
            'name' => 'Groupe de juillet, modifié',
            'status' => 'scheduled',
            'capacity' => 20,
            'starts_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addWeeks(2)->addHours(3)->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $this->assertSame('Groupe de juillet, modifié', $session->fresh()->name);
    }

    public function test_pedagogy_manager_can_create_and_manually_grade_an_evaluation(): void
    {
        $manager = $this->userWithPermissions(['pedagogy.manage'], 'responsable-pedagogique');
        $training = $this->training('Comptabilité SYSCOHADA');

        $this->actingAs($manager)->get(route('management.pedagogy.evaluations.create'))
            ->assertOk()
            ->assertSee('Créer une évaluation');

        $this->actingAs($manager)->post(route('management.pedagogy.evaluations.store'), [
            'training_id' => $training->id,
            'title' => 'Contrôle final',
            'type' => 'exam',
            'is_published' => '1',
            'questions' => [
                [
                    'prompt' => 'Expliquez la notion de bilan.',
                    'question_type' => 'short_answer',
                    'options' => ['', '', ''],
                    'answer' => 'Le bilan décrit une situation.',
                    'points' => '4',
                ],
            ],
        ])->assertRedirect(route('management.pedagogy.evaluations.index'));

        $evaluation = Evaluation::query()->firstOrFail();
        $question = $evaluation->questions()->firstOrFail();
        $learner = $this->userWithPermissions(['learning.access'], 'apprenant');
        Enrollment::create([
            'user_id' => $learner->id,
            'training_id' => $training->id,
            'registration_key' => hash('sha256', 'manual-grade-'.$learner->id),
            'status' => 'confirmed',
        ]);

        $this->actingAs($learner)->get(route('learning.evaluations.show', $evaluation))->assertOk();
        $this->actingAs($learner)->post(route('learning.evaluations.submit', $evaluation), [
            'answers' => [$question->id => 'Une situation financière à une date donnée.'],
        ])->assertRedirect();

        $response = EvaluationResponse::query()->firstOrFail();
        $this->assertNull($response->awarded_points);

        $this->actingAs($manager)->get(route('management.pedagogy.evaluations.show', $evaluation))
            ->assertOk()
            ->assertSee('Une situation financière à une date donnée.');
        $this->actingAs($manager)->patch(route('management.pedagogy.evaluations.grade', $response), [
            'awarded_points' => '3',
            'instructor_feedback' => 'Bonne réponse.',
        ])->assertRedirect();
        $this->assertSame('3.00', $response->fresh()->awarded_points);
        $this->assertSame('3.00', $response->attempt->fresh()->score);
    }

    public function test_enrollment_cannot_be_confirmed_after_session_capacity_is_reached(): void
    {
        $manager = $this->userWithPermissions(['pedagogy.manage'], 'responsable-pedagogique');
        $training = $this->training('Bureautique avancée');
        $mode = DeliveryMode::create(['name' => 'Présentiel', 'slug' => 'presentiel']);
        $training->deliveryModes()->attach($mode);
        $session = TrainingSession::create([
            'training_id' => $training->id,
            'delivery_mode_id' => $mode->id,
            'name' => 'Session complète',
            'status' => 'scheduled',
            'capacity' => 1,
            'starts_at' => now()->addWeek(),
        ]);
        $alreadyConfirmed = User::factory()->create();
        $candidate = User::factory()->create();
        Enrollment::create([
            'user_id' => $alreadyConfirmed->id,
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'delivery_mode_id' => $mode->id,
            'registration_key' => hash('sha256', 'occupied-seat'),
            'status' => 'confirmed',
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $candidate->id,
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'delivery_mode_id' => $mode->id,
            'registration_key' => hash('sha256', 'waiting-seat'),
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->get(route('management.pedagogy.enrollments.index'))
            ->assertOk()
            ->assertSee('Bureautique avancée');

        $this->actingAs($manager)->patch(route('management.pedagogy.enrollments.update', $enrollment), [
            'status' => 'confirmed',
        ])->assertSessionHasErrors('status');

        $this->assertSame('pending', $enrollment->fresh()->status);
    }

    private function userWithPermissions(array $permissionSlugs, string $roleSlug = 'responsable-pedagogique'): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => ucfirst(str_replace('-', ' ', $roleSlug)), 'slug' => $roleSlug]);
        $permissions = [];

        foreach ($permissionSlugs as $slug) {
            $permissions[] = Permission::create(['name' => $slug, 'slug' => $slug]);
        }

        $role->permissions()->sync(collect($permissions)->pluck('id')->all());
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }

    private function training(string $title): Training
    {
        return Training::create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'level' => 'debutant',
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
