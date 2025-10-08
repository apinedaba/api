<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\InvalidGoogleTokenException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAppointmentToGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Propiedades para guardar la información que necesitamos
    public $appointment;
    public $user;
    public $action; // 'create', 'update', o 'delete'
    public $googleEventIdToDelete; // Para guardar el ID si la cita se borra

    /**
     * Create a new job instance.
     *
     * @param \App\Models\Appointment $appointment La cita de nuestra base de datos.
     * @param \App\Models\User $user El profesional (dueño del calendario).
     * @param string $action La operación a realizar.
     */
    public function __construct(Appointment $appointment, User $user, string $action)
    {
        $this->appointment = $appointment;
        $this->user = $user;
        $this->action = $action;

        // Si la acción es 'delete', guardamos el ID de Google antes de que la cita se elimine.
        if ($this->action === 'delete') {
            $this->googleEventIdToDelete = $appointment->google_event_id;
        }
    }

    /**
     * Execute the job.
     *
     * Se ejecuta cuando el "worker" de la cola procesa este job.
     * @param \App\Services\GoogleCalendarService $googleCalendarService El servicio se inyecta automáticamente.
     */
    public function handle(GoogleCalendarService $googleCalendarService): void
    {
        // Si el usuario no tiene una cuenta de Google conectada, no hacemos nada.
        if (!$this->user->googleAccount) {
            return;
        }

        try {
            // Usamos un 'match' para ejecutar el código correcto según la acción.
            match ($this->action) {
                'create' => $googleCalendarService->createEvent($this->appointment, $this->user),
                'update' => $googleCalendarService->updateEvent($this->appointment, $this->user),
                'delete' => $googleCalendarService->deleteEvent($this->googleEventIdToDelete, $this->user),
            };
        } catch (InvalidGoogleTokenException $e) {
            // Si el token fue revocado, lo borramos y registramos el error.
            $this->user->googleAccount->delete();
            Log::warning('Token de Google inválido para usuario (job): ' . $this->user->id . '. Se eliminó la conexión.');
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, registramos el mensaje y dejamos que la cola lo reintente.
            Log::error('Falló el job de SyncAppointmentToGoogleCalendar: ' . $e->getMessage());
            // Relanzamos la excepción para que el job falle y la cola lo pueda reintentar.
            $this->fail($e);
        }
    }
}
