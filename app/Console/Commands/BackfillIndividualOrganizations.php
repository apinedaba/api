<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillIndividualOrganizations extends Command
{
    protected $signature = 'organizations:backfill-individuals {--dry-run : Report intended changes without writing}';

    protected $description = 'Create individual organizations for existing psychologists and attach legacy records safely.';

    public function handle(OrganizationService $organizations): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $memberships = 0;
        $appointments = 0;
        $patients = 0;

        User::query()
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($dryRun, $organizations, &$created, &$memberships, &$appointments, &$patients) {
                foreach ($users as $user) {
                    $organization = Organization::query()
                        ->where('owner_id', $user->id)
                        ->where('type', Organization::TYPE_INDIVIDUAL)
                        ->first();

                    if (!$organization) {
                        $created++;

                        if (!$dryRun) {
                            $organization = Organization::create([
                                'name' => $user->name ?: "Profesional {$user->id}",
                                'slug' => $organizations->uniqueSlug($user->name ?: "profesional-{$user->id}"),
                                'type' => Organization::TYPE_INDIVIDUAL,
                                'owner_id' => $user->id,
                                'settings' => ['created_by_backfill' => true],
                            ]);
                        }
                    }

                    if (!$organization && $dryRun) {
                        continue;
                    }

                    $membershipExists = OrganizationMembership::query()
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $user->id)
                        ->exists();

                    if (!$membershipExists) {
                        $memberships++;

                        if (!$dryRun) {
                            OrganizationMembership::query()->create([
                                'organization_id' => $organization->id,
                                'user_id' => $user->id,
                                'role' => OrganizationMembership::ROLE_OWNER,
                                'permissions' => ['*'],
                                'status' => OrganizationMembership::STATUS_ACTIVE,
                            ]);
                        }
                    }

                    if (!$dryRun) {
                        $appointments += DB::table('appointments')
                            ->where('user', $user->id)
                            ->whereNull('organization_id')
                            ->update(['organization_id' => $organization->id]);

                        $patientIds = DB::table('patient_users')
                            ->where('user', $user->id)
                            ->pluck('patient')
                            ->filter()
                            ->unique()
                            ->values();

                        if ($patientIds->isNotEmpty()) {
                            $patients += DB::table('patients')
                                ->whereIn('id', $patientIds)
                                ->whereNull('organization_id')
                                ->update(['organization_id' => $organization->id]);
                        }
                    }
                }
            });

        $this->info(($dryRun ? 'Dry run complete.' : 'Backfill complete.'));
        $this->line("Organizations to create/created: {$created}");
        $this->line("Memberships to create/created: {$memberships}");
        $this->line("Appointments updated: {$appointments}");
        $this->line("Patients updated: {$patients}");

        return self::SUCCESS;
    }
}
