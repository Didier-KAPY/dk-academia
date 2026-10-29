<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_listing_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('management.users.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_search_users_and_inspect_roles_and_permissions(): void
    {
        $administrator = $this->userWithPermissions('admin@example.com', ['users.view', 'users.manage', 'roles.manage'], 'administrateur');
        $target = User::factory()->create(['name' => 'Amina Kalala', 'email' => 'amina@example.com']);
        $learnerRole = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);
        $learningPermission = Permission::create(['name' => 'Accéder aux formations', 'slug' => 'learning.access']);
        $learnerRole->permissions()->attach($learningPermission);
        $target->roles()->attach($learnerRole, ['is_primary' => true]);

        $this->actingAs($administrator)
            ->get(route('management.users.index', ['q' => 'amina']))
            ->assertOk()
            ->assertSee('Amina Kalala')
            ->assertSee('amina@example.com')
            ->assertDontSee('admin@example.com');

        $this->get(route('management.roles.index'))
            ->assertOk()
            ->assertSee('Rôles et permissions')
            ->assertSee('Accéder aux formations')
            ->assertSee('Apprenant');
    }

    public function test_administrator_can_update_a_users_roles_and_primary_role(): void
    {
        $administrator = $this->userWithPermissions('admin@example.com', ['users.manage'], 'administrateur');
        $target = User::factory()->create();
        $learnerRole = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);
        $trainerRole = Role::create(['name' => 'Formateur', 'slug' => 'formateur']);
        $target->roles()->attach($learnerRole, ['is_primary' => true]);

        $this->actingAs($administrator)
            ->put(route('management.users.roles.update', $target), [
                'role_ids' => [$learnerRole->id, $trainerRole->id],
                'primary_role_id' => $trainerRole->id,
            ])
            ->assertRedirect(route('management.users.roles.edit', $target));

        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'role_id' => $learnerRole->id,
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'role_id' => $trainerRole->id,
            'is_primary' => true,
        ]);
    }

    public function test_primary_role_must_be_selected_from_assigned_roles(): void
    {
        $administrator = $this->userWithPermissions('admin@example.com', ['users.manage'], 'administrateur');
        $target = User::factory()->create();
        $learnerRole = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);
        $trainerRole = Role::create(['name' => 'Formateur', 'slug' => 'formateur']);
        $target->roles()->attach($learnerRole, ['is_primary' => true]);

        $this->actingAs($administrator)
            ->from(route('management.users.roles.edit', $target))
            ->put(route('management.users.roles.update', $target), [
                'role_ids' => [$trainerRole->id],
                'primary_role_id' => $learnerRole->id,
            ])
            ->assertRedirectBackWithErrors(['primary_role_id' => 'Le rôle principal doit faire partie des rôles attribués.']);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'role_id' => $learnerRole->id,
            'is_primary' => true,
        ]);
    }

    public function test_administrator_cannot_remove_own_admin_role(): void
    {
        $administrator = $this->userWithPermissions('admin@example.com', ['users.manage'], 'administrateur');
        $learnerRole = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);
        $adminRole = $administrator->roles()->firstOrFail();

        $this->actingAs($administrator)
            ->from(route('management.users.roles.edit', $administrator))
            ->put(route('management.users.roles.update', $administrator), [
                'role_ids' => [$learnerRole->id],
                'primary_role_id' => $learnerRole->id,
            ])
            ->assertRedirectBackWithErrors(['role_ids' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.']);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $administrator->id,
            'role_id' => $adminRole->id,
            'is_primary' => true,
        ]);
    }

    public function test_manager_cannot_demote_the_last_administrator(): void
    {
        $manager = $this->userWithPermissions('manager@example.com', ['users.manage'], 'gestionnaire-delegue');
        $administrator = User::factory()->create();
        $adminRole = Role::create(['name' => 'Administrateur', 'slug' => 'administrateur']);
        $administrator->roles()->attach($adminRole, ['is_primary' => true]);
        $learnerRole = Role::create(['name' => 'Apprenant', 'slug' => 'apprenant']);

        $this->actingAs($manager)
            ->from(route('management.users.roles.edit', $administrator))
            ->put(route('management.users.roles.update', $administrator), [
                'role_ids' => [$learnerRole->id],
                'primary_role_id' => $learnerRole->id,
            ])
            ->assertRedirectBackWithErrors(['role_ids' => 'Le dernier administrateur ne peut pas être rétrogradé.']);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $administrator->id,
            'role_id' => $adminRole->id,
            'is_primary' => true,
        ]);
    }

    public function test_administrator_can_update_permissions_for_a_role(): void
    {
        $administrator = $this->userWithPermissions('admin@example.com', ['roles.manage'], 'administrateur');
        $role = Role::create(['name' => 'Formateur', 'slug' => 'formateur']);
        $oldPermission = Permission::create(['name' => 'Ancienne permission', 'slug' => 'old.permission']);
        $newPermission = Permission::create(['name' => 'Nouvelle permission', 'slug' => 'new.permission']);
        $role->permissions()->attach($oldPermission);

        $this->actingAs($administrator)
            ->put(route('management.roles.permissions.update', $role), [
                'permission_ids' => [$newPermission->id],
            ])
            ->assertRedirect(route('management.roles.index'));

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $oldPermission->id,
        ]);
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $newPermission->id,
        ]);
    }

    public function test_administrator_role_cannot_lose_access_management_permissions(): void
    {
        $administrator = $this->userWithPermissions('admin@example.com', ['users.view', 'users.manage', 'roles.manage'], 'administrateur');
        $role = $administrator->roles()->firstOrFail();
        $permissionIds = Permission::query()
            ->whereIn('slug', ['users.view', 'users.manage'])
            ->pluck('id')
            ->all();

        $this->actingAs($administrator)
            ->from(route('management.roles.permissions.edit', $role))
            ->put(route('management.roles.permissions.update', $role), [
                'permission_ids' => $permissionIds,
            ])
            ->assertRedirectBackWithErrors(['permission_ids' => 'Le rôle administrateur doit conserver les permissions de gestion des accès.']);

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => Permission::query()->where('slug', 'roles.manage')->value('id'),
        ]);
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(string $email, array $permissions, string $roleSlug): User
    {
        $user = User::factory()->create(['email' => $email]);
        $role = Role::create([
            'name' => ucfirst($roleSlug),
            'slug' => $roleSlug,
        ]);

        foreach ($permissions as $permissionSlug) {
            $permission = Permission::create([
                'name' => $permissionSlug,
                'slug' => $permissionSlug,
            ]);
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }
}
