<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'delivery_mode_id' => ['required', 'integer', 'exists:delivery_modes,id'],
            'training_session_id' => ['nullable', 'integer', 'exists:training_sessions,id'],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_mode_id.required' => 'Veuillez choisir une modalité de formation.',
            'training_session_id.exists' => 'La session choisie n’est pas disponible.',
            'terms.accepted' => 'Vous devez accepter les conditions d’inscription.',
        ];
    }
}
