<?php

namespace Tests\Feature;

use App\Models\NewsPost;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SitePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_editor_can_create_update_and_publish_news_with_an_image(): void
    {
        Storage::fake('public');
        $editor = $this->userWithContentPermission();

        $this->actingAs($editor)
            ->get(route('management.content.news.create'))
            ->assertOk()
            ->assertSee('Nouvelle actualité');

        $this->post(route('management.content.news.store'), [
            'title' => 'Atelier numérique',
            'slug' => 'atelier-numerique',
            'category' => 'Formation',
            'excerpt' => 'Un atelier pratique.',
            'body' => "Bienvenue.\n\n<script>alert(1)</script>",
            'image' => UploadedFile::fake()->image('atelier.png'),
            'is_published' => '1',
            'published_at' => now()->subMinute()->format('Y-m-d\\TH:i'),
        ])
            ->assertRedirect(route('management.content.news.index'));

        $post = NewsPost::query()->where('slug', 'atelier-numerique')->firstOrFail();
        Storage::disk('public')->assertExists($post->image_path);
        $this->assertSame($editor->id, $post->created_by);
        $this->assertTrue($post->is_published);

        $this->get(route('management.content.news.edit', $post))
            ->assertOk()
            ->assertSee('Atelier numérique')
            ->assertSee('Image actuelle');

        $this->get(route('news.show', $post))
            ->assertOk()
            ->assertSee('<script>alert(1)</script>');

        $this->put(route('management.content.news.update', $post), [
            'title' => 'Atelier numérique mis à jour',
            'slug' => 'atelier-numerique-mis-a-jour',
            'category' => 'Événement',
            'excerpt' => 'Résumé modifié.',
            'body' => 'Contenu mis à jour.',
            'is_published' => '1',
            'published_at' => now()->subMinute()->format('Y-m-d\\TH:i'),
        ])->assertRedirect(route('management.content.news.index'));

        $post->refresh();
        $this->assertSame('atelier-numerique-mis-a-jour', $post->slug);
        $this->assertSame('Contenu mis à jour.', $post->body);
        Storage::disk('public')->assertExists($post->image_path);

        $this->get(route('news.show', $post))
            ->assertOk()
            ->assertSee('Atelier numérique mis à jour')
            ->assertSee('Contenu mis à jour.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $editor->id,
            'event' => 'news_post.created',
            'subject_id' => $post->id,
        ]);
    }

    public function test_draft_news_is_hidden_until_an_editor_publishes_it(): void
    {
        $editor = $this->userWithContentPermission();
        $post = NewsPost::create([
            'created_by' => $editor->id,
            'title' => 'Annonce en brouillon',
            'slug' => 'annonce-en-brouillon',
            'body' => 'Contenu à relire.',
        ]);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertDontSee('Annonce en brouillon');

        $this->actingAs($editor)
            ->patch(route('management.content.news.publication', $post))
            ->assertRedirect(route('management.content.news.index'));

        $post->refresh();
        $this->assertTrue($post->is_published);
        $this->assertNotNull($post->published_at);

        $this->get(route('news.show', $post))
            ->assertOk()
            ->assertSee('Annonce en brouillon');
    }

    public function test_editor_can_update_about_and_mission_pages_and_public_pages_show_saved_text(): void
    {
        $editor = $this->userWithContentPermission();

        $this->actingAs($editor)
            ->get(route('management.content.pages.index'))
            ->assertOk()
            ->assertSee('À propos')
            ->assertSee('Mission et vision');

        $this->get(route('management.content.pages.edit', 'about'))
            ->assertOk()
            ->assertSee('Modifier : À propos');

        $this->put(route('management.content.pages.update', 'about'), [
            'eyebrow' => 'NOTRE ÉQUIPE',
            'title' => 'Une nouvelle présentation',
            'introduction' => 'Texte de présentation mis à jour.',
            'body' => '<script>alert(1)</script>'."\n\nNous formons les professionnels.",
            'secondary_title' => '',
            'secondary_body' => '',
        ])->assertRedirect(route('management.content.pages.index'));

        $this->assertDatabaseHas('site_pages', [
            'slug' => 'about',
            'title' => 'Une nouvelle présentation',
            'updated_by' => $editor->id,
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Une nouvelle présentation')
            ->assertSee('<script>alert(1)</script>')
            ->assertSee('Nous formons les professionnels.');

        $this->get(route('mission'))
            ->assertOk()
            ->assertSee('NOTRE MISSION')
            ->assertSee('Notre vision');

        $this->assertInstanceOf(SitePage::class, SitePage::forSlug('mission'));
    }

    public function test_content_management_requires_its_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('management.content.pages.index'))
            ->assertForbidden();

        $this->post(route('management.content.news.store'), [
            'title' => 'Non autorisé',
            'body' => 'Non autorisé',
        ])->assertForbidden();

        $this->assertDatabaseCount('news_posts', 0);
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_news_rejects_an_invalid_image_upload(): void
    {
        Storage::fake('public');
        $editor = $this->userWithContentPermission();

        $this->actingAs($editor)
            ->from(route('management.content.news.create'))
            ->post(route('management.content.news.store'), [
                'title' => 'Article sans image valide',
                'body' => 'Texte de test.',
                'image' => UploadedFile::fake()->create('document.txt', 1, 'text/plain'),
            ])
            ->assertRedirectBackWithErrors(['image']);

        $this->assertDatabaseCount('news_posts', 0);
    }

    private function userWithContentPermission(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Éditeur du site', 'slug' => 'editeur-site']);
        $permission = Permission::create(['name' => 'Gérer les contenus du site', 'slug' => 'site-content.manage']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }
}
