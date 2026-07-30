<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Profile;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class SendDailyAppointmentsWhatsApp extends Command
{
    protected $signature = 'appointments:send-daily-whatsapp
        {--date= : Fecha a procesar en formato Y-m-d}
        {--dry-run : Muestra los destinatarios sin enviar mensajes}';

    protected $description = 'Envia por WhatsApp a cada psicologo el resumen de sus citas del dia';

    public function handle(WhatsAppService $whatsApp): int
    {
        $timezone = config('app.timezone', 'America/Mexico_City');

        try {
            $date = $this->option('date')
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'), $timezone)
                : CarbonImmutable::now($timezone)->startOfDay();
        } catch (Throwable) {
            $this->error('La fecha debe tener el formato Y-m-d.');

            return self::INVALID;
        }

        $start = $date->startOfDay();
        $end = $start->addDay();
        $template = $whatsApp->templateName('daily_appointments');
        $appointmentsByProfessional = $this->appointmentsFor($start, $end)->groupBy('user');
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($appointmentsByProfessional as $appointments) {
            /** @var Appointment $first */
            $first = $appointments->first();
            $professional = $first->getRelation('user');

            if (! $professional) {
                $skipped++;
                continue;
            }

            $contact = $professional->contacto ?? [];
            $profile = Profile::query()->where('user_id', $professional->id)->latest('id')->first();
            $phone = $this->professionalPhone($contact, $profile);
            $publicName = data_get($contact, 'publicname')
                ?: data_get($contact, 'publicName')
                ?: $profile?->publicName
                ?: $professional->name;
            $publicName = self::sanitizeTemplateText((string) $publicName);
            $appointmentLines = $appointments
                ->map(function (Appointment $appointment) use ($timezone): ?string {
                    $patientName = $appointment->getRelation('patient')?->name;

                    if (! $patientName) {
                        return null;
                    }

                    $start = CarbonImmutable::parse($appointment->start)->timezone($timezone);

                    return self::formatAppointmentLine($patientName, $start);
                })
                ->filter()
                ->values()
                ->all();

            if (! $phone || $appointmentLines === []) {
                $this->warn("Omitido {$professional->name}: sin WhatsApp o pacientes validos.");
                $skipped++;
                continue;
            }

            if ($this->alreadySent($professional->id, $template, $start, $end)) {
                $this->line("Omitido {$professional->name}: el resumen ya fue enviado.");
                $skipped++;
                continue;
            }

            $count = $appointments->count();
            // Meta no permite saltos de linea ni tabuladores dentro de los
            // parametros de una plantilla de WhatsApp.
            $schedule = implode(' • ', $appointmentLines);

            if ($this->option('dry-run')) {
                $this->line("{$publicName} | {$phone} | {$count}\n{$schedule}");
                continue;
            }

            try {
                $result = $whatsApp->sendTemplateWithComponents(
                    $phone,
                    $template,
                    [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'text', 'text' => trim((string) $publicName)],
                            ],
                        ],
                        $whatsApp->bodyParametersComponent([$count, $schedule]),
                    ],
                    'es_MX',
                    [
                        'user_id' => $professional->id,
                        'daily_appointments_date' => $start->toDateString(),
                    ]
                );

                if ($result['success'] ?? false) {
                    $sent++;
                } else {
                    $failed++;
                    $this->error("Fallo el envio a {$professional->name}: ".($result['error'] ?? 'error desconocido'));
                }
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->error("Fallo el envio a {$professional->name}: {$exception->getMessage()}");
            }
        }

        $this->info("Resumen diario: {$sent} enviados, {$skipped} omitidos, {$failed} fallidos.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function appointmentsFor(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Appointment::query()
            ->with(['patient:id,name', 'user:id,name,contacto'])
            ->where('start', '>=', $start)
            ->where('start', '<', $end)
            ->where(fn ($query) => $query
                ->whereNull('statusUser')
                ->orWhereNotIn('statusUser', ['Cancel', 'Cancelado', 'Cancelada', 'cancel', 'cancelado', 'cancelada', 'Completed']))
            ->where(fn ($query) => $query
                ->whereNull('statusPatient')
                ->orWhereNotIn('statusPatient', ['Cancel', 'Cancelado', 'Cancelada', 'cancel', 'cancelado', 'cancelada', 'Completed']))
            ->where(fn ($query) => $query
                ->whereNull('state')
                ->orWhereNotIn('state', ['Cancel', 'Cancelado', 'Cancelada', 'cancel', 'cancelado', 'cancelada']))
            ->orderBy('start')
            ->get();
    }

    private function professionalPhone(array $contact, ?Profile $profile): ?string
    {
        $phone = data_get($contact, 'whatsapp')
            ?: data_get($contact, 'telefono')
            ?: data_get($contact, 'phone');

        if ($phone) {
            return (string) $phone;
        }

        return (string) ($profile?->whatsapp ?: $profile?->movil ?: '') ?: null;
    }

    private function alreadySent(
        int $userId,
        string $template,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): bool {
        return WhatsAppMessage::query()
            ->where('user_id', $userId)
            ->where('template', $template)
            ->whereIn('status', ['queued', 'sent', 'delivered', 'read'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->exists();
    }

    public static function formatAppointmentLine(string $patientName, CarbonInterface $start): string
    {
        $time = str_replace(':00', '', $start->format('g:ia'));

        return self::sanitizeTemplateText($patientName).' - '.$time;
    }

    public static function sanitizeTemplateText(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }
}
