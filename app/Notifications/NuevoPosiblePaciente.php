<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevoPosiblePaciente extends Notification
{
    use Queueable;

    protected $lead;

    public function __construct($lead)
    {
        $this->lead = $lead;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        // Mapeo para que en el correo se vea profesional
        $especialidades = [
            'addictions' => 'Adicciones',
            'anxiety_stress' => 'Ansiedad y Estrés',
            'autism_developmental_disorders' => 'Autismo y Trastornos del Desarrollo',
            'depression_mood_disorders' => 'Depresión y Trastornos del Estado de Ánimo',
            'grief_loss' => 'Duelo y Pérdida',
            'schizophrenia_psychotic_disorders' => 'Esquizofrenia y Trastornos Psicóticos',
            'psychological_assessment' => 'Evaluación Psicológica',
            'neuropsychology' => 'Neuropsicología',
            'child_adolescent_psychiatry' => 'Paidopsiquiatría',
            'clinical_psychology' => 'Psicología Clínica',
            'community_psychology' => 'Psicología Comunitaria',
            'educational_psychology' => 'Psicología Educativa',
            'forensic_psychology' => 'Psicología Forense',
            'child_adolescent_psychology' => 'Psicología Infantil y Adolescente',
            'industrial_organizational_psychology' => 'Psicología Laboral y Organizacional',
            'positive_psychology' => 'Psicología Positiva',
            'social_psychology' => 'Psicología Social',
            'psychotherapy' => 'Psicoterapia',
            'general_psychiatry' => 'Psiquiatría General',
            'geriatric_psychiatry' => 'Psiquiatría Geriátrica',
            'sexology' => 'Sexología',
            'thanatology' => 'Tanatología',
            'couple_family_therapy' => 'Terapia de Pareja y Familia',
            'eating_disorders' => 'Trastornos de la Conducta Alimentaria',
            'personality_disorders' => 'Trastornos de Personalidad',
            'sleep_disorders' => 'Trastornos del Sueño',
            'ocd_related_disorders' => 'Trastornos Obsesivo-Compulsivos (TOC)',
            'trauma_ptsd' => 'Trauma y Estrés Postraumático',
        ];

        // Obtenemos la traducción o el valor original si no existe en el mapa
        $especialidadTraducida = $this->lead->lead_type === 'package'
            ? 'Paquete de sesiones: ' . ($this->lead->package_name ?? 'Paquete MindMeet')
            : ($especialidades[$this->lead->tipo_sesion] ?? 'Consulta General');

        return (new MailMessage)
            ->subject('🔔 Tienes un nuevo posible paciente en MindMeet')        
            ->view('email.newPotencialPatient', [
                'consulta' => $this->lead, 
                'especialidad' => $especialidadTraducida,
                'user' => $notifiable
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $leadLabel = $this->lead->lead_type === 'package'
            ? 'el paquete ' . ($this->lead->package_name ?? 'de sesiones')
            : $this->lead->tipo_sesion;

        return [
            'title' => 'Nuevo lead recibido',
            'body' => "{$this->lead->nombre} mostro interes en {$leadLabel}.",
            'action_url' => rtrim(config('app.front_url_user') ?: config('app.front_url'), '/') . '/leads',
            'action_label' => 'Ver lead',
            'kind' => 'lead-created',
            'lead_email' => $this->lead->email,
            'lead_phone' => $this->lead->telefono,
            'lead_type' => $this->lead->lead_type,
            'package_name' => $this->lead->package_name,
        ];
    }
}
