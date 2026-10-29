<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEvaluationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $questions = array_filter(
            (array) $this->input('questions', []),
            fn (array $question): bool => trim((string) ($question['prompt'] ?? '')) !== '',
        );

        foreach ($questions as $index => $question) {
            $options = array_values(array_filter(
                (array) ($question['options'] ?? []),
                fn (mixed $option): bool => is_string($option) && trim($option) !== '',
            ));
            $questions[$index]['options'] = $options;
        }

        $this->merge(['questions' => $questions]);
    }

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
            'training_session_id' => ['nullable', 'integer', 'exists:training_sessions,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'in:quiz,exam,exercise'],
            'passing_points' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'is_published' => ['sometimes', 'boolean'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.prompt' => ['required', 'string', 'max:5000'],
            'questions.*.question_type' => ['required', 'in:multiple_choice,short_answer'],
            'questions.*.options' => ['nullable', 'array', 'max:20'],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            'questions.*.answer' => ['required', 'string', 'max:5000'],
            'questions.*.points' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('questions', []) as $index => $question) {
                if (($question['question_type'] ?? null) === 'multiple_choice') {
                    $options = $question['options'] ?? [];
                    if (count($options) < 2) {
                        $validator->errors()->add("questions.$index.options", 'Une question à choix multiples doit proposer au moins deux réponses.');
                    } elseif (! in_array($question['answer'] ?? null, $options, true)) {
                        $validator->errors()->add("questions.$index.answer", 'La bonne réponse doit faire partie des choix proposés.');
                    }
                }
            }
        }];
    }
}
