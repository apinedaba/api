<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo psicólogos autenticados pueden crear solicitudes de campaña.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'marketing_package_id' => ['required', 'integer', 'exists:marketing_packages,id'],

            // Brief inicial de audiencia. Superadmin puede completar detalles operativos.
            'target_audience'                   => ['required', 'array'],
            'target_audience.age_range'         => ['nullable', 'string', 'max:50'],
            'target_audience.gender'            => ['nullable', 'string', Rule::in(['femenino', 'masculino', 'todos'])],
            'target_audience.interests'         => ['required', 'array', 'min:1'],
            'target_audience.interests.*'       => ['string', 'max:100'],
            'target_audience.specialty_focus'   => ['nullable', 'string', 'max:150'],

            // Ubicaciones de la campaña - REQUERIDO AL MENOS UNA
            'locations'     => ['required', 'array', 'min:1'],
            'locations.*'   => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'marketing_package_id.required' => 'Debes seleccionar un paquete de marketing.',
            'marketing_package_id.exists'   => 'El paquete de marketing seleccionado no existe.',
            'target_audience.required'      => 'Debes proporcionar el brief de audiencia.',
            'target_audience.array'         => 'El brief de audiencia debe ser un objeto válido.',
            'target_audience.gender.in'         => 'El género debe ser: femenino, masculino, o todos.',
            'target_audience.interests.required' => 'Debes agregar al menos una condición.',
            'target_audience.interests.min'     => 'Debes agregar al menos una condición.',
            'locations.required'            => 'Debes agregar al menos una ubicación.',
            'locations.min'                 => 'Debes agregar al menos una ubicación.',
            'locations.array'               => 'Las ubicaciones deben ser una lista válida.',
        ];
    }

    /**
     * Validaciones personalizadas adicionales
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar formato de edad (ej. "25-65")
            if ($this->filled('target_audience.age_range')) {
                $ageRange = $this->input('target_audience.age_range');
                if (!preg_match('/^\d{1,3}-\d{1,3}$/', $ageRange)) {
                    $validator->errors()->add('target_audience.age_range', 'Formato de edad inválido. Usa: 25-65');
                } else {
                    $ages = explode('-', $ageRange);
                    $min = (int)$ages[0];
                    $max = (int)$ages[1];

                    if ($min < 1 || $max > 120) {
                        $validator->errors()->add('target_audience.age_range', 'El rango de edad debe estar entre 1-120.');
                    }

                    if ($min >= $max) {
                        $validator->errors()->add('target_audience.age_range', 'La edad mínima debe ser menor que la máxima.');
                    }
                }
            }

            // Validar que el paquete exista y esté activo
            if ($this->has('marketing_package_id')) {
                $package = \App\Models\MarketingPackage::find($this->input('marketing_package_id'));
                if ($package && !$package->is_active) {
                    $validator->errors()->add('marketing_package_id', 'Este paquete no está disponible en este momento.');
                }
            }
        });
    }
}
