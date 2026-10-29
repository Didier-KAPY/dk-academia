<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('site-content.manage') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'eyebrow' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:200'],
            'introduction' => ['required', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:20000'],
            'secondary_title' => ['nullable', 'string', 'max:200'],
            'secondary_body' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
