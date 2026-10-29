<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\UpdateAppSettingsRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = AppSetting::query()->first() ?? new AppSetting([
            'company_name' => 'DK ACADEMIA',
            'slogan' => 'Apprendre, maîtriser, réussir.',
            'default_currency' => 'USD',
        ]);

        return view('management.settings.index', compact('settings'));
    }

    public function update(UpdateAppSettingsRequest $request): RedirectResponse
    {
        $settings = AppSetting::query()->firstOrNew(['id' => 1]);
        $settings->fill($request->validated());
        $settings->updated_by = $request->user()->id;
        $settings->save();

        return redirect()->route('management.settings.index')->with('status', 'Les paramètres généraux ont été enregistrés.');
    }
}
