<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RedPreguntaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $tipo  'nueva_pregunta' | 'nueva_respuesta' | 'pregunta_eliminada'
     * @param  int     $preguntaId
     */
    public function __construct(
        public readonly string $tipo,
        public readonly int $preguntaId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('red.preguntas'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'red-pregunta-actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'tipo'        => $this->tipo,
            'pregunta_id' => $this->preguntaId,
        ];
    }
}
