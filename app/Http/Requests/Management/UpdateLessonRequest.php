<?php

namespace App\Http\Requests\Management;

use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonRequest extends FormRequest
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
        $routeLesson = $this->route('lesson');
        $lesson = $routeLesson instanceof Lesson
            ? $routeLesson
            : Lesson::query()->findOrFail($routeLesson);

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('lessons', 'slug')->where(fn ($query) => $query->where('training_module_id', $lesson->training_module_id))->ignore($lesson)],
            'content' => ['nullable', 'string', 'max:100000'],
            'resource_path' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
