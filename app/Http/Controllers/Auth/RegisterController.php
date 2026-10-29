<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $role = Role::firstOrCreate(
                ['slug' => 'apprenant'],
                [
                    'name' => 'Apprenant',
                    'description' => 'Accès personnel aux formations et aux ressources pédagogiques.',
                ],
            );

            $learningPermission = Permission::firstOrCreate(
                ['slug' => 'learning.access'],
                [
                    'name' => 'Accéder aux formations',
                    'description' => 'Consulter les formations et ressources attribuées.',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$learningPermission->getKey()]);

            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            $user->roles()->attach($role, ['is_primary' => true]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
