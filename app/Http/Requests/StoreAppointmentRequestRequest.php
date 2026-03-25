<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequestRequest extends FormRequest
{
    /**
     * El paciente autenticado siempre puede crear solicitudes.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'      => ['required', 'integer', 'exists:patients,id'],
            'psychologist_id' => ['required', 'integer', 'exists:users,id'],
            'date'            => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time'            => ['required', 'date_format:H:i'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.exists'      => 'El paciente indicado no existe.',
            'psychologist_id.exists' => 'El psicólogo indicado no existe.',
            'date.after_or_equal'    => 'La fecha de la solicitud no puede ser en el pasado.',
            'date.date_format'       => 'El formato de fecha debe ser YYYY-MM-DD.',
            'time.date_format'       => 'El formato de hora debe ser HH:MM.',
        ];
    }
}
