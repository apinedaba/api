<?php

namespace App\Console\Commands;

use App\Models\OnDemandOffer;
use App\Models\OnDemandRequest;
use App\Services\OnDemandMatchingService;
use Illuminate\Console\Command;

class ProcessOnDemandMarketplace extends Command
{
    protected $signature = 'marketplace:on-demand-process';
    protected $description = 'Expira ofertas vencidas y busca nuevos profesionales para solicitudes on-demand';

    public function handle(OnDemandMatchingService $matching): int
    {
        $now = now(config('app.timezone'));
        OnDemandOffer::where('status', 'pending')->where('expires_at', '<=', $now)
            ->update(['status' => 'expired', 'responded_at' => $now]);

        OnDemandRequest::with('appointment.cart')
            ->whereIn('status', OnDemandRequest::ACTIVE_STATUSES)
            ->where('expires_at', '<=', $now)
            ->get()
            ->each(function (OnDemandRequest $request) {
                if ($request->status === 'awaiting_payment' && $request->appointment) {
                    $request->appointment->update([
                        'statusUser' => 'Cancel',
                        'statusPatient' => 'Cancel',
                        'state' => 'Cancelada',
                    ]);
                    $request->appointment->cart?->update(['estado' => 'expirado']);
                }
                $request->offers()->whereIn('status', ['pending', 'accepted'])->update(['status' => 'expired']);
                $request->update(['status' => 'expired']);
            });

        OnDemandRequest::query()
            ->whereIn('status', ['matching', 'offered'])
            ->where('expires_at', '>', $now)
            ->whereDoesntHave('offers', fn ($query) => $query->where('status', 'pending')->where('expires_at', '>', $now))
            ->each(fn (OnDemandRequest $request) => $matching->match($request));

        return self::SUCCESS;
    }
}
