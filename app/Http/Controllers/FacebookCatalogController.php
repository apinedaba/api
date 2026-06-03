<?php

namespace App\Http\Controllers;

use App\Models\FacebookCatalogItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FacebookCatalogController extends Controller
{
    public function index(): InertiaResponse
    {
        $users = User::query()
            ->with([
                'subscription',
                'facebookCatalogItem',
                'escuelas' => fn ($query) => $query->orderByDesc('id'),
                'activeSessionPackages',
                'activeOffice',
            ])
            ->orderBy('name')
            ->get();

        $entries = $users
            ->map(fn (User $user) => $this->buildCatalogEntry($user))
            ->values();

        return Inertia::render('FacebookCatalog', [
            'entries' => $entries,
            'summary' => [
                'total' => $entries->count(),
                'published' => $entries->where('feed_status', 'published')->count(),
                'with_overrides' => $entries->filter(fn ($entry) => $entry['has_overrides'])->count(),
                'ready' => $entries->filter(fn ($entry) => $entry['catalog_ready'])->count(),
            ],
            'feedUrl' => route('facebook-catalog.feed'),
        ]);
    }

    public function upsert(Request $request, User $user)
    {
        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'custom_title' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string', 'max:1200'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'custom_currency' => ['nullable', 'string', 'max:10'],
            'custom_therapy_type' => ['nullable', 'string', 'max:120'],
            'custom_certification' => ['nullable', 'string', 'max:160'],
            'custom_image_url' => ['nullable', 'url', 'max:2048'],
            'custom_public_url' => ['nullable', 'url', 'max:2048'],
            'custom_schedule_summary' => ['nullable', 'string', 'max:255'],
            'custom_availability' => ['nullable', 'string', 'max:40'],
        ]);

        $validated['custom_currency'] = $validated['custom_currency'] ?: 'MXN';

        FacebookCatalogItem::query()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return redirect()
            ->route('facebook-catalog.index')
            ->with('status', 'Catalogo de Facebook actualizado correctamente.');
    }

    public function feed()
    {
        $users = User::query()
            ->with([
                'facebookCatalogItem',
                'subscription',
                'escuelas' => fn ($query) => $query->orderByDesc('id'),
                'activeSessionPackages',
                'activeOffice',
            ])
            ->publiclyVisible()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->buildCatalogEntry($user))
            ->filter(fn ($entry) => $entry['feed_status'] === 'published')
            ->values();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'id',
            'title',
            'description',
            'availability',
            'condition',
            'price',
            'link',
            'image_link',
            'brand',
            'google_product_category',
            'custom_label_0',
            'custom_label_1',
            'custom_label_2',
            'custom_label_3',
        ]);

        foreach ($users as $entry) {
            fputcsv($handle, [
                $entry['id'],
                $entry['effective']['title'],
                $entry['effective']['description'],
                $entry['effective']['availability'],
                'new',
                number_format((float) $entry['effective']['price'], 2, '.', '') . ' ' . $entry['effective']['currency'],
                $entry['effective']['public_url'],
                $entry['effective']['image_url'],
                'MindMeet',
                'Salud y bienestar > Psicologia',
                $entry['effective']['therapy_type'],
                $entry['effective']['certification'],
                $entry['effective']['schedule_summary'],
                $entry['psychologist']['name'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="mindmeet-facebook-catalog.csv"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function buildCatalogEntry(User $user): array
    {
        $item = $user->facebookCatalogItem;
        $defaults = [
            'title' => $this->resolveTitle($user),
            'description' => $this->resolveDescription($user),
            'price' => $this->resolvePrice($user),
            'currency' => $item?->custom_currency ?: 'MXN',
            'therapy_type' => $this->resolveTherapyType($user),
            'certification' => $this->resolveCertification($user),
            'image_url' => $user->image,
            'public_url' => $this->resolvePublicUrl($user),
            'schedule_summary' => $this->resolveScheduleSummary($user),
            'availability' => $this->resolveAvailability($user),
        ];

        $effective = [
            'title' => $item?->custom_title ?: $defaults['title'],
            'description' => $item?->custom_description ?: $defaults['description'],
            'price' => $item?->custom_price !== null ? (float) $item->custom_price : $defaults['price'],
            'currency' => $item?->custom_currency ?: $defaults['currency'],
            'therapy_type' => $item?->custom_therapy_type ?: $defaults['therapy_type'],
            'certification' => $item?->custom_certification ?: $defaults['certification'],
            'image_url' => $item?->custom_image_url ?: $defaults['image_url'],
            'public_url' => $item?->custom_public_url ?: $defaults['public_url'],
            'schedule_summary' => $item?->custom_schedule_summary ?: $defaults['schedule_summary'],
            'availability' => $item?->custom_availability ?: $defaults['availability'],
        ];

        $catalogReady = filled($effective['title'])
            && filled($effective['description'])
            && !is_null($effective['price'])
            && filled($effective['image_url'])
            && filled($effective['public_url']);

        $publiclyVisible = $this->isPubliclyVisible($user);
        $enabled = $item ? (bool) $item->is_enabled : true;
        $feedStatus = $publiclyVisible && $enabled && $catalogReady ? 'published' : 'draft';

        return [
            'id' => $user->id,
            'psychologist' => [
                'name' => $this->resolveDisplayName($user),
                'email' => $user->email,
                'image' => $user->image,
                'identity_verification_status' => $user->identity_verification_status,
                'publicly_visible' => $publiclyVisible,
                'subscription_status' => $user->has_lifetime_access
                    ? 'lifetime'
                    : optional($user->subscription)->stripe_status,
            ],
            'defaults' => $defaults,
            'effective' => $effective,
            'overrides' => [
                'is_enabled' => $enabled,
                'custom_title' => $item?->custom_title,
                'custom_description' => $item?->custom_description,
                'custom_price' => $item?->custom_price !== null ? (string) $item->custom_price : '',
                'custom_currency' => $item?->custom_currency ?: 'MXN',
                'custom_therapy_type' => $item?->custom_therapy_type,
                'custom_certification' => $item?->custom_certification,
                'custom_image_url' => $item?->custom_image_url,
                'custom_public_url' => $item?->custom_public_url,
                'custom_schedule_summary' => $item?->custom_schedule_summary,
                'custom_availability' => $item?->custom_availability,
            ],
            'feed_status' => $feedStatus,
            'catalog_ready' => $catalogReady,
            'has_overrides' => (bool) $item,
            'public_url' => $effective['public_url'],
        ];
    }

    private function resolveDisplayName(User $user): string
    {
        return data_get($user->contacto, 'publicName')
            ?: $user->name;
    }

    private function resolveTitle(User $user): string
    {
        return $this->resolveDisplayName($user);
    }

    private function resolveDescription(User $user): string
    {
        $littleDescription = trim((string) data_get($user->educacion, 'littleDescription', ''));
        if ($littleDescription !== '') {
            return Str::limit($littleDescription, 240, '');
        }

        $specialties = collect(data_get($user->educacion, 'especialidades', []))
            ->filter()
            ->take(3)
            ->implode(', ');
        $enfoque = trim((string) data_get($user->educacion, 'enfoque', ''));

        $parts = collect([
            $specialties ? "Especialidades: {$specialties}." : null,
            $enfoque ? "Enfoque: {$enfoque}." : null,
            'Agenda tu primera sesion en MindMeet.',
        ])->filter();

        return $parts->implode(' ');
    }

    private function resolveTherapyType(User $user): string
    {
        $sessions = collect(data_get($user->configurations, 'sesiones', []))
            ->filter(fn ($session) => filled(data_get($session, 'tipoSesion')));

        if ($sessions->isNotEmpty()) {
            return (string) $sessions->pluck('tipoSesion')->filter()->first();
        }

        $packageType = $user->activeSessionPackages
            ->pluck('tipo_sesion')
            ->filter()
            ->first();

        return $packageType ?: 'Terapia individual';
    }

    private function resolvePrice(User $user): ?float
    {
        $sessionPrices = collect(data_get($user->configurations, 'sesiones', []))
            ->map(fn ($session) => data_get($session, 'precio'))
            ->filter(fn ($price) => is_numeric($price))
            ->map(fn ($price) => (float) $price);

        $packagePrices = $user->activeSessionPackages
            ->map(fn ($package) => $package->promotional_session_price ?: $package->package_session_price)
            ->filter(fn ($price) => is_numeric($price))
            ->map(fn ($price) => (float) $price);

        $allPrices = $sessionPrices->merge($packagePrices)->sort()->values();

        return $allPrices->isNotEmpty() ? (float) $allPrices->first() : null;
    }

    private function resolveCertification(User $user): string
    {
        $cedula = $user->escuelas
            ->pluck('numero_cedula')
            ->filter()
            ->first();

        if ($cedula) {
            return 'Cedula profesional ' . $cedula;
        }

        if ($user->identity_verification_status === 'approved') {
            return 'Identidad verificada por MindMeet';
        }

        return 'Perfil profesional en revision';
    }

    private function resolvePublicUrl(User $user): string
    {
        $baseUrl = rtrim(
            config('app.front_url_user')
            ?: config('app.front_url')
            ?: config('app.frontend_url')
            ?: 'https://mindmeet.com.mx',
            '/'
        );
        $slug = Str::slug($this->resolveDisplayName($user), '-', 'es');

        return "{$baseUrl}/psicologos/{$user->id}/{$slug}";
    }

    private function resolveScheduleSummary(User $user): string
    {
        $horarios = collect($user->horarios ?? []);
        $days = [
            'monday' => 'Lun',
            'tuesday' => 'Mar',
            'wednesday' => 'Mie',
            'thursday' => 'Jue',
            'friday' => 'Vie',
            'saturday' => 'Sab',
            'sunday' => 'Dom',
        ];

        $summary = collect($days)
            ->map(function ($label, $dayKey) use ($horarios) {
                $ranges = collect($horarios->get($dayKey, []))
                    ->filter(fn ($range) => filled(data_get($range, 'start')) && filled(data_get($range, 'end')))
                    ->take(1)
                    ->map(fn ($range) => data_get($range, 'start') . '-' . data_get($range, 'end'))
                    ->implode(', ');

                return $ranges ? "{$label} {$ranges}" : null;
            })
            ->filter()
            ->take(3)
            ->implode(' | ');

        if ($summary !== '') {
            return $summary;
        }

        if ($user->activeOffice) {
            return 'Presencial en ' . trim($user->activeOffice->city . ', ' . $user->activeOffice->state, ', ');
        }

        return 'Horarios bajo solicitud';
    }

    private function resolveAvailability(User $user): string
    {
        return collect($user->horarios ?? [])->flatten(1)->isNotEmpty()
            ? 'in stock'
            : 'available for order';
    }

    private function isPubliclyVisible(User $user): bool
    {
        $subscriptionStatus = optional($user->subscription)->stripe_status;
        $subscriptionStripeId = optional($user->subscription)->stripe_id;

        return (bool) $user->activo
            && (bool) $user->isProfileComplete
            && $user->identity_verification_status === 'approved'
            && !is_null($user->email_verified_at)
            && (
                $user->has_lifetime_access
                || $subscriptionStatus === 'active'
                || ($subscriptionStatus === 'trialing' && filled($subscriptionStripeId))
            );
    }
}
