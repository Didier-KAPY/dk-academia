<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('settings.manage') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:160'],
            'slogan' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],
            'default_currency' => ['required', 'in:CDF,USD,EUR'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Le nom de l’organisation est obligatoire.',
            'contact_email.email' => 'Saisissez une adresse e-mail valide.',
            'website.url' => 'Saisissez une adresse de site valide, avec https://.',
            'default_currency.in' => 'La devise par défaut doit être CDF, USD ou EUR.',
        ];
    }
}
