<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('trainings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:100000',
                Rule::unique('training_modules', 'sort_order')
                    ->where(fn ($query) => $query->where('training_id', $this->route('training')?->id ?? $this->route('module')?->training_id))
                    ->ignore($this->route('module')),
            ],
        ];
    }
}
