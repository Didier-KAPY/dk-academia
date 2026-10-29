<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
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
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'attendees' => ['sometimes', 'array', 'max:1000'],
            'attendees.*.status' => ['required', 'string', 'in:present,absent,late,excused'],
            'attendees.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
