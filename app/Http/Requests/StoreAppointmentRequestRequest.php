<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'psychologist_id' => ['required', 'integer', 'exists:users,id'],
            'date'            => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time'            => ['required', 'date_format:H:i'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled(['date', 'time'])) {
                return;
            }

            $requestedAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                "{$this->input('date')} {$this->input('time')}",
                config('app.timezone')
            );

            if ($requestedAt->lessThanOrEqualTo(now())) {
                $validator->errors()->add('time', 'El horario de la solicitud debe ser posterior al momento actual.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'psychologist_id.exists' => 'El psicólogo indicado no existe.',
            'date.after_or_equal'    => 'La fecha de la solicitud no puede ser en el pasado.',
            'date.date_format'       => 'El formato de fecha debe ser YYYY-MM-DD.',
            'time.date_format'       => 'El formato de hora debe ser HH:MM.',
        ];
    }
}
