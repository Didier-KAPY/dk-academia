<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('pedagogy.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'training_id' => ['required', 'integer', 'exists:trainings,id'],
            'delivery_mode_id' => ['nullable', 'integer', 'exists:delivery_modes,id'],
            'instructor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'venue' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:2000'],
        ];
    }
}
