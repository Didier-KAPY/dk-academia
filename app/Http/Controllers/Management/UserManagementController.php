<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\UpdateRolePermissionsRequest;
use App\Http\Requests\Management\UpdateUserRolesRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $users = User::query()
            ->with('roles:id,name,slug')
            ->withCount('roles')
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $userQuery) use ($search): void {
                $userQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('management.users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function editRoles(User $user): View
    {
        $user->load('roles:id,name,slug');

        return view('management.users.edit-roles', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'primaryRoleId' => $user->roles->firstWhere('pivot.is_primary', true)?->id ?? $user->roles->first()?->id,
        ]);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $roleIds = collect($request->validated('role_ids'))->map(fn (int|string $roleId): int => (int) $roleId);
        $primaryRoleId = (int) $request->validated('primary_role_id');

        DB::transaction(function () use ($request, $user, $roleIds, $primaryRoleId): void {
            $administratorRole = Role::query()->where('slug', 'administrateur')->lockForUpdate()->firstOrFail();
            $userHasAdministratorRole = $user->roles()->whereKey($administratorRole->getKey())->exists();
            $removesAdministratorRole = $userHasAdministratorRole && ! $roleIds->contains($administratorRole->getKey());

            if ($user->is($request->user()) && ! $roleIds->contains($administratorRole->getKey())) {
                throw ValidationException::withMessages([
                    'role_ids' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.',
                ]);
            }

            if ($removesAdministratorRole) {
                $otherAdministratorCount = $administratorRole->users()
                    ->where('users.id', '!=', $user->getKey())
                    ->count();

                if ($otherAdministratorCount === 0) {
                    throw ValidationException::withMessages([
                        'role_ids' => 'Le dernier administrateur ne peut pas être rétrogradé.',
                    ]);
                }
            }

            $assignments = $roleIds->mapWithKeys(fn (int $roleId): array => [
                $roleId => ['is_primary' => $roleId === $primaryRoleId],
            ])->all();

            $user->roles()->sync($assignments);
        });

        return redirect()
            ->route('management.users.roles.edit', $user)
            ->with('status', 'Les rôles de cet utilisateur ont été mis à jour.');
    }

    public function roles(): View
    {
        return view('management.roles.index', [
            'roles' => Role::query()
                ->withCount('users')
                ->with('permissions:id,name,slug')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function editPermissions(Role $role): View
    {
        $role->load('permissions:id,name,slug');

        return view('management.roles.edit-permissions', [
            'role' => $role,
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $permissionIds = collect($request->validated('permission_ids'))->map(fn (int|string $permissionId): int => (int) $permissionId);

        DB::transaction(function () use ($role, $permissionIds): void {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->getKey());

            if ($lockedRole->slug === 'administrateur') {
                $requiredPermissionIds = Permission::query()
                    ->whereIn('slug', ['users.view', 'users.manage', 'roles.manage'])
                    ->pluck('id');

                if ($requiredPermissionIds->diff($permissionIds)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'permission_ids' => 'Le rôle administrateur doit conserver les permissions de gestion des accès.',
                    ]);
                }
            }

            $lockedRole->permissions()->sync($permissionIds->all());
        });

        return redirect()
            ->route('management.roles.index')
            ->with('status', 'Les permissions du rôle ont été mises à jour.');
    }
}
