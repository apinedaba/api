<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Traits\NotificaInternamente;

class NotificacionPsicologo extends Notification
{
    use Queueable, NotificaInternamente;

    protected $patient;
    protected $user;

    public function __construct($patient, $user)
    {
        $this->patient = $patient;
        $this->user = $user;
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

        $asunto = '¡Un paciente esta interesado en ' . $this->user->name . '!';
        $cuerpo = "Nuevo contacto de paciente:\n\nNombre: {$this->patient->nombre}\nCorreo: {$this->patient->email}\nTelefono: {$this->patient->telefono}\nMotivo: {$this->patient->motivo}";
        $this->enviarNotificacionInterna($this->patient, $asunto, $cuerpo);

        return (new MailMessage)
            ->subject('¡Un paciente esta interesado en ti!')
            ->view('email.newPotencialPatient', [
                'consulta' => $this->patient,
                'especialidad' => $especialidadTraducida,
                'user' => $this->user
            ]);
    }
}
