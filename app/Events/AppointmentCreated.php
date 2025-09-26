<?php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
class AppointmentCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $appointmentId,
        public int $psychologistId, // user_id del Minder
        public int $patientId
    ) {}
    public function broadcastOn() {
        return new PrivateChannel("psychologist.{$this->psychologistId}");
    }
    public function broadcastAs() { return 'appointment.created'; }
    public function broadcastWith() {
        return [
            'appointment_id' => $this->appointmentId,
            'patient_id' => $this->patientId,
            'at' => now()->toIso8601String(),
        ];
    }
}