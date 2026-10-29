<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\Lesson;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Training;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_learner_can_open_lessons_and_record_progress(): void
    {
        $learner = $this->userWithPermission('learning.access', 'apprenant');
        [$training, $lesson] = $this->trainingWithLesson('Bureautique avancée');
        $enrollment = $this->enrollment($learner, $training, 'confirmed');
        $this->assertSame($training->id, $enrollment->fresh()->training_id);
        $this->assertSame($training->id, $enrollment->training->id);

        $this->actingAs($learner)->get(route('learning.index'))
            ->assertOk()
            ->assertSee('Bureautique avancée')
            ->assertSee('Première leçon');
        $this->actingAs($learner)->get(route('learning.lessons.show', $lesson))
            ->assertOk()
            ->assertSee('Contenu réservé aux inscrits.');
        $this->actingAs($learner)->post(route('learning.lessons.complete', $lesson), ['seconds_watched' => 600])
            ->assertRedirect(route('learning.lessons.show', $lesson));

        $this->assertDatabaseHas('progressions', [
            'user_id' => $learner->id,
            'lesson_id' => $lesson->id,
            'completion_percent' => 100,
            'seconds_watched' => 600,
        ]);
        $this->assertSame('in_progress', $enrollment->fresh()->status);
    }

    public function test_learner_cannot_open_course_content_without_a_confirmed_enrollment(): void
    {
        $learner = $this->userWithPermission('learning.access', 'apprenant');
        [, $lesson] = $this->trainingWithLesson('Comptabilité générale');

        $this->actingAs($learner)->get(route('learning.lessons.show', $lesson))->assertNotFound();
    }

    public function test_evaluation_does_not_expose_the_answer_key_and_scores_multiple_choice(): void
    {
        $learner = $this->userWithPermission('learning.access', 'apprenant');
        $training = $this->training('Gestion de projet');
        $this->enrollment($learner, $training, 'in_progress');
        $evaluation = Evaluation::create([
            'training_id' => $training->id,
            'created_by' => $learner->id,
            'title' => 'Vérification des acquis',
            'type' => 'quiz',
            'total_points' => 2,
            'is_published' => true,
        ]);
        $question = $evaluation->questions()->create([
            'prompt' => 'Quelle est la bonne réponse ?',
            'question_type' => 'multiple_choice',
            'options' => ['3', '4'],
            'answer_key' => ['answer' => '4'],
            'points' => 2,
            'sort_order' => 0,
        ]);

        $this->actingAs($learner)->get(route('learning.evaluations.show', $evaluation))
            ->assertOk()
            ->assertSee('Quelle est la bonne réponse ?')
            ->assertDontSee('answer_key');
        $this->actingAs($learner)->post(route('learning.evaluations.submit', $evaluation), [
            'answers' => [$question->id => '4'],
        ])->assertRedirect(route('learning.evaluations.show', $evaluation));

        $attempt = EvaluationAttempt::query()->where('evaluation_id', $evaluation->id)->firstOrFail();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('2.00', $attempt->score);
        $this->assertSame('2.00', $attempt->responses()->firstOrFail()->awarded_points);
    }

    public function test_management_can_issue_a_certificate_only_for_a_completed_enrollment(): void
    {
        $manager = $this->userWithPermission('pedagogy.manage', 'responsable-pedagogique');
        $learner = $this->userWithPermission('learning.access', 'apprenant');
        $training = $this->training('Entrepreneuriat');
        $pending = $this->enrollment($learner, $training, 'pending');

        $this->actingAs($manager)->post(route('management.pedagogy.enrollments.certificate', $pending))
            ->assertStatus(422);
        $pending->update(['status' => 'completed']);
        $this->actingAs($manager)->post(route('management.pedagogy.enrollments.certificate', $pending))
            ->assertRedirect();

        $this->assertSame(1, Certificate::query()->where('enrollment_id', $pending->id)->count());
        $this->actingAs($learner)->get(route('learning.certificates.index'))
            ->assertOk()
            ->assertSee('Entrepreneuriat');
    }

    private function userWithPermission(string $permissionSlug, string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => ucfirst($roleSlug), 'slug' => $roleSlug]);
        $permission = Permission::create(['name' => $permissionSlug, 'slug' => $permissionSlug]);
        $role->permissions()->attach($permission);
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

    /** @return array{Training, Lesson} */
    private function trainingWithLesson(string $title): array
    {
        $training = $this->training($title);
        $module = TrainingModule::create(['training_id' => $training->id, 'title' => 'Module de démarrage', 'sort_order' => 1]);
        $lesson = Lesson::create([
            'training_module_id' => $module->id,
            'title' => 'Première leçon',
            'slug' => 'premiere-lecon',
            'content' => 'Contenu réservé aux inscrits.',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        return [$training, $lesson];
    }

    private function enrollment(User $user, Training $training, string $status): Enrollment
    {
        return Enrollment::create([
            'user_id' => $user->id,
            'training_id' => $training->id,
            'registration_key' => hash('sha256', $user->id.'|'.$training->id.'|'.$status),
            'status' => $status,
            'registered_at' => now(),
        ]);
    }
}
