<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_settings_permission_can_view_and_update_general_settings(): void
    {
        $administrator = $this->userWithSettingsPermission();

        $this->actingAs($administrator)
            ->get(route('management.settings.index'))
            ->assertOk()
            ->assertSee('Paramètres généraux')
            ->assertSee('DK ACADEMIA');

        $this->put(route('management.settings.update'), [
            'company_name' => 'DK Academia RDC',
            'slogan' => 'Apprendre et réussir',
            'contact_email' => 'contact@example.cd',
            'phone' => '+243 000 000 000',
            'address' => 'Kinshasa, RDC',
            'website' => 'https://example.cd',
            'default_currency' => 'CDF',
        ])->assertRedirect(route('management.settings.index'));

        $this->assertDatabaseHas('app_settings', [
            'company_name' => 'DK Academia RDC',
            'contact_email' => 'contact@example.cd',
            'default_currency' => 'CDF',
            'updated_by' => $administrator->id,
        ]);
        $this->assertSame(1, AppSetting::query()->count());

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('DK Academia RDC')
            ->assertSee('contact@example.cd')
            ->assertSee('Apprendre et réussir');
    }

    public function test_general_settings_require_settings_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('management.settings.index'))
            ->assertForbidden();

        $this->put(route('management.settings.update'), [
            'company_name' => 'Intrusion',
            'slogan' => 'Nope',
            'default_currency' => 'USD',
        ])->assertForbidden();
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $administrator = $this->userWithSettingsPermission();

        $this->actingAs($administrator)
            ->from(route('management.settings.index'))
            ->put(route('management.settings.update'), [
                'company_name' => '',
                'slogan' => 'Slogan',
                'contact_email' => 'not-an-email',
                'website' => 'not-a-url',
                'default_currency' => 'GBP',
            ])
            ->assertRedirectBackWithErrors(['company_name', 'contact_email', 'website', 'default_currency']);

        $this->assertDatabaseCount('app_settings', 0);
    }

    private function userWithSettingsPermission(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Administrateur', 'slug' => 'administrateur']);
        $permission = Permission::create(['name' => 'Gérer les paramètres généraux', 'slug' => 'settings.manage']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }
}
