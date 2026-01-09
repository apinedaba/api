<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Traits\NotificaInternamente;

class NuevoContacto extends Notification
{
    use Queueable, NotificaInternamente;

    protected $patient;

    public function __construct($patient)
    {
        $this->patient = $patient;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
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

        $especialidadTraducida = $especialidades[$this->patient->tipo_sesion] ?? $this->patient->tipo_sesion;

        $asunto = 'Nuevo contacto para dar información';
        $cuerpo = "Nuevo contacto de paciente:\n\nNombre: {$this->patient->name}\nCorreo: {$this->patient->email}";
        $this->enviarNotificacionInterna($this->patient, $asunto, $cuerpo);

        return (new MailMessage)
            ->subject('¡Bienvenido(a) a MindMeet!')
            ->view('email.newContact', [
                'consulta' => $this->patient,
                'especialidad' => $especialidadTraducida 
            ]);
    }
}
