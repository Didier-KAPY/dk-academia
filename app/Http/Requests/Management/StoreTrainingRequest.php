<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['objectives', 'syllabus'] as $field) {
            $this->merge([$field => array_values(array_filter(
                (array) $this->input($field, []),
                fn (mixed $value): bool => is_string($value) && trim($value) !== '',
            ))]);
        }
    }

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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('trainings', 'slug')],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:50000'],
            'objectives' => ['nullable', 'array', 'max:30'],
            'objectives.*' => ['required', 'string', 'max:500'],
            'target_audience' => ['nullable', 'string', 'max:5000'],
            'level' => ['required', Rule::in(['debutant', 'intermediaire', 'avance', 'expert'])],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'syllabus' => ['nullable', 'array', 'max:100'],
            'syllabus.*' => ['required', 'string', 'max:500'],
            'prerequisites' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'delivery_modes' => ['required', 'array', 'min:1'],
            'delivery_modes.*' => ['integer', 'distinct', 'exists:delivery_modes,id'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
