<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryMode;
use App\Models\Enrollment;
use App\Models\NewsPost;
use App\Models\Role;
use App\Models\ServiceOffering;
use App\Models\TrainerProfile;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\EnrollmentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicCatalogAndEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_shows_published_trainings_and_applies_filters(): void
    {
        $category = Category::create(['name' => 'Bureautique', 'slug' => 'bureautique']);
        $online = DeliveryMode::create(['name' => 'En ligne', 'slug' => 'en-ligne']);
        $training = $this->training($category, 'Word professionnel', true, 50);
        $training->deliveryModes()->attach($online);
        $this->training($category, 'Formation en brouillon', false, 20);

        $this->get(route('trainings.index', ['category' => 'bureautique', 'mode' => 'en-ligne', 'max_price' => 60]))
            ->assertOk()
            ->assertSee('Word professionnel')
            ->assertDontSee('Formation en brouillon');
    }

    public function test_unpublished_training_detail_is_not_public(): void
    {
        $training = $this->training(null, 'Brouillon', false);

        $this->get(route('trainings.show', $training))->assertNotFound();
    }

    public function test_public_news_hides_drafts_and_scheduled_posts(): void
    {
        $publishedPost = NewsPost::create([
            'title' => 'Actualité publiée',
            'slug' => 'actualite-publiee',
            'category' => 'Annonce',
            'excerpt' => 'Résumé public.',
            'body' => 'Un article déjà visible.',
            'image_path' => 'actualites/article.jpg',
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
        $draftPost = NewsPost::create([
            'title' => 'Actualité brouillon',
            'slug' => 'actualite-brouillon',
            'body' => 'Un article encore privé.',
            'is_published' => false,
            'published_at' => now()->subMinute(),
        ]);
        $scheduledPost = NewsPost::create([
            'title' => 'Actualité programmée',
            'slug' => 'actualite-programmee',
            'body' => 'Un article qui paraîtra plus tard.',
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertSee($publishedPost->title)
            ->assertDontSee($draftPost->title)
            ->assertDontSee($scheduledPost->title);

        $this->get(route('news.show', $draftPost))->assertNotFound();
        $this->get(route('news.show', $scheduledPost))->assertNotFound();
        $this->get(route('news.show', $publishedPost))
            ->assertOk()
            ->assertSee($publishedPost->title)
            ->assertSee('Résumé public.')
            ->assertSee('Un article déjà visible.');
    }

    public function test_public_services_and_prices_only_show_published_offerings(): void
    {
        $publishedService = ServiceOffering::create([
            'name' => 'Service disponible',
            'slug' => 'service-disponible',
            'summary' => 'Une offre publiée.',
            'description' => 'Description publique du service.',
            'price_from' => 75,
            'is_published' => true,
        ]);
        $draftService = ServiceOffering::create([
            'name' => 'Service brouillon',
            'slug' => 'service-brouillon',
            'summary' => 'Une offre privée.',
            'description' => 'Description privée du service.',
            'price_from' => 120,
            'is_published' => false,
        ]);
        $publishedTraining = $this->training(null, 'Formation affichée', true, 90);
        $draftTraining = $this->training(null, 'Formation masquée', false, 20);

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee($publishedService->name)
            ->assertSee('Description publique du service.')
            ->assertDontSee($draftService->name);

        $this->get(route('prices'))
            ->assertOk()
            ->assertSee($publishedService->name)
            ->assertSee($publishedTraining->title)
            ->assertDontSee($draftService->name)
            ->assertDontSee($draftTraining->title);
    }

    public function test_public_trainer_directory_only_shows_public_profiles(): void
    {
        $publicTrainer = User::factory()->create(['name' => 'Formateur visible']);
        $privateTrainer = User::factory()->create(['name' => 'Formateur privé']);
        TrainerProfile::create([
            'user_id' => $publicTrainer->id,
            'specialties' => ['Comptabilité'],
            'biography' => 'Une expérience partagée avec le public.',
            'is_public' => true,
        ]);
        TrainerProfile::create(['user_id' => $privateTrainer->id, 'is_public' => false]);

        $this->get(route('trainers.index'))
            ->assertOk()
            ->assertSee('Formateur visible')
            ->assertDontSee('Formateur privé');
    }

    public function test_public_training_pages_hide_inactive_modes_and_their_sessions(): void
    {
        $training = $this->training(null, 'Formation aux outils numériques', true);
        $activeMode = DeliveryMode::create(['name' => 'Mode accessible', 'slug' => 'mode-accessible', 'is_active' => true]);
        $inactiveMode = DeliveryMode::create(['name' => 'Mode suspendu', 'slug' => 'mode-suspendu', 'is_active' => false]);
        $training->deliveryModes()->attach([$activeMode->id, $inactiveMode->id]);
        TrainingSession::create([
            'training_id' => $training->id,
            'delivery_mode_id' => $activeMode->id,
            'name' => 'Session accessible',
            'status' => 'scheduled',
            'starts_at' => now()->addWeek(),
        ]);
        TrainingSession::create([
            'training_id' => $training->id,
            'delivery_mode_id' => $inactiveMode->id,
            'name' => 'Session suspendue',
            'status' => 'scheduled',
            'starts_at' => now()->addWeek(),
        ]);
        $learner = User::factory()->create();

        $this->get(route('trainings.show', $training))
            ->assertOk()
            ->assertSee('Mode accessible')
            ->assertSee('Session accessible')
            ->assertDontSee('Mode suspendu')
            ->assertDontSee('Session suspendue');

        $this->actingAs($learner)->get(route('enrollments.create', $training))
            ->assertOk()
            ->assertSee('Mode accessible')
            ->assertSee('Session accessible')
            ->assertDontSee('Mode suspendu')
            ->assertDontSee('Session suspendue');
    }

    public function test_training_without_active_modes_shows_contact_instead_of_an_unusable_enrollment_form(): void
    {
        $training = $this->training(null, 'Formation en pause', true);
        $inactiveMode = DeliveryMode::create(['name' => 'Mode archivé', 'slug' => 'mode-archive', 'is_active' => false]);
        $training->deliveryModes()->attach($inactiveMode);
        $learner = User::factory()->create();

        $this->get(route('trainings.show', $training))
            ->assertOk()
            ->assertSee('Les inscriptions pour cette formation ne sont pas ouvertes actuellement.')
            ->assertDontSee(route('enrollments.create', $training));

        $this->actingAs($learner)->get(route('enrollments.create', $training))
            ->assertOk()
            ->assertSee('Nous contacter')
            ->assertDontSee(route('enrollments.store', $training));
    }

    public function test_contact_form_saves_valid_message_and_rejects_invalid_email(): void
    {
        $this->get(route('contact'))->assertOk()->assertSee('Envoyer le message');

        $payload = [
            'name' => 'Amina Kabila',
            'email' => 'amina@example.com',
            'subject' => 'Question sur les cours',
            'message' => 'Bonjour, je souhaite recevoir des informations sur vos formations.',
            'website' => '',
        ];

        $this->post(route('contact.store'), $payload)->assertRedirect(route('contact'));
        $this->assertDatabaseHas('contact_messages', ['email' => 'amina@example.com', 'status' => 'new']);

        $payload['email'] = 'adresse-invalide';
        $this->from(route('contact'))->post(route('contact.store'), $payload)->assertSessionHasErrors('email');
    }

    public function test_enrollment_is_pending_deduplicated_and_not_paid_automatically(): void
    {
        Notification::fake();
        $training = $this->training(null, 'Comptabilité SYSCOHADA', true, 125);
        $mode = DeliveryMode::create(['name' => 'Présentiel', 'slug' => 'presentiel']);
        $training->deliveryModes()->attach($mode);
        $learner = User::factory()->create();
        $administrator = User::factory()->create();
        $adminRole = Role::create(['name' => 'Administrateur', 'slug' => 'administrateur']);
        $administrator->roles()->attach($adminRole, ['is_primary' => true]);
        $payload = ['delivery_mode_id' => $mode->id, 'terms' => '1'];

        $this->actingAs($learner)->post(route('enrollments.store', $training), $payload)
            ->assertRedirect(route('my-enrollments.index'));
        $this->actingAs($learner)->post(route('enrollments.store', $training), $payload)
            ->assertRedirect(route('my-enrollments.index'));

        $this->assertSame(1, Enrollment::query()->where('user_id', $learner->id)->count());
        $enrollment = Enrollment::query()->where('user_id', $learner->id)->firstOrFail();
        $this->assertSame('pending', $enrollment->status);
        $this->assertSame('125.00', $enrollment->agreed_amount);
        $this->assertSame(0, $enrollment->invoices()->count());
        Notification::assertSentTo($administrator, EnrollmentReceived::class);
    }

    public function test_enrollment_rejects_mode_session_mismatch_and_full_sessions(): void
    {
        $training = $this->training(null, 'Excel avancé', true);
        $online = DeliveryMode::create(['name' => 'En ligne', 'slug' => 'en-ligne']);
        $inPerson = DeliveryMode::create(['name' => 'Présentiel', 'slug' => 'presentiel']);
        $training->deliveryModes()->attach([$online->id, $inPerson->id]);
        $session = TrainingSession::create([
            'training_id' => $training->id,
            'delivery_mode_id' => $online->id,
            'name' => 'Session complète',
            'status' => 'scheduled',
            'capacity' => 1,
            'starts_at' => now()->addWeek(),
        ]);
        $learner = User::factory()->create();
        $payload = ['delivery_mode_id' => $inPerson->id, 'training_session_id' => $session->id, 'terms' => '1'];

        $this->actingAs($learner)->from(route('enrollments.create', $training))
            ->post(route('enrollments.store', $training), $payload)
            ->assertSessionHasErrors('delivery_mode_id');

        $payload['delivery_mode_id'] = $online->id;
        Enrollment::create([
            'user_id' => User::factory()->create()->id,
            'training_id' => $training->id,
            'training_session_id' => $session->id,
            'delivery_mode_id' => $online->id,
            'registration_key' => hash('sha256', 'occupied'),
            'status' => 'confirmed',
        ]);
        $this->actingAs($learner)->from(route('enrollments.create', $training))
            ->post(route('enrollments.store', $training), $payload)
            ->assertSessionHasErrors('training_session_id');
    }

    public function test_learner_can_only_see_their_own_enrollments(): void
    {
        $training = $this->training(null, 'Développement web', true);
        $mode = DeliveryMode::create(['name' => 'À distance', 'slug' => 'a-distance']);
        $training->deliveryModes()->attach($mode);
        $learner = User::factory()->create();
        $otherLearner = User::factory()->create();

        $this->actingAs($learner)->post(route('enrollments.store', $training), [
            'delivery_mode_id' => $mode->id,
            'terms' => '1',
        ]);
        $otherTraining = $this->training(null, 'Comptabilité générale', true);
        $otherTraining->deliveryModes()->attach($mode);
        $this->actingAs($otherLearner)->post(route('enrollments.store', $otherTraining), [
            'delivery_mode_id' => $mode->id,
            'terms' => '1',
        ]);

        $this->actingAs($learner)->get(route('my-enrollments.index'))
            ->assertOk()
            ->assertSee('Développement web')
            ->assertDontSee('Comptabilité générale');
    }

    private function training(?Category $category, string $title, bool $isPublished, ?float $price = null): Training
    {
        return Training::create([
            'category_id' => $category?->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'summary' => 'Formation pratique pour les professionnels.',
            'level' => 'debutant',
            'price' => $price,
            'currency' => 'USD',
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
        ]);
    }
}
