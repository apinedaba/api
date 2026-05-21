<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'isProfileComplete',
        'personales',
        'address',
        'contacto',
        'educacion',
        'configurations',
        'horarios',
        'plan',
        'image',
        'stripe_id',
        'verification_code',
        'code_expires_at',
        'has_lifetime_access',
        'activo',
        'cedula_selfie_url',
        'ine_selfie_url',
        'identity_verification_status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'validado',
        'updated_at',
        'verification_code',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'code_expires_at' => 'datetime',
        'contacto' => 'array',
        'address' => 'array',
        'educacion' => 'array',
        'personales' => 'array',
        'configurations' => 'array',
        'horarios' => 'array',
        'image' => 'string',
        'isProfileComplete' => 'boolean',
        'activo' => 'boolean',
        'has_lifetime_access' => 'boolean',
    ];
    public function patientUsers()
    {
        return $this->hasMany(PatientUser::class, 'user', 'id')->with('patient');
    }
    public function appointment()
    {
        return $this->hasMany(Appointment::class, "user", "id");
    }
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function ownedClinics(): HasMany
    {
        return $this->hasMany(Clinic::class, 'owner_user_id');
    }

    public function clinicMemberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class);
    }

    public function primaryClinicMembership(): HasOne
    {
        return $this->hasOne(ClinicMembership::class)->where('is_primary', true);
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_memberships')
            ->withPivot(['role', 'is_primary', 'can_manage_schedule', 'can_manage_patients', 'can_view_finance', 'meta'])
            ->withTimestamps();
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationMembership::class)
            ->withPivot(['id', 'role', 'permissions', 'status'])
            ->withTimestamps();
    }

    public function activeOrganizationMembership(): ?OrganizationMembership
    {
        $activeOrganizationId = data_get($this->configurations, 'active_organization_id');

        if ($activeOrganizationId) {
            $membership = $this->organizationMemberships()
                ->with('organization')
                ->where('organization_id', $activeOrganizationId)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->first();

            if ($membership) {
                return $membership;
            }
        }

        return $this->organizationMemberships()
            ->with('organization')
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->first();
    }

    public function resolveOrganizationAccess(): array
    {
        $membership = $this->activeOrganizationMembership();

        return [
            'active_organization_id' => $membership?->organization_id,
            'active_organization_type' => $membership?->organization?->type,
            'role' => $membership?->role,
            'permissions' => $membership?->permissions ?? [],
            'can_manage_members' => $membership?->canManageMembers() ?? false,
            'memberships_count' => $this->organizationMemberships()
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->count(),
        ];
    }

    public function isClinicMember(): bool
    {
        return $this->clinicMemberships()->exists();
    }

    public function isIndependentProfessional(): bool
    {
        return !$this->isClinicMember();
    }

    public function currentClinicMembership(): ?ClinicMembership
    {
        if ($this->relationLoaded('clinicMemberships')) {
            $memberships = $this->clinicMemberships;
            return $memberships->firstWhere('is_primary', true) ?: $memberships->first();
        }

        return $this->primaryClinicMembership()->with('clinic')->first()
            ?: $this->clinicMemberships()->with('clinic')->first();
    }

    public function resolveWorkspaceAccess(): array
    {
        $membership = $this->currentClinicMembership();
        $role = $membership?->role;
        $allowedModules = data_get($membership?->meta, 'allowed_modules', []);
        $allowedModules = is_array($allowedModules) ? array_values(array_unique($allowedModules)) : [];

        $workspaceType = data_get($this->configurations, 'workspace_type', 'independent');
        if ($this->ownedClinics()->exists()) {
            $workspaceType = 'clinic';
        } elseif ($membership) {
            $workspaceType = 'clinic_member';
        }

        return [
            'workspace_type' => $workspaceType,
            'role' => $role,
            'clinic_id' => $membership?->clinic_id,
            'clinic_name' => $membership?->clinic?->name,
            'can_access_clinic_workspace' => $this->ownedClinics()->exists() || in_array($role, ['owner', 'manager', 'assistant'], true),
            'can_manage_clinic_team' => $this->ownedClinics()->exists() || in_array($role, ['owner', 'manager'], true),
            'allowed_modules' => $allowedModules,
        ];
    }

    public function hasMultiClinicPlan(): bool
    {
        $values = [
            data_get($this->configurations, 'workspace_plan_key'),
            data_get($this->configurations, 'clinic_plan_key'),
            data_get($this->configurations, 'plan_key'),
            $this->plan,
            optional($this->subscription)->stripe_plan,
            optional($this->subscription)->stripe_status,
        ];

        $haystack = collect($values)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => mb_strtolower($value))
            ->implode(' | ');

        return str_contains($haystack, 'multiclinica')
            || str_contains($haystack, 'multi-clinica')
            || str_contains($haystack, 'multi_clinica')
            || str_contains($haystack, 'multiclinic');
    }

    public function resolveClinicAccess(): array
    {
        $workspaceType = data_get($this->configurations, 'workspace_type', 'independent');
        $isClinicAccount = $workspaceType === 'clinic' || $this->ownedClinics()->exists();
        $isMultiClinic = $isClinicAccount && $this->hasMultiClinicPlan();
        $ownedClinicsCount = $this->ownedClinics()->count();

        return [
            'is_clinic_account' => $isClinicAccount,
            'is_multiclinic' => $isMultiClinic,
            'can_access_workspace' => $isClinicAccount,
            'can_create_additional_clinics' => $isMultiClinic,
            'clinics_count' => $ownedClinicsCount,
            'max_clinics' => $isMultiClinic ? null : ($isClinicAccount ? 1 : 0),
            'base_psychologist_limit' => $isMultiClinic ? null : 6,
            'psychologist_capacity_mode' => $isMultiClinic ? 'unlimited' : 'fixed',
        ];
    }
    public function googleAccount(): HasOne
    {
        return $this->hasOne(GoogleAccount::class);
    }
    public function escuelas()
    {
        return $this->hasMany(ValidacionCedulaManual::class);
    }

    /**
     * Relación con los consultorios
     */
    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    /**
     * Consultorio activo del usuario
     */
    public function activeOffice(): HasOne
    {
        return $this->hasOne(Office::class)->where('is_active', true);
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'notifiable');
    }

    public function sessionPackages(): HasMany
    {
        return $this->hasMany(SessionPackage::class);
    }

    public function activeSessionPackages(): HasMany
    {
        return $this->sessionPackages()->where('is_active', true);
    }

    public function discountCoupons(): HasMany
    {
        return $this->hasMany(DiscountCoupon::class);
    }

    public function activeDiscountCoupons(): HasMany
    {
        return $this->discountCoupons()->currentlyAvailable();
    }

    public function sellerReferral(): HasOne
    {
        return $this->hasOne(SellerReferral::class);
    }

    public function notificationBroadcastChannel(): string
    {
        return "user.{$this->id}";
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('activo', true)
            ->where('isProfileComplete', true)
            ->where('identity_verification_status', 'approved')
            ->whereNotNull('email_verified_at')
            ->where(function (Builder $visibilityQuery) {
                $visibilityQuery
                    ->where('has_lifetime_access', true)
                    ->orWhereHas('subscription', function (Builder $subscriptionQuery) {
                        $subscriptionQuery
                            ->where('stripe_status', 'active')
                            ->orWhere(function (Builder $trialQuery) {
                                $trialQuery
                                    ->where('stripe_status', 'trialing')
                                    ->whereNotNull('stripe_id');
                            });
                    });
            });
    }
}
